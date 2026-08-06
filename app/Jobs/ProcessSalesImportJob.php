<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use App\Models\Produk;
use App\Models\Supplier;
use App\Models\Shop;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Services\EnsureSaleSupplierLedgerService;
use Carbon\Carbon;

class ProcessSalesImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Allow up to 2 hours for very large imports (100k+ rows) */
    public $timeout = 7200;

    protected $progressKey;
    /** @var int Number of cache batches to process (each batch loaded from cache) */
    protected $totalBatches;
    protected $totalReceipts;
    protected $userId;
    /** Receipts per batch when processing (matches SalesImport::RECEIPT_BATCH_SIZE) */
    protected $batchSize = 1000;

    /** Max rows per receipt to avoid one mis-grouped receipt stalling import for hours */
    const MAX_ROWS_PER_RECEIPT = 2500;

    /** In-memory lookups for the duration of this job (avoids repeated SELECT per line) */
    protected $supplierCache = [];

    protected $shopCache = [];

    protected $productCache = [];

    protected $existingSaleCache = [];

    /** @var array<string, int> receipt|Y-m-d => penjualan id */
    protected $existingSaleIdByKey = [];

    /** @var array<int, int> penjualan id => detail line count */
    protected $existingDetailCountCache = [];

    protected $importMode = 'standard';

    protected $yearFrom = 0;

    protected $yearTo = 0;

    protected $reimportFastSkipped = 0;

    /**
     * Create a new job instance. No large payload - batches are loaded from cache.
     */
    public function __construct($progressKey, $totalBatches, $totalReceipts, $userId)
    {
        $this->progressKey = $progressKey;
        $this->totalBatches = (int) $totalBatches;
        $this->totalReceipts = (int) $totalReceipts;
        $this->userId = $userId;

        ini_set('memory_limit', '2048M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $totalReceipts = $this->totalReceipts;
            $processedCount = 0;
            $importedCount = 0;
            $errors = [];
            $warnings = [];

            Cache::put($this->progressKey, [
                'status' => 'processing',
                'phase' => 'importing',
                'current' => 0,
                'total' => $totalReceipts,
                'receipt_groups' => $totalReceipts,
                'percentage' => 40,
                'message' => "Importing {$totalReceipts} receipt groups into sales and stock…",
            ], 900);

            $columnMap = Cache::get("import_column_map_{$this->progressKey}", $this->getDefaultColumnMap());

            $this->importMode = (string) Cache::get("import_mode_{$this->progressKey}", 'standard');
            $this->yearFrom = (int) Cache::get("import_year_from_{$this->progressKey}", 0);
            $this->yearTo = (int) Cache::get("import_year_to_{$this->progressKey}", 0);

            if ($this->importMode === 'reimport' && $this->yearFrom > 0 && $this->yearTo > 0) {
                $this->runReimportPath($columnMap, $totalReceipts, $processedCount, $importedCount, $errors, $warnings);

                return;
            }

            $this->warmupLookupCaches();

            // Load and process one cache batch at a time (no huge payload in memory)
            for ($batchIndex = 0; $batchIndex < $this->totalBatches; $batchIndex++) {
                $batch = Cache::get("import_grouped_{$this->progressKey}_batch_{$batchIndex}", []);

                if (empty($batch)) {
                    continue;
                }

                $batchLineCounts = Cache::get("import_line_counts_{$this->progressKey}_batch_{$batchIndex}", []);
                if ($batchLineCounts === [] && $batchIndex === $this->totalBatches - 1) {
                    $batchLineCounts = array_merge(
                        $batchLineCounts,
                        Cache::get("import_line_counts_{$this->progressKey}", [])
                    );
                }

                // Process batch with retry logic for deadlocks
                $batchProcessed = $this->processBatchWithRetry($batch, $batchIndex, $processedCount, $totalReceipts, $columnMap, $this->progressKey, $batchLineCounts);
                
                $processedCount = $batchProcessed['processedCount'];
                $importedCount += $batchProcessed['importedCount'];
                $errors = array_merge($errors, $batchProcessed['errors']);
                $warnings = array_merge($warnings, $batchProcessed['warnings']);

                if (! empty($batchProcessed['batch_ok'])) {
                    Cache::forget("import_grouped_{$this->progressKey}_batch_{$batchIndex}");
                }
                unset($batch);

                if ($batchIndex % 2 == 1) {
                    gc_collect_cycles();
                }
            }

            $this->finalizeImportJob($importedCount, $totalReceipts, $errors, $warnings);

        } catch (\Exception $e) {
            Log::error("Fatal error in ProcessSalesImportJob: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            Cache::put($this->progressKey, [
                'status' => 'error',
                'current' => 0,
                'total' => 0,
                'percentage' => 0,
                'message' => 'Fatal error: ' . $e->getMessage()
            ], 600);
        }
    }

    /**
     * Default column indices (must match SalesImport::DEFAULT_COL_MAP when no header detected).
     */
    protected function getDefaultColumnMap(): array
    {
        return [
            'supplier' => 0, 'stockOut' => 1, 'commodity' => 2, 'salesDate' => 3, 'paymentMethod' => 4,
            'confirmPrice' => 5, 'shop' => 6, 'receipt' => 7, 'totalAmount' => 9, 'buyingPrice' => 10, 'sellingPrice' => 11,
        ];
    }

    /**
     * Normalize product/commodity name for matching: trim and collapse whitespace.
     */
    protected function normalizeProductName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * Excel rows may be 1-based or sparse; normalize to 0..n-1 for column map indices.
     */
    protected function normalizeImportRowArray(array $row): array
    {
        if ($row === []) {
            return [];
        }

        $numeric = [];
        $hasStringKeys = false;
        foreach ($row as $k => $v) {
            if (is_string($k) && ! is_numeric($k)) {
                $hasStringKeys = true;
                break;
            }
            if (is_numeric($k)) {
                $numeric[(int) $k] = $v;
            }
        }

        if ($hasStringKeys) {
            return $row;
        }

        if ($numeric === []) {
            return $row;
        }

        ksort($numeric);
        $min = min(array_keys($numeric));
        if ($min === 0) {
            return $numeric;
        }

        $out = [];
        foreach ($numeric as $k => $v) {
            $out[$k - $min] = $v;
        }

        return $out;
    }

    protected function importCellToString($cell): string
    {
        if ($cell === null) {
            return '';
        }
        if ($cell instanceof \DateTimeInterface) {
            return $cell->format('Y-m-d');
        }
        if (is_bool($cell)) {
            return $cell ? '1' : '0';
        }
        if (is_float($cell) || is_int($cell)) {
            return (string) $cell;
        }

        return trim((string) $cell);
    }

    /**
     * Get value from row by column key using map (handles array and object row).
     */
    protected function getRowVal($row, string $key, array $columnMap, $default = '')
    {
        $arr = $this->normalizeImportRowArray(is_array($row) ? $row : (array) $row);

        $headerAliases = [
            'commodity' => ['commodity', 'product name', 'product', 'description'],
            'supplier' => ['supplier'],
            'stockOut' => ['stock out', 'stockout', 'stock', 'qty', 'quantity'],
            'salesDate' => ['sales date', 'saledate', 'date'],
            'paymentMethod' => ['means of payment', 'means', 'payment', 'mop'],
            'confirmPrice' => ['confirm price', 'confirm pr', 'confirm'],
            'shop' => ['shop'],
            'receipt' => ['receipt'],
            'totalAmount' => ['total amount', 'total amc', 'total'],
            'buyingPrice' => ['buying price', 'buying pri', 'buying', 'cost'],
            'sellingPrice' => ['selling price', 'selling pri', 'selling'],
        ];

        foreach ($headerAliases[$key] ?? [] as $alias) {
            foreach ($arr as $k => $v) {
                if (is_string($k) && strtolower(trim($k)) === $alias) {
                    return $this->importCellToString($v) ?: $default;
                }
            }
        }

        $col = $columnMap[$key] ?? null;
        if ($col === null) {
            return $default;
        }

        $val = $arr[$col] ?? $default;

        return $val === null || $val === '' ? $default : $this->importCellToString($val);
    }

    /**
     * Parse a numeric value from Excel (handles comma thousands separator and float drift).
     * Excel may export "1,500.00" as string; floatval("1,500.00") returns 1. Round to 2 decimals for money.
     */
    protected function parseNumericFromExcel($value, $default = 0, int $decimals = 2): float
    {
        if ($value === null || $value === '') {
            return (float) $default;
        }
        if (is_numeric($value)) {
            return round((float) $value, $decimals);
        }
        $str = trim((string) $value);
        $str = preg_replace('/[^\d.-]/', '', $str);
        if ($str === '' || $str === '-') {
            return (float) $default;
        }
        return round((float) $str, $decimals);
    }

    /**
     * Parse grouped cache key: receipt|Y-m-d or legacy receipt|supplier.
     */
    protected function parseReceiptGroupKey(string $groupKey): array
    {
        $parts = explode('|', $groupKey);
        $receipt = trim($parts[0] ?? '');
        $second = trim($parts[1] ?? '');
        $third = trim($parts[2] ?? '');
        $salesDate = '';
        $supplierHint = '';

        if ($second !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $second)) {
            $salesDate = $second;
            $supplierHint = $third;
        } else {
            $supplierHint = $second;
        }

        return [
            'receipt' => $receipt,
            'sales_date' => $salesDate,
            'supplier_hint' => $supplierHint,
            'group_key' => $groupKey,
            'is_legacy_supplier_key' => $salesDate === '' && $supplierHint !== '',
        ];
    }

    /**
     * Reimport: index DB, scan line-count metadata only, import missing/partial receipts only.
     */
    protected function runReimportPath(array $columnMap, int $totalReceiptsExcel, int &$processedCount, int &$importedCount, array &$errors, array &$warnings): void
    {
        Cache::put($this->progressKey, [
            'status' => 'processing',
            'phase' => 'prefiltering',
            'current' => 0,
            'total' => $totalReceiptsExcel,
            'receipt_groups' => $totalReceiptsExcel,
            'percentage' => 36,
            'message' => "Reimport {$this->yearFrom}–{$this->yearTo}: comparing {$totalReceiptsExcel} Excel receipts to the database…",
        ], 900);

        $this->warmupExistingSalesForReimport($this->yearFrom, $this->yearTo);

        $manifest = $this->buildReimportPendingManifest($columnMap);
        $pending = $manifest['pending'];
        $this->reimportFastSkipped = $manifest['skipped'];
        $pendingCount = count($pending);

        Cache::put($this->progressKey, [
            'status' => 'processing',
            'phase' => 'importing',
            'current' => 0,
            'total' => $pendingCount,
            'receipt_groups' => $totalReceiptsExcel,
            'percentage' => 41,
            'message' => $pendingCount > 0
                ? "Reimport: {$this->reimportFastSkipped} already complete, importing {$pendingCount} missing or partial receipt(s)…"
                : "Reimport: all {$totalReceiptsExcel} receipt groups are already in the database.",
        ], 900);

        if ($pendingCount === 0) {
            $this->clearImportGroupedCaches();
            $this->finalizeImportJob($importedCount, $totalReceiptsExcel, $errors, $warnings);

            return;
        }

        $this->warmupLookupCaches();
        $this->processReimportPendingQueue($pending, $columnMap, $processedCount, $importedCount, $errors, $warnings);
        $this->clearImportGroupedCaches();
        $this->finalizeImportJob($importedCount, $totalReceiptsExcel, $errors, $warnings);
    }

    /**
     * Scan line-count caches (lightweight) to find receipts that still need importing.
     *
     * @return array{pending: array<int, array{batch_index: int, group_key: string}>, skipped: int, scanned: int}
     */
    protected function buildReimportPendingManifest(array $columnMap): array
    {
        $pending = [];
        $skipped = 0;
        $scanned = 0;

        for ($batchIndex = 0; $batchIndex < $this->totalBatches; $batchIndex++) {
            $counts = Cache::get("import_line_counts_{$this->progressKey}_batch_{$batchIndex}", []);

            if ($batchIndex === $this->totalBatches - 1) {
                $counts = array_merge($counts, Cache::get("import_line_counts_{$this->progressKey}", []));
            }

            if ($counts === []) {
                $batch = Cache::get("import_grouped_{$this->progressKey}_batch_{$batchIndex}", []);
                foreach ($batch as $groupKey => $saleRows) {
                    $scanned++;
                    $excelLines = $this->countValidExcelLines($saleRows, $columnMap);
                    if ($this->reimportReceiptIsFullyImported($groupKey, $saleRows, $columnMap, $excelLines)) {
                        $skipped++;
                    } else {
                        $pending[] = ['batch_index' => $batchIndex, 'group_key' => $groupKey];
                    }
                }
                unset($batch);
            } else {
                foreach ($counts as $groupKey => $excelLines) {
                    $scanned++;
                    if ($this->reimportReceiptIsFullyImported($groupKey, [], $columnMap, (int) $excelLines)) {
                        $skipped++;
                    } else {
                        $pending[] = ['batch_index' => $batchIndex, 'group_key' => $groupKey];
                    }
                }
            }

            if ($scanned > 0 && $scanned % 3000 === 0) {
                Cache::put($this->progressKey, [
                    'status' => 'processing',
                    'phase' => 'prefiltering',
                    'current' => $scanned,
                    'total' => $this->totalReceipts,
                    'percentage' => 39,
                    'message' => "Reimport: compared {$scanned}/{$this->totalReceipts} Excel receipts to the database…",
                ], 900);
            }
        }

        return [
            'pending' => $pending,
            'skipped' => $skipped,
            'scanned' => $scanned,
        ];
    }

    /**
     * Import only receipts identified as missing or partial (does not load already-complete groups).
     */
    protected function processReimportPendingQueue(array $pending, array $columnMap, int &$processedCount, int &$importedCount, array &$errors, array &$warnings): void
    {
        $totalWork = count($pending);
        $loadedBatches = [];
        $remainderBatch = null;

        foreach ($pending as $item) {
            $processedCount++;
            $batchIndex = (int) $item['batch_index'];
            $groupKey = (string) $item['group_key'];

            if ($processedCount % 25 === 0 || $processedCount === $totalWork) {
                $pct = 41 + round(($processedCount / max($totalWork, 1)) * 54, 2);
                Cache::put($this->progressKey, [
                    'status' => 'processing',
                    'phase' => 'importing',
                    'current' => $processedCount,
                    'total' => $totalWork,
                    'percentage' => $pct,
                    'message' => "Reimport: importing {$processedCount}/{$totalWork} missing or partial receipt(s)…",
                ], 900);
            }

            if (! isset($loadedBatches[$batchIndex])) {
                $loadedBatches[$batchIndex] = Cache::get("import_grouped_{$this->progressKey}_batch_{$batchIndex}", []);
            }

            $saleRows = $loadedBatches[$batchIndex][$groupKey] ?? null;
            if ($saleRows === null) {
                if ($remainderBatch === null) {
                    $remainderBatch = Cache::get("import_grouped_{$this->progressKey}", []);
                }
                $saleRows = $remainderBatch[$groupKey] ?? null;
            }

            if ($saleRows === null || $saleRows === []) {
                if (count($errors) < 50) {
                    $errors[] = "Receipt {$groupKey}: Excel rows not found in cache.";
                }
                continue;
            }

            $parsed = $this->parseReceiptGroupKey($groupKey);
            $receiptLabel = $parsed['receipt'];
            $receiptAttempt = 0;
            $receiptDone = false;

            while ($receiptAttempt < 3 && ! $receiptDone) {
                $receiptAttempt++;
                try {
                    DB::beginTransaction();
                    $result = $this->processReceipt(
                        $groupKey,
                        $saleRows,
                        $columnMap,
                        $this->progressKey,
                        $processedCount,
                        $totalWork
                    );
                    DB::commit();
                    $receiptDone = true;

                    if ($result['success'] && ! empty($result['actually_imported'])) {
                        $importedCount++;
                    }
                    if (count($errors) < 100) {
                        $errors = array_merge($errors, $result['errors'] ?? []);
                    }
                    if (count($warnings) < 100) {
                        $warnings = array_merge($warnings, $result['warnings'] ?? []);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    $isDeadlock = strpos($e->getMessage(), 'Deadlock') !== false
                        || strpos($e->getMessage(), '1213') !== false;
                    if ($isDeadlock && $receiptAttempt < 3) {
                        usleep(min(200000 * $receiptAttempt, 1000000));
                        continue;
                    }
                    if (count($errors) < 100) {
                        $errors[] = "Receipt {$receiptLabel}: " . $e->getMessage();
                    }
                    $receiptDone = true;
                }
            }
        }

        unset($loadedBatches, $remainderBatch);
    }

    protected function clearImportGroupedCaches(): void
    {
        for ($batchIndex = 0; $batchIndex < $this->totalBatches; $batchIndex++) {
            Cache::forget("import_grouped_{$this->progressKey}_batch_{$batchIndex}");
            Cache::forget("import_line_counts_{$this->progressKey}_batch_{$batchIndex}");
        }
        Cache::forget("import_grouped_{$this->progressKey}");
        Cache::forget("import_line_counts_{$this->progressKey}");
    }

    protected function finalizeImportJob(int $importedCount, int $totalReceipts, array $errors, array $warnings): void
    {
        $importMode = $this->importMode;
        $yearFrom = $this->yearFrom;
        $yearTo = $this->yearTo;

        $skippedRows = (int) Cache::get("import_skipped_rows_{$this->progressKey}", 0);
        if ($skippedRows > 0) {
            $warnings[] = "{$skippedRows} Excel row(s) were skipped because no receipt number could be read (blank or invalid format).";
        }

        if ($importMode === 'reimport' && $yearFrom > 0 && $yearTo > 0) {
            $warnings[] = "Reimport mode: only sales dated {$yearFrom}–{$yearTo} were processed; same receipt number in a different year creates a separate sale.";
            if ($this->reimportFastSkipped > 0) {
                $warnings[] = "{$this->reimportFastSkipped} receipt group(s) were already complete and were not re-processed.";
            }
        }

        $skippedReceipts = $this->reimportFastSkipped;

        if ($importMode === 'reimport' && $this->reimportFastSkipped > 0) {
            $message = "Reimport finished: {$importedCount} receipt group(s) imported or updated.";
            $message .= " {$this->reimportFastSkipped} were already in the system (from {$totalReceipts} in Excel).";
        } else {
            $message = "Successfully imported {$importedCount} of {$totalReceipts} receipt group(s).";
        }

        if (count($errors) > 0) {
            $message .= ' ' . count($errors) . ' errors occurred.';
        }

        $reportPath = 'import_reports/' . str_replace('import_progress_', '', $this->progressKey) . '.json';
        try {
            \Illuminate\Support\Facades\Storage::disk('local')->put($reportPath, json_encode([
                'imported_count' => $importedCount,
                'total_receipts' => $totalReceipts,
                'reimport_skipped_complete' => $this->reimportFastSkipped,
                'skipped_excel_rows' => $skippedRows,
                'errors' => array_slice($errors, 0, 200),
                'warnings' => array_slice($warnings, 0, 200),
                'completed_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('Could not write import report file: ' . $e->getMessage());
        }

        Cache::put($this->progressKey, [
            'status' => 'completed',
            'current' => $totalReceipts,
            'total' => $totalReceipts,
            'percentage' => 100,
            'message' => $message,
            'imported_count' => $importedCount,
            'skipped_receipts' => $skippedReceipts,
            'skipped_excel_rows' => $skippedRows,
            'errors' => array_slice($errors, 0, 50),
            'warnings' => array_slice($warnings, 0, 50),
            'report_path' => $reportPath,
        ], 600);
    }

    /**
     * Preload suppliers and shops once per job (historical imports hit the same names repeatedly).
     */
    protected function warmupLookupCaches(): void
    {
        foreach (Supplier::select('id_supplier', 'nama', 'mop', 'alamat', 'telepon')->get() as $supplier) {
            $this->supplierCache[$supplier->nama] = $supplier;
        }

        foreach (Shop::select('id', 'shop_name', 'shop_code')->get() as $shop) {
            $this->shopCache[$shop->shop_name] = $shop;
            if (! empty($shop->shop_code)) {
                $this->shopCache[$shop->shop_code] = $shop;
            }
        }
    }

    /**
     * Bulk-load sales + line counts for the reimport year range (avoids per-receipt SELECT).
     */
    protected function warmupExistingSalesForReimport(int $yearFrom, int $yearTo): void
    {
        $from = sprintf('%d-01-01', $yearFrom);
        $to = sprintf('%d-12-31', $yearTo);
        $indexed = 0;

        Cache::put($this->progressKey, [
            'status' => 'processing',
            'phase' => 'prefiltering',
            'current' => 0,
            'total' => $this->totalReceipts,
            'percentage' => 38,
            'message' => "Reimport {$yearFrom}–{$yearTo}: indexing existing sales (chunked, not loading entire year into memory)…",
        ], 900);

        Penjualan::query()
            ->whereBetween('saledate', [$from, $to])
            ->select('id_penjualan', 'receiptno', 'saledate')
            ->orderBy('id_penjualan')
            ->chunk(5000, function ($sales) use (&$indexed) {
                foreach ($sales as $sale) {
                    $indexed++;
                    $id = (int) $sale->id_penjualan;
                    $date = Carbon::parse($sale->saledate)->toDateString();
                    $stored = trim((string) $sale->receiptno);
                    $clean = $this->normalizeReceiptFromStored($stored);
                    $groupKey = $date !== '' ? $clean . '|' . $date : $clean;

                    $this->indexExistingSaleId($clean, $date, $groupKey, $id);
                    if ($stored !== '' && $stored !== $clean && $stored !== $groupKey) {
                        $this->indexExistingSaleId($clean, $date, $stored, $id);
                    }
                }

                if ($indexed % 10000 === 0) {
                    Cache::put($this->progressKey, [
                        'status' => 'processing',
                        'phase' => 'prefiltering',
                        'current' => 0,
                        'total' => $this->totalReceipts,
                        'percentage' => 39,
                        'message' => "Reimport: indexed {$indexed} existing sales for {$this->yearFrom}–{$this->yearTo}…",
                    ], 900);
                }
            });

        Cache::put($this->progressKey, [
            'status' => 'processing',
            'phase' => 'prefiltering',
            'current' => 0,
            'total' => $this->totalReceipts,
            'percentage' => 39,
            'message' => "Reimport: loading line counts for {$indexed} sales…",
        ], 900);

        $counts = DB::table('penjualan_detail as d')
            ->join('penjualan as p', 'p.id_penjualan', '=', 'd.id_penjualan')
            ->whereBetween('p.saledate', [$from, $to])
            ->groupBy('d.id_penjualan')
            ->selectRaw('d.id_penjualan, COUNT(*) as line_count')
            ->pluck('line_count', 'id_penjualan');

        foreach ($counts as $id => $count) {
            $this->existingDetailCountCache[(int) $id] = (int) $count;
        }

        Cache::put($this->progressKey, [
            'status' => 'processing',
            'phase' => 'importing',
            'current' => 0,
            'total' => $this->totalReceipts,
            'receipt_groups' => $this->totalReceipts,
            'percentage' => 40,
            'message' => "Reimport ready: {$indexed} sales indexed, {$this->totalReceipts} Excel receipt groups to check…",
        ], 900);
    }

    protected function normalizeReceiptFromStored(string $receiptno): string
    {
        $parts = explode('|', $receiptno);

        return trim($parts[0] ?? $receiptno);
    }

    protected function indexExistingSaleId(string $cleanReceipt, string $salesDate, string $groupKey, int $saleId): void
    {
        if ($salesDate !== '') {
            $this->existingSaleIdByKey[$cleanReceipt . '|' . $salesDate] = $saleId;
        }
        if ($groupKey !== '') {
            $this->existingSaleIdByKey[$groupKey] = $saleId;
        }
    }

    protected function resolveExistingSaleId(string $cleanReceipt, string $salesDate, string $groupKey): ?int
    {
        if ($salesDate !== '' && isset($this->existingSaleIdByKey[$cleanReceipt . '|' . $salesDate])) {
            return $this->existingSaleIdByKey[$cleanReceipt . '|' . $salesDate];
        }
        if (isset($this->existingSaleIdByKey[$groupKey])) {
            return $this->existingSaleIdByKey[$groupKey];
        }

        return null;
    }

    /**
     * True when the sale exists and DB has at least as many lines as Excel (nothing to add).
     */
    protected function reimportReceiptIsFullyImported(string $groupKey, $saleRows, array $columnMap, ?int $excelLines = null): bool
    {
        $parsed = $this->parseReceiptGroupKey($groupKey);
        $receiptNo = $parsed['receipt'];
        $salesDate = $parsed['sales_date'];

        if ($salesDate === '' && ! empty($saleRows)) {
            $first = is_array($saleRows[0]) ? $saleRows[0] : (array) $saleRows[0];
            $salesDate = $this->parseDate($this->getRowVal($first, 'salesDate', $columnMap, ''));
        }

        $saleId = $this->resolveExistingSaleId($receiptNo, $salesDate, $parsed['group_key']);
        if ($saleId === null) {
            $existing = $this->findExistingSaleForReceipt($receiptNo, $salesDate, $parsed['group_key']);
            if (! $existing) {
                return false;
            }
            $saleId = (int) $existing->id_penjualan;
        }

        if ($excelLines === null) {
            $excelLines = $this->countValidExcelLines($saleRows, $columnMap);
        }
        if ($excelLines <= 0) {
            return true;
        }

        $dbLines = $this->existingDetailCountCache[$saleId] ?? null;
        if ($dbLines === null) {
            $dbLines = (int) PenjualanDetail::where('id_penjualan', $saleId)->count();
            $this->existingDetailCountCache[$saleId] = $dbLines;
        }

        return $dbLines >= $excelLines;
    }

    /**
     * Count non-empty Excel lines (same rules as sales:audit-import).
     */
    protected function countValidExcelLines($saleRows, array $map): int
    {
        $count = 0;
        foreach ($saleRows as $row) {
            $arr = is_array($row) ? $row : (array) $row;
            $stockOut = (int) $this->getRowVal($arr, 'stockOut', $map, 0);
            $commodity = trim((string) $this->getRowVal($arr, 'commodity', $map, ''));
            $confirmPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'confirmPrice', $map, 0), 0);
            $totalAmount = $this->parseNumericFromExcel($this->getRowVal($arr, 'totalAmount', $map, 0), 0);
            $buyingPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'buyingPrice', $map, 0), 0);

            if ($commodity !== '' || $stockOut > 0 || $confirmPrice > 0 || $totalAmount > 0 || $buyingPrice > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Reimport fast path: skip heavy product/supplier work when DB already has all lines.
     *
     * @return array<string, mixed>|null
     */
    protected function tryReimportFastSkip(string $receiptGroupKey, $saleRows, array $map, ?int $excelLines = null): ?array
    {
        if ($this->importMode !== 'reimport') {
            return null;
        }

        $parsed = $this->parseReceiptGroupKey($receiptGroupKey);
        $receiptNo = $parsed['receipt'];
        $salesDate = $parsed['sales_date'];
        $receiptLabel = $receiptNo . ($salesDate !== '' ? " ({$salesDate})" : '');

        if (! $this->reimportReceiptIsFullyImported($receiptGroupKey, $saleRows, $map, $excelLines)) {
            return null;
        }

        $this->reimportFastSkipped++;

        $existing = $this->findExistingSaleForReceipt($receiptNo, $salesDate, $parsed['group_key']);

        return [
            'success' => true,
            'actually_imported' => false,
            'errors' => [],
            'warnings' => [
                $existing
                    ? "Receipt {$receiptLabel}: Already imported for this date (all lines present), skipped"
                    : "Receipt {$receiptLabel}: No importable lines, skipped",
            ],
        ];
    }

    /**
     * Find existing sale by receipt number AND sales date (never match another year).
     */
    protected function findExistingSaleForReceipt(string $cleanReceipt, string $salesDate, string $groupKey): ?Penjualan
    {
        $cacheKey = $cleanReceipt . '|' . $salesDate . '|' . $groupKey;
        if (array_key_exists($cacheKey, $this->existingSaleCache)) {
            return $this->existingSaleCache[$cacheKey];
        }

        $sale = $this->findExistingSaleForReceiptUncached($cleanReceipt, $salesDate, $groupKey);
        $this->existingSaleCache[$cacheKey] = $sale;

        return $sale;
    }

    protected function findExistingSaleForReceiptUncached(string $cleanReceipt, string $salesDate, string $groupKey): ?Penjualan
    {
        $saleId = $this->resolveExistingSaleId($cleanReceipt, $salesDate, $groupKey);
        if ($saleId !== null) {
            return Penjualan::find($saleId);
        }

        if ($salesDate !== '') {
            $sale = Penjualan::where('receiptno', $cleanReceipt)
                ->whereDate('saledate', $salesDate)
                ->first();
            if ($sale) {
                return $sale;
            }

            $legacy = Penjualan::where('receiptno', 'like', $cleanReceipt . '|%')
                ->whereDate('saledate', $salesDate)
                ->first();
            if ($legacy) {
                return $legacy;
            }
        }

        if ($groupKey !== $cleanReceipt) {
            $legacy = Penjualan::where('receiptno', $groupKey)->first();
            if ($legacy) {
                if ($salesDate === '' || Carbon::parse($legacy->saledate)->toDateString() === $salesDate) {
                    return $legacy;
                }
            }
        }

        return null;
    }

    /**
     * Load existing detail line keys for append mode (one query per receipt instead of per row).
     *
     * @return array<string, true>
     */
    protected function loadExistingDetailLineKeys(int $penjualanId): array
    {
        $keys = [];
        foreach (PenjualanDetail::where('id_penjualan', $penjualanId)
            ->select('id_produk', 'jumlah', 'harga_jual')
            ->cursor() as $detail) {
            $keys[$detail->id_produk . '|' . $detail->jumlah . '|' . round((float) $detail->harga_jual, 2)] = true;
        }

        return $keys;
    }

    /**
     * Recalculate sale header totals from detail lines after appending items.
     */
    protected function refreshPenjualanTotalsFromDetails(Penjualan $penjualan): void
    {
        $agg = PenjualanDetail::where('id_penjualan', $penjualan->id_penjualan)
            ->selectRaw('COALESCE(SUM(jumlah), 0) as total_item, COALESCE(SUM(subtotal), 0) as total_harga')
            ->first();

        $penjualan->total_item = (int) ($agg->total_item ?? 0);
        $penjualan->total_harga = (float) ($agg->total_harga ?? 0);
        $penjualan->bayar = $penjualan->total_harga;
        $penjualan->diterima = $penjualan->total_harga;
        $penjualan->save();
    }

    /**
     * Process a single receipt
     * @param string|null $progressKey If set, progress is updated when receipt has many rows (so UI does not appear stuck)
     * @param int $batchProcessedCount Current receipt index (for progress message)
     * @param int $totalReceipts Total receipts (for progress message)
     */
    protected function processReceipt($receiptGroupKey, $saleRows, array $columnMap = [], $progressKey = null, $batchProcessedCount = 0, $totalReceipts = 0)
    {
        $errors = [];
        $warnings = [];
        $map = !empty($columnMap) ? $columnMap : $this->getDefaultColumnMap();
        $parsed = $this->parseReceiptGroupKey($receiptGroupKey);
        $receiptNo = $parsed['receipt'];
        $groupKey = $parsed['group_key'];
        $groupSalesDate = $parsed['sales_date'];

        if (empty($saleRows) || count($saleRows) == 0) {
            return ['success' => true, 'actually_imported' => false, 'errors' => [], 'warnings' => ["Receipt {$receiptNo}: No rows, skipped"]];
        }

        $rowCount = count($saleRows);
        if ($rowCount > self::MAX_ROWS_PER_RECEIPT) {
            $warnings[] = "Receipt {$receiptNo}: Has {$rowCount} rows; processing first " . self::MAX_ROWS_PER_RECEIPT . " only to avoid timeout.";
            $saleRows = array_slice($saleRows, 0, self::MAX_ROWS_PER_RECEIPT, true);
            $rowCount = count($saleRows);
        }

        // Get first row for sale-level data
        $firstRow = $saleRows[0];
        $firstArr = is_array($firstRow) ? $firstRow : $firstRow->toArray();

        $supplierName = trim((string) $this->getRowVal($firstArr, 'supplier', $map, ''));
        $salesDate = $groupSalesDate !== ''
            ? $groupSalesDate
            : $this->parseDate($this->getRowVal($firstArr, 'salesDate', $map, ''));
        
        // Determine payment method - CRITICAL for consignment
        $paymentMethod = 'CASH';
        foreach ($saleRows as $r) {
            $arr = is_array($r) ? $r : $r->toArray();
            $means = strtoupper(trim((string) $this->getRowVal($arr, 'paymentMethod', $map, '')));
            if ($means === 'CONSIGNMENT') {
                $paymentMethod = 'CONSIGNMENT';
                break;
            } elseif ($means === 'CASH') {
                $paymentMethod = 'CASH';
                // Don't break - keep checking for CONSIGNMENT
            }
        }

        // Find shop name
        $shopName = $this->findShopName($saleRows, $map);

        if (empty($supplierName)) {
            return ['success' => false, 'actually_imported' => false, 'errors' => ["Receipt {$receiptNo}: Missing supplier name"], 'warnings' => []];
        }

        // Skip receipts with no valid data
        if (!$this->hasValidData($saleRows, $map)) {
            return ['success' => true, 'actually_imported' => false, 'errors' => $errors, 'warnings' => array_merge($warnings, ["Receipt {$receiptNo}: No valid data rows, skipped"])];
        }

        $fastSkip = $this->tryReimportFastSkip($receiptGroupKey, $saleRows, $map, null);
        if ($fastSkip !== null) {
            return $fastSkip;
        }

        $existingSale = $this->findExistingSaleForReceipt($receiptNo, $salesDate, $groupKey);
        $appendMode = $existingSale !== null;
        $linesAdded = 0;
        $receiptLabel = $receiptNo . ($salesDate !== '' ? " ({$salesDate})" : '');

        if (empty($salesDate) || $salesDate === Carbon::today()->toDateString()) {
            $warnings[] = "Receipt {$receiptLabel}: Sales date might be invalid, using today's date";
        }

        // Find or create main supplier with correct MOP
        $expectedMop = $paymentMethod === 'CONSIGNMENT' ? 'Consignment' : 'Cash';
        $supplier = $this->findOrCreateSupplier($supplierName, $expectedMop);

        // Find or create shop
        $shop = $this->findOrCreateShop($shopName);

        // Calculate totals
        $totals = $this->calculateTotals($saleRows, $map);
        $totalItem = $totals['totalItem'];
        $totalHarga = $totals['totalHarga'];
        $totalBayar = $totals['totalBayar'];

        $calculatedDiscount = $totalHarga > 0 ? (($totalHarga - $totalBayar) / $totalHarga) * 100 : 0;
        $discountPercent = round($calculatedDiscount, 0);

        if ($appendMode) {
            $penjualan = $existingSale;
            if ($penjualan->receiptno !== $receiptNo) {
                $penjualan->receiptno = $receiptNo;
                $penjualan->save();
            }
        } else {
            $penjualan = $this->createSale($receiptNo, $salesDate, $totalItem, $totalHarga, $totalBayar, $discountPercent, $paymentMethod);
        }

        // Process items and track consignment items BY SUPPLIER
        $itemsToInsert = [];
        $consignmentBySupplier = [];
        $cashItems = [];
        $stockDeltas = [];
        $existingDetailKeys = $appendMode
            ? $this->loadExistingDetailLineKeys($penjualan->id_penjualan)
            : [];

        $totalRows = count($saleRows);
        $lastCommodityOnReceipt = '';
        foreach ($saleRows as $itemIndex => $row) {
            if ($progressKey && $totalReceipts > 0 && $totalRows > 500 && ($itemIndex + 1) % 500 === 0) {
                $pct = 40 + round(($batchProcessedCount / $totalReceipts) * 55, 2);
                Cache::put($progressKey, [
                    'status' => 'processing',
                    'current' => $batchProcessedCount,
                    'total' => $totalReceipts,
                    'percentage' => $pct,
                    'message' => "Processing receipt {$receiptNo} ({$batchProcessedCount}/{$totalReceipts}) – row " . ($itemIndex + 1) . "/{$totalRows}..."
                ], 900);
            }

            $arr = $this->normalizeImportRowArray(is_array($row) ? $row : $row->toArray());

            $commodity = $this->resolveCommodityFromRow($arr, $map, $lastCommodityOnReceipt);
            $itemData = $this->extractItemData($arr, $map, $itemIndex, $receiptNo, $commodity);
            
            if (!$itemData['valid']) {
                if (!empty($itemData['warning'])) {
                    $warnings[] = $itemData['warning'];
                }
                continue;
            }

            if (! empty($itemData['used_fallback_name'])) {
                $warnings[] = "Receipt {$receiptNo}, line " . ($itemIndex + 1)
                    . ': Commodity not read from Excel — check column headers or merged cells.';
            }

            $commodity = $itemData['commodity'];
            if (! preg_match('/^Imported Item \d+$/i', $commodity)) {
                $lastCommodityOnReceipt = $commodity;
            }
            $effectiveQty = $itemData['effectiveQty'];
            $confirmPrice = $itemData['confirmPrice'];
            $totalAmount = $itemData['totalAmount'];
            $buyingPrice = $itemData['buyingPrice'];
            $sellingPrice = $itemData['sellingPrice'];

            // Get the supplier for this specific row (use receipt-level expectedMop)
            $rowSupplierName = trim((string) $this->getRowVal($arr, 'supplier', $map, ''));
            if (empty($rowSupplierName)) {
                $rowSupplierName = $supplierName;
            }
            $rowSupplierName = trim($rowSupplierName);
            $rowSupplier = $this->findOrCreateSupplier($rowSupplierName, $expectedMop);

            // Find or create product
            $produk = $this->findOrCreateProduct(
                $commodity,
                $rowSupplier,
                $shop, 
                $buyingPrice, 
                $sellingPrice, 
                $receiptNo, 
                $itemIndex
            );

            $priceForSubtotal = $confirmPrice > 0 ? $confirmPrice : $totalAmount;
            $subtotal = $priceForSubtotal * $effectiveQty;

            $detailKey = $produk->id_produk . '|' . $effectiveQty . '|' . round($priceForSubtotal, 2);
            if ($appendMode && isset($existingDetailKeys[$detailKey])) {
                continue;
            }

            $linesAdded++;

            // Prepare item for penjualan_detail
            $itemRow = [
                'id_penjualan' => $penjualan->id_penjualan,
                'id_produk' => $produk->id_produk,
                'harga_jual' => $priceForSubtotal,
                'jumlah' => $effectiveQty,
                'diskon' => 0,
                'subtotal' => $subtotal,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
                $itemRow['item_confirmation_status'] = 'pending';
            }
            $itemsToInsert[] = $itemRow;

            $stockDeltas[$produk->id_produk] = ($stockDeltas[$produk->id_produk] ?? 0) + $effectiveQty;

            // For cash purchases, use this row's Means of Payment so CASH rows are never skipped
            $rowMeans = strtoupper(trim((string) $this->getRowVal($arr, 'paymentMethod', $map, '')));
            $isCashRow = ($rowMeans === 'CASH') || ($rowMeans === '' && $paymentMethod === 'CASH');
            if ($isCashRow) {
                $cashItems[] = [
                    'produk' => $produk,
                    'buyingPrice' => $buyingPrice > 0 ? $buyingPrice : ($confirmPrice > 0 ? $confirmPrice : 0),
                    'stockOut' => $effectiveQty,
                    'subtotal' => ($buyingPrice > 0 ? $buyingPrice : ($confirmPrice > 0 ? $confirmPrice : 0)) * $effectiveQty
                ];
            }

            // For consignment: supplier payable = stock_out × buying_price only. Ignore ConfirmPrice (selling price) and discounts.
            $isConsignmentRow = ($rowMeans === 'CONSIGNMENT') || ($rowMeans === '' && $paymentMethod === 'CONSIGNMENT');
            if ($isConsignmentRow) {
                // Use ONLY Excel Buying Price or product's harga_beli. Never use ConfirmPrice, totalAmount, or subtotal for supplier total.
                $effectiveBuyingPrice = $buyingPrice > 0
                    ? round($buyingPrice, 2)
                    : (($produk && $produk->harga_beli > 0) ? round($produk->harga_beli, 2) : 0);

                if ($effectiveBuyingPrice <= 0) {
                    Log::warning("Consignment item: no buying price in Excel or product; supplier amount will use product harga_beli or zero", [
                        'receipt' => $receiptNo,
                        'product' => $produk->nama_produk ?? $commodity,
                        'buyingPrice' => $buyingPrice,
                        'quantity' => $effectiveQty
                    ]);
                    // Only fallback: product's cost. Do NOT use confirmPrice, totalAmount, or subtotal (those are customer/sale values).
                    if ($produk && $produk->harga_beli > 0) {
                        $effectiveBuyingPrice = round($produk->harga_beli, 2);
                    }
                }
                
                // Correct known Excel issue: some cells (e.g. formula =1500+Discount) yield 1503 when sheet shows 1500
                if ($effectiveBuyingPrice == 1503 && $effectiveQty == 1
                    && (stripos($commodity, 'AH62') !== false || stripos($commodity, 'PALAZZO') !== false)) {
                    $effectiveBuyingPrice = 1500.0;
                }
                
                // Correct erroneous buying price 3 (likely Discount column read as Buying price) – use product cost or known default, not sale total
                $isPalazzoOrAh62 = (stripos($commodity, 'AH62') !== false || stripos($commodity, 'PALAZZO') !== false);
                if ($effectiveBuyingPrice == 3 && $effectiveQty == 1 && $isPalazzoOrAh62) {
                    $effectiveBuyingPrice = ($produk && $produk->harga_beli > 0) ? round($produk->harga_beli, 2) : 1500.0;
                }
                
                // Use this ROW's sales date so consignment list totals match Excel when filtering by month (e.g. February)
                $rowDateRaw = $this->getRowVal($arr, 'salesDate', $map, '');
                $rowSalesDate = !empty(trim((string) $rowDateRaw)) ? $this->parseDate($rowDateRaw) : $salesDate;
                $groupKey = $rowSupplier->id_supplier . '_' . $rowSalesDate;

                // Supplier payable = quantity × buying_price (NOT customer sale total/discounts). Consignment totals must not depend on selling price or discounts.
                if ($effectiveBuyingPrice > 0 && $effectiveQty > 0) {
                    $itemAmount = round($effectiveQty * $effectiveBuyingPrice, 2);
                } else {
                    // Fallback only when buying price unknown: use product cost so we still record the line
                    $unitCost = ($produk && $produk->harga_beli > 0) ? $produk->harga_beli : 0;
                    $itemAmount = ($unitCost > 0 && $effectiveQty > 0) ? round($effectiveQty * $unitCost, 2) : 0;
                }

                if (!isset($consignmentBySupplier[$groupKey])) {
                    $consignmentBySupplier[$groupKey] = [
                        'supplier' => $rowSupplier,
                        'date' => $salesDate,
                        'items' => [],
                        'total_amount' => 0,
                        'total_quantity' => 0
                    ];
                }
                
                $consignmentBySupplier[$groupKey]['items'][] = [
                    'produk' => $produk,
                    'buyingPrice' => round($effectiveBuyingPrice, 2),
                    'stockOut' => $effectiveQty,
                    'amount' => $itemAmount
                ];

                $consignmentBySupplier[$groupKey]['total_amount'] += $itemAmount;
                $consignmentBySupplier[$groupKey]['total_quantity'] += $effectiveQty;
            }
        }

        // Fallback if no detail rows
        if (empty($itemsToInsert) && $totalItem > 0 && $totalHarga > 0 && ! $appendMode) {
            $result = $this->createFallbackItem($penjualan, $receiptNo, $supplier, $shop, $totalItem, $totalHarga, $paymentMethod);
            $itemsToInsert = array_merge($itemsToInsert, $result['items']);
            
            if ($paymentMethod === 'CASH') {
                $cashItems = array_merge($cashItems, $result['cashItems']);
            }
            if ($paymentMethod === 'CONSIGNMENT') {
                foreach ($result['consignmentItems'] as $supplierKey => $data) {
                    $dataWithDate = array_merge(['date' => $salesDate], $data);
                    $fallbackKey = (isset($data['supplier']) ? $data['supplier']->id_supplier : $supplierKey) . '_' . $salesDate;
                    if (!isset($consignmentBySupplier[$fallbackKey])) {
                        $consignmentBySupplier[$fallbackKey] = $dataWithDate;
                    } else {
                        $consignmentBySupplier[$fallbackKey]['items'] = array_merge($consignmentBySupplier[$fallbackKey]['items'], $data['items']);
                        $consignmentBySupplier[$fallbackKey]['total_amount'] += $data['total_amount'];
                        $consignmentBySupplier[$fallbackKey]['total_quantity'] += $data['total_quantity'];
                    }
                }
            }
        }

        if ($linesAdded === 0 && empty($itemsToInsert)) {
            return [
                'success' => true,
                'actually_imported' => false,
                'errors' => $errors,
                'warnings' => array_merge($warnings, ["Receipt {$receiptLabel}: Already imported for this date (all lines present), skipped"]),
            ];
        }

        $this->applyStockDeltas($stockDeltas);

        // Insert sale items
        if (!empty($itemsToInsert)) {
            PenjualanDetail::insert($itemsToInsert);
        }

        if ($appendMode && ($linesAdded > 0 || ! empty($itemsToInsert))) {
            $this->refreshPenjualanTotalsFromDetails($penjualan);
        }

        // Create consignment invoices (deferred until sale-list confirmation from 01/06/2026)
        $deferLedger = app(EnsureSaleSupplierLedgerService::class)->saleDefersLedgerUntilConfirmed($penjualan);
        if ($paymentMethod === 'CONSIGNMENT' && !empty($consignmentBySupplier) && ! $deferLedger) {
            foreach ($consignmentBySupplier as $supplierKey => $supplierData) {
                try {
                    $this->createConsignmentInvoiceConsolidated(
                        $supplierData['supplier'],
                        $supplierData['items'],
                        $penjualan,
                        $supplierData['date'] ?? $salesDate,
                        $supplierData['total_amount']
                    );
                } catch (\Exception $e) {
                    Log::error("Failed to create consignment invoice", [
                        'receipt' => $receiptNo,
                        'supplier' => $supplierData['supplier']->nama,
                        'amount' => $supplierData['total_amount'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $errors[] = "Receipt {$receiptNo}: Failed to create consignment invoice for {$supplierData['supplier']->nama}: " . $e->getMessage();
                    // Do NOT rethrow: sale is already created; continue so other receipts still import
                }
            }
        }

        // Create cash purchase whenever we have any CASH rows (deferred until sale-list confirmation from 01/06/2026)
        if (!empty($cashItems) && ! $deferLedger) {
            $this->createCashPurchase(
                $supplier,
                $cashItems,
                $salesDate,
                $penjualan,
                count($cashItems),
                array_sum(array_column($cashItems, 'subtotal'))
            );
        }

        return ['success' => true, 'actually_imported' => true, 'errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Create consignment invoice with ONE consolidated invoice item per receipt/supplier (original behaviour).
     * Amount and totals match receipt; product from first item for display.
     */
    protected function createConsignmentInvoiceConsolidated($supplier, $items, $penjualan, $salesDate, $totalAmount)
    {
        $dateOnly = Carbon::parse($salesDate)->toDateString();
        
        // Ensure supplier has correct MOP
        if ($supplier->mop !== 'Consignment') {
            $supplier->mop = 'Consignment';
            $supplier->save();
            DB::table('supplier')->where('id_supplier', $supplier->id_supplier)->update(['mop' => 'Consignment']);
        }

        // Find or create invoice for this supplier/date
        $invoice = Invoice::where('id_supplier', $supplier->id_supplier)
            ->whereDate('created_at', $dateOnly)
            ->first();

        if (!$invoice) {
            $invoice = Invoice::create([
                'id_supplier' => $supplier->id_supplier,
                'total' => 0,
            ]);
            DB::table('invoices')->where('id', $invoice->id)->update([
                'created_at' => $dateOnly . ' 00:00:00',
                'updated_at' => $dateOnly . ' 00:00:00',
            ]);
            $invoice->refresh();
            
            Log::info('Consignment import: created new invoice for supplier/date', [
                'invoice_id' => $invoice->id,
                'supplier' => $supplier->nama,
                'date' => $dateOnly,
            ]);
        }

        // One consolidated invoice item per receipt/supplier: amount = SUM(stock_out × buying_price) (supplier payable; no ConfirmPrice/discounts).
        $firstItem = $items[0] ?? null;
        $produk = $firstItem && isset($firstItem['produk']) ? $firstItem['produk'] : null;
        $totalQty = array_sum(array_column($items, 'stockOut'));
        if ($totalQty <= 0) {
            $totalQty = 1;
        }
        $amount = round((float) $totalAmount, 2);
        if ($amount <= 0 || !$produk) {
            return;
        }

        $ledger = app(EnsureSaleSupplierLedgerService::class);
        $remaining = $ledger->remainingConsignmentQtyToPost(
            (int) $penjualan->id_penjualan,
            (int) $produk->id_produk,
            (int) $supplier->id_supplier
        );
        if ($remaining <= 0) {
            Log::info('Consignment import: skip duplicate ledger post', [
                'receiptno' => $penjualan->receiptno,
                'produk_id' => $produk->id_produk,
                'supplier' => $supplier->nama,
            ]);

            return;
        }

        $item = $ledger->createConsignmentInvoiceItemIfNeeded(
            $penjualan,
            $produk,
            $supplier,
            min($totalQty, $remaining),
            0
        );
        if (! $item) {
            return;
        }
    }

    /**
     * SIMPLIFIED: Create cash purchase that WILL appear on cash generated page
     */
    protected function createCashPurchase($supplier, $items, $salesDate, $penjualan, $totalItem, $totalHarga)
    {
        // STEP 1: Create/Get Cash supplier with MOP = 'Cash'
        $cashSupplierName = trim($supplier->nama) . ' (Cash)';
        
        // Check if supplier exists
        $cashSupplier = Supplier::where('nama', $cashSupplierName)->first();
        
        if (!$cashSupplier) {
            // Create new cash supplier
            $cashSupplier = Supplier::create([
                'nama' => $cashSupplierName,
                'alamat' => $supplier->alamat ?? 'Imported from old system',
                'telepon' => $supplier->telepon ?? '0000000000',
                'mop' => 'Cash',
            ]);
            Log::info("Created new cash supplier: {$cashSupplierName}");
        } else {
            // Update existing supplier to ensure MOP is Cash
            if ($cashSupplier->mop !== 'Cash') {
                $cashSupplier->mop = 'Cash';
                $cashSupplier->save();
                Log::info("Updated cash supplier MOP to Cash: {$cashSupplierName}");
            }
        }

        // Double-check MOP in database (direct update to be sure)
        DB::table('supplier')
            ->where('id_supplier', $cashSupplier->id_supplier)
            ->update(['mop' => 'Cash']);

        Log::info("Using cash supplier", [
            'supplier_id' => $cashSupplier->id_supplier,
            'supplier_name' => $cashSupplier->nama,
            'mop' => $cashSupplier->mop
        ]);

        // STEP 2: Create ONE pembelian record for this supplier/date
        // First check if exists
        $pembelian = Pembelian::where('id_supplier', $cashSupplier->id_supplier)
            ->whereDate('purchasedate2', $salesDate)
            ->first();

        if (!$pembelian) {
            // Create new pembelian with ALL fields that might be needed
            $pembelianData = [
                'id_supplier' => $cashSupplier->id_supplier,
                'total_item' => 0,
                'total_harga' => 0,
                'bayar' => 0,
                'purchasedate2' => $salesDate,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Add optional fields if they exist
            if (Schema::hasColumn('pembelian', 'diterima')) {
                $pembelianData['diterima'] = 0;
            }
            
            // CRITICAL: These fields are likely what the cash generated page looks for
            if (Schema::hasColumn('pembelian', 'status')) {
                $pembelianData['status'] = 'completed';
            }
            
            if (Schema::hasColumn('pembelian', 'payment_method')) {
                $pembelianData['payment_method'] = 'Cash';
            }
            
            if (Schema::hasColumn('pembelian', 'type')) {
                $pembelianData['type'] = 'cash_sale';
            }
            
            if (Schema::hasColumn('pembelian', 'source')) {
                $pembelianData['source'] = 'import';
            }
            
            // Link to the original sale
            if (Schema::hasColumn('pembelian', 'penjualan_id')) {
                $pembelianData['penjualan_id'] = $penjualan->id_penjualan;
            }

            $pembelian = Pembelian::create($pembelianData);
            
            Log::info("CREATED NEW PEMBELIAN FOR CASH SALE", [
                'pembelian_id' => $pembelian->id_pembelian,
                'supplier_id' => $cashSupplier->id_supplier,
                'date' => $salesDate,
                'status' => $pembelian->status ?? 'not set',
                'payment_method' => $pembelian->payment_method ?? 'not set'
            ]);
        } else {
            Log::info("Using existing pembelian", [
                'pembelian_id' => $pembelian->id_pembelian
            ]);
            
            // Update existing pembelian with any missing fields
            $needsUpdate = false;
            
            if (Schema::hasColumn('pembelian', 'status') && empty($pembelian->status)) {
                $pembelian->status = 'completed';
                $needsUpdate = true;
            }
            
            if (Schema::hasColumn('pembelian', 'payment_method') && empty($pembelian->payment_method)) {
                $pembelian->payment_method = 'Cash';
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $pembelian->save();
            }
        }

        // STEP 3: Add items to pembelian_detail
        foreach ($items as $item) {
            $produk = $item['produk'];
            $buyingPrice = $item['buyingPrice'];
            $stockOut = $item['stockOut'];
            $subtotal = $item['subtotal'];

            // Create pembelian_detail record
            PembelianDetail::create([
                'id_pembelian' => $pembelian->id_pembelian,
                'id_produk' => $produk->id_produk,
                'harga_beli' => (int) round($buyingPrice),
                'jumlah' => $stockOut,
                'subtotal' => (int) round($subtotal),
            ]);
            
            Log::info("Added item to pembelian_detail", [
                'product' => $produk->nama_produk,
                'quantity' => $stockOut
            ]);

            // Cancel any automatic stock increase
            DB::table('produk')
                ->where('id_produk', $produk->id_produk)
                ->decrement('stok', $stockOut);
        }

        // STEP 4: Update pembelian totals
        $pembelian->total_item += $totalItem;
        $pembelian->total_harga += $totalHarga;
        $pembelian->save();

        Log::info("CASH PURCHASE COMPLETE - SHOULD APPEAR ON CASH GENERATED PAGE", [
            'pembelian_id' => $pembelian->id_pembelian,
            'supplier' => $cashSupplier->nama,
            'supplier_mop' => $cashSupplier->mop,
            'total_items' => $totalItem,
            'total_amount' => $totalHarga,
            'pembelian_status' => $pembelian->status ?? 'not set',
            'pembelian_payment_method' => $pembelian->payment_method ?? 'not set',
            'date' => $salesDate
        ]);

        return $pembelian;
    }

    /**
     * Helper method to determine which supplier should be used for an item
     */
    protected function determineItemSupplier($produk, $mainSupplier, $paymentMethod)
    {
        // For consignment, prefer product's supplier if it exists and is consignment
        if ($produk->id_supplier) {
            $productSupplier = Supplier::find($produk->id_supplier);
            if ($productSupplier && strtoupper(trim($productSupplier->mop ?? '')) === 'CONSIGNMENT') {
                return $productSupplier;
            }
        }
        
        // Otherwise use main supplier if it's consignment
        if ($paymentMethod === 'CONSIGNMENT' && strtoupper(trim($mainSupplier->mop ?? '')) === 'CONSIGNMENT') {
            // Update product to have correct supplier
            if ($produk->id_supplier !== $mainSupplier->id_supplier) {
                $produk->id_supplier = $mainSupplier->id_supplier;
                $produk->save();
                
                Log::info('Consignment import: Updated product supplier', [
                    'product_id' => $produk->id_produk,
                    'product_name' => $produk->nama_produk,
                    'new_supplier_id' => $mainSupplier->id_supplier,
                    'receipt' => $produk->receiptno ?? 'unknown'
                ]);
            }
            return $mainSupplier;
        }
        
        return null;
    }

    /**
     * Resolve product/commodity name from the mapped column, scan row, or previous line (merged cells).
     */
    protected function resolveCommodityFromRow(array $arr, array $map, string $carryForward = ''): string
    {
        $arr = $this->normalizeImportRowArray($arr);
        $fromColumn = trim((string) $this->getRowVal($arr, 'commodity', $map, ''));

        if ($fromColumn !== '' && $this->looksLikeCommodityName($fromColumn)) {
            return $fromColumn;
        }

        $inferred = $this->inferCommodityFromRowCells($arr, $map);
        if ($inferred !== '') {
            return $inferred;
        }

        if ($carryForward !== '' && $this->looksLikeCommodityName($carryForward)) {
            return $carryForward;
        }

        if ($fromColumn !== '' && ! $this->looksLikeLineNumberOnly($fromColumn)) {
            return $fromColumn;
        }

        return '';
    }

    protected function looksLikeLineNumberOnly(string $value): bool
    {
        return preg_match('/^\d+$/', trim($value)) === 1;
    }

    protected function looksLikeReceiptStrict(string $value): bool
    {
        $value = preg_replace('/\s+/', '', trim($value));
        if ($value === '') {
            return false;
        }
        if (strpos($value, ' ') !== false) {
            return false;
        }
        if (preg_match('/^[A-Za-z]{0,10}\d{2,14}[A-Za-z0-9-]*$/', $value)) {
            return true;
        }
        if (preg_match('/^\d{3,10}$/', $value)) {
            $n = (float) $value;

            return $n > 0 && $n < 100000000;
        }

        return false;
    }

    protected function looksLikeCommodityName(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || $this->looksLikeLineNumberOnly($value)) {
            return false;
        }

        if (preg_match('/^Imported Item \d+$/i', $value)) {
            return false;
        }

        // Typical export: "CD445 COFFEE HOUSE DRIP BAG"
        if (preg_match('/^[A-Za-z0-9]{2,15}[\s\-].{2,}/', $value)) {
            return true;
        }

        return (bool) preg_match('/[A-Za-z]/', $value);
    }

    /**
     * When the commodity column is wrong or empty, pick the best text cell in the row.
     */
    protected function inferCommodityFromRowCells(array $arr, array $map): string
    {
        $arr = $this->normalizeImportRowArray($arr);
        $commodityCol = isset($map['commodity']) ? (int) $map['commodity'] : -1;
        $supplierCol = isset($map['supplier']) ? (int) $map['supplier'] : -1;
        $receiptCol = isset($map['receipt']) ? (int) $map['receipt'] : -1;
        $shopCol = isset($map['shop']) ? (int) $map['shop'] : -1;
        $stockCol = isset($map['stockOut']) ? (int) $map['stockOut'] : -1;

        $best = '';
        $bestScore = -1;

        foreach ($arr as $col => $cell) {
            if (! is_numeric($col)) {
                continue;
            }

            $colIndex = (int) $col;
            $val = $this->importCellToString($cell);

            if ($val === '' || ! $this->looksLikeCommodityName($val)) {
                continue;
            }

            if ($colIndex === $stockCol && $this->looksLikeLineNumberOnly($val)) {
                continue;
            }

            if ($this->looksLikeReceiptStrict($val) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                continue;
            }

            $score = strlen($val);
            if (preg_match('/^[A-Za-z0-9]{2,15}[\s\-].{2,}/', $val)) {
                $score += 200;
            }
            if ($colIndex === $commodityCol) {
                $score += 500;
            }
            if ($colIndex === $supplierCol || $colIndex === $receiptCol) {
                $score -= 120;
            }
            if ($colIndex === $shopCol) {
                $score -= 60;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $val;
            }
        }

        return $best;
    }

    /**
     * Extract item data from row
     */
    protected function extractItemData($arr, $map, $itemIndex, $receiptNo, string $resolvedCommodity = '')
    {
        $commodity = $resolvedCommodity !== ''
            ? trim($resolvedCommodity)
            : trim((string) $this->getRowVal($arr, 'commodity', $map, ''));
        $stockOut = intval($this->getRowVal($arr, 'stockOut', $map, 0));
        $confirmPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'confirmPrice', $map, 0), 0);
        $totalAmount = $this->parseNumericFromExcel($this->getRowVal($arr, 'totalAmount', $map, 0), 0);
        $buyingPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'buyingPrice', $map, 0), 0);
        $sellingPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'sellingPrice', $map, 0), 0);

        // Infer quantity when zero but amount/price present
        $effectiveQty = $stockOut > 0 ? $stockOut : 0;
        if ($effectiveQty === 0 && ($confirmPrice > 0 || $totalAmount > 0 || $buyingPrice > 0)) {
            $effectiveQty = 1;
        }
        
        if ($effectiveQty === 0) {
            return [
                'valid' => false,
                'warning' => "Receipt {$receiptNo}, Item " . ($itemIndex + 1) . ": No quantity or amount, skipping"
            ];
        }

        if ($commodity === '' || $this->looksLikeLineNumberOnly($commodity)) {
            $commodity = $this->resolveCommodityFromRow($arr, $map, '');
        }

        $usedFallbackName = false;
        if ($commodity === '' || ! $this->looksLikeCommodityName($commodity)) {
            $commodity = 'Imported Item ' . ($itemIndex + 1);
            $usedFallbackName = true;
        }

        return [
            'valid' => true,
            'commodity' => $commodity,
            'used_fallback_name' => $usedFallbackName,
            'effectiveQty' => $effectiveQty,
            'confirmPrice' => $confirmPrice,
            'totalAmount' => $totalAmount,
            'buyingPrice' => $buyingPrice,
            'sellingPrice' => $sellingPrice
        ];
    }

    /**
     * Calculate totals from rows
     */
    protected function calculateTotals($saleRows, $map)
    {
        $totalItem = 0;
        $totalHarga = 0;
        $totalBayar = 0;

        foreach ($saleRows as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $stockOut = intval($this->getRowVal($arr, 'stockOut', $map, 0));
            $confirmPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'confirmPrice', $map, 0), 0);
            $totalAmount = $this->parseNumericFromExcel($this->getRowVal($arr, 'totalAmount', $map, 0), 0);
            $buyingPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'buyingPrice', $map, 0), 0);
            $commodity = trim((string) $this->getRowVal($arr, 'commodity', $map, ''));

            $effectiveQty = $stockOut > 0 ? $stockOut : 0;
            if ($effectiveQty === 0 && ($confirmPrice > 0 || $totalAmount > 0 || $buyingPrice > 0 || $commodity !== '')) {
                $effectiveQty = 1;
            }
            
            $totalItem += $effectiveQty;
            $totalHarga += ($confirmPrice > 0 ? $confirmPrice : $totalAmount) * $effectiveQty;
            $totalBayar += $totalAmount;
        }

        return [
            'totalItem' => $totalItem,
            'totalHarga' => $totalHarga,
            'totalBayar' => $totalBayar
        ];
    }

    /**
     * Check if receipt has any valid data
     */
    protected function hasValidData($saleRows, $map)
    {
        foreach ($saleRows as $r) {
            $arr = is_array($r) ? $r : $r->toArray();
            $stockOut = intval($this->getRowVal($arr, 'stockOut', $map, 0));
            $confirmPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'confirmPrice', $map, 0), 0);
            $totalAmount = $this->parseNumericFromExcel($this->getRowVal($arr, 'totalAmount', $map, 0), 0);
            $buyingPrice = $this->parseNumericFromExcel($this->getRowVal($arr, 'buyingPrice', $map, 0), 0);
            $commodity = trim((string) $this->getRowVal($arr, 'commodity', $map, ''));
            
            if ($commodity !== '' || $stockOut > 0 || $confirmPrice > 0 || $totalAmount > 0 || $buyingPrice > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find shop name from rows
     */
    protected function findShopName($saleRows, $map)
    {
        foreach ($saleRows as $r) {
            $arr = is_array($r) ? $r : $r->toArray();
            $sn = trim((string) $this->getRowVal($arr, 'shop', $map, ''));
            if ($sn !== '') {
                return $sn;
            }
        }
        
        foreach ($saleRows as $r) {
            $arr = is_array($r) ? $r : $r->toArray();
            $sn = trim((string) $this->getRowVal($arr, 'commodity', $map, ''));
            if ($sn !== '' && !is_numeric($sn)) {
                return $sn;
            }
        }
        
        return '';
    }

    /**
     * Find or create supplier with correct MOP
     */
    protected function findOrCreateSupplier($supplierName, $expectedMop)
    {
        if (isset($this->supplierCache[$supplierName])) {
            return $this->supplierCache[$supplierName];
        }

        $supplier = Supplier::where('nama', $supplierName)->first();

        if (! $supplier) {
            $supplier = Supplier::create([
                'nama' => $supplierName,
                'alamat' => 'Imported from old system',
                'telepon' => '0000000000',
                'mop' => $expectedMop,
            ]);
        }

        $this->supplierCache[$supplierName] = $supplier;

        return $supplier;
    }

    /**
     * Find or create shop
     */
    protected function findOrCreateShop($shopName)
    {
        if (empty($shopName)) {
            return null;
        }

        if (isset($this->shopCache[$shopName])) {
            return $this->shopCache[$shopName];
        }

        $shop = Shop::where('shop_name', $shopName)->first();
        if (! $shop) {
            $shop = Shop::where('shop_code', $shopName)->first();
        }
        if (! $shop) {
            $shop = Shop::create([
                'shop_name' => $shopName,
                'shop_code' => strtoupper(substr($shopName, 0, 3)) . rand(100, 999),
            ]);
        }

        $this->shopCache[$shopName] = $shop;

        return $shop;
    }

    /**
     * Create sale record
     */
    protected function createSale($receiptNo, $salesDate, $totalItem, $totalHarga, $totalBayar, $discountPercent, $paymentMethod)
    {
        $penjualan = Penjualan::create([
            'id_member' => null,
            'total_item' => $totalItem,
            'total_harga' => $totalHarga,
            'diskon' => $discountPercent,
            'bayar' => $totalBayar,
            'diterima' => $totalBayar,
            'id_user' => $this->userId,
            'saledate' => $salesDate,
            'receiptno' => $receiptNo,
            'created_at' => $salesDate . ' ' . date('H:i:s'),
            'sale_type' => 'normal',
        ]);

        if (Schema::hasColumn('penjualan', 'status')) {
            $penjualan->status = 'completed';
            $penjualan->save();
        }

        if (Schema::hasColumn('penjualan', 'payment_method')) {
            $penjualan->payment_method = $paymentMethod === 'CONSIGNMENT' ? 'Consignment' : 'Cash';
            $penjualan->save();
        }

        return $penjualan;
    }

    /**
     * Product code for import rows (unique per shop; avoids IMPORTEDIT collisions on "Imported Item N").
     */
    protected function deriveImportProductCode(string $normalizedCommodity, string $receiptNo, int $itemIndex): string
    {
        if (preg_match('/^Imported Item \d+$/i', $normalizedCommodity)) {
            $suffix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $receiptNo), 0, 12));

            return 'IMP-' . ($suffix !== '' ? $suffix : 'RCP') . '-' . ($itemIndex + 1);
        }

        $base = strtoupper(substr(preg_replace('/\s+/', '', $normalizedCommodity), 0, 10));

        if ($base === '') {
            $suffix = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $receiptNo), 0, 8));

            return 'IMP-' . ($suffix !== '' ? $suffix : 'ITEM') . '-' . ($itemIndex + 1);
        }

        return $base;
    }

    /**
     * Find existing product in shop by item/kode code (case-insensitive).
     */
    protected function findProdukByShopAndCode(int $shopId, string $productCode): ?Produk
    {
        $code = trim($productCode);
        if ($shopId <= 0 || $code === '') {
            return null;
        }

        $norm = strtoupper($code);

        return Produk::query()
            ->where('shop_id', $shopId)
            ->where(function ($q) use ($norm, $code) {
                $q->whereRaw('UPPER(TRIM(COALESCE(kode_produk, ""))) = ?', [$norm])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(item_code, ""))) = ?', [$norm])
                    ->orWhere('kode_produk', $code)
                    ->orWhere('item_code', $code);
            })
            ->first();
    }

    /**
     * Find or create product (scoped by shop + code to satisfy produk_shop_kode_produk_unique).
     */
    protected function findOrCreateProduct($commodity, $supplier, $shop, $buyingPrice, $sellingPrice, $receiptNo, $itemIndex)
    {
        $normalizedCommodity = $this->normalizeProductName($commodity);
        $shopId = $shop ? (int) $shop->id : 0;
        $cacheKey = $shopId . '|' . mb_strtolower($normalizedCommodity) . '|' . ($itemIndex + 1);

        if (isset($this->productCache[$cacheKey])) {
            return $this->productCache[$cacheKey];
        }

        $productCode = $this->deriveImportProductCode($normalizedCommodity, $receiptNo, $itemIndex);

        $produk = null;
        if ($shopId > 0) {
            $produk = $this->findProdukByShopAndCode($shopId, $productCode);
        }

        if (! $produk) {
            $nameQuery = Produk::query()->where('nama_produk', $normalizedCommodity);
            if ($shopId > 0) {
                $nameQuery->where('shop_id', $shopId);
            }
            $produk = $nameQuery->first();
        }

        if (! $produk && $commodity !== $normalizedCommodity) {
            $altQuery = Produk::query()->where('nama_produk', $commodity);
            if ($shopId > 0) {
                $altQuery->where('shop_id', $shopId);
            }
            $produk = $altQuery->first();
        }

        if (! $produk) {
            $legacyQuery = Produk::query()->where('nama_produk', $normalizedCommodity);
            if ($shopId > 0) {
                $legacyQuery->where(function ($q) use ($shopId) {
                    $q->where('shop_id', $shopId)->orWhereNull('shop_id');
                });
            }
            $produk = $legacyQuery->orderByRaw('shop_id IS NULL')->first();
        }

        if (! $produk) {
            try {
                $produk = Produk::create([
                    'kode_produk' => $productCode,
                    'nama_produk' => $normalizedCommodity,
                    'id_kategori' => 1,
                    'id_supplier' => $supplier->id_supplier,
                    'harga_beli' => $buyingPrice > 0 ? $buyingPrice : 0,
                    'harga_jual' => $sellingPrice > 0 ? $sellingPrice : 0,
                    'stok' => 0,
                    'shop_id' => $shopId > 0 ? $shopId : null,
                    'is_incomplete' => false,
                    'mop' => $supplier->mop ?? 'Consignment',
                    'item_code' => $productCode,
                    'date_in' => now()->toDateString(),
                ]);
            } catch (QueryException $e) {
                if ($shopId > 0 && $this->isDuplicateShopProductCodeException($e)) {
                    $produk = $this->findProdukByShopAndCode($shopId, $productCode);
                }
                if (! $produk) {
                    throw $e;
                }
            }
        } else {
            $needsUpdate = false;
            if ($produk->id_supplier !== $supplier->id_supplier) {
                $produk->id_supplier = $supplier->id_supplier;
                $needsUpdate = true;
            }
            if ($shopId > 0 && (empty($produk->shop_id) || (int) $produk->shop_id !== $shopId)) {
                $produk->shop_id = $shopId;
                $needsUpdate = true;
            }
            if ($buyingPrice > 0 && abs((float) $produk->harga_beli - $buyingPrice) > 0.01) {
                $produk->harga_beli = $buyingPrice;
                $needsUpdate = true;
            }
            if ($produk->is_incomplete) {
                $produk->is_incomplete = false;
                $needsUpdate = true;
            }
            if ($needsUpdate) {
                try {
                    $produk->save();
                } catch (QueryException $e) {
                    if ($shopId > 0 && $this->isDuplicateShopProductCodeException($e)) {
                        $existing = $this->findProdukByShopAndCode($shopId, (string) ($produk->kode_produk ?? $productCode));
                        if ($existing) {
                            $produk = $existing;
                        } else {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }
            }
        }

        $this->productCache[$cacheKey] = $produk;

        return $produk;
    }

    protected function isDuplicateShopProductCodeException(QueryException $e): bool
    {
        $msg = $e->getMessage();

        return str_contains($msg, '1062')
            || str_contains($msg, 'produk_shop_kode_produk_unique')
            || str_contains($msg, 'Duplicate entry');
    }

    /**
     * Apply aggregated stock deductions (one UPDATE per product per receipt).
     *
     * @param array<int, int> $deltas
     */
    protected function applyStockDeltas(array $deltas): void
    {
        foreach ($deltas as $produkId => $quantity) {
            if ($quantity > 0) {
                $this->decrementStockWithRetry((int) $produkId, (int) $quantity);
            }
        }
    }

    /**
     * Create fallback item when no detail rows
     */
    protected function createFallbackItem($penjualan, $receiptNo, $supplier, $shop, $totalItem, $totalHarga, $paymentMethod)
    {
        $pricePerUnit = $totalItem > 0 ? round($totalHarga / $totalItem, 2) : 0;
        $aggregateName = 'Imported Sale ' . $receiptNo;
        
        $shopId = $shop ? (int) $shop->id : 0;
        $fallbackCode = 'IMP-' . strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $receiptNo), 0, 12)) . '-AGG';

        $produk = null;
        if ($shopId > 0) {
            $produk = $this->findProdukByShopAndCode($shopId, $fallbackCode);
        }
        if (! $produk) {
            $produk = Produk::query()
                ->where('nama_produk', $aggregateName)
                ->when($shopId > 0, fn ($q) => $q->where('shop_id', $shopId))
                ->first();
        }

        if (! $produk) {
            try {
                $produk = Produk::create([
                    'kode_produk' => $fallbackCode,
                    'nama_produk' => $aggregateName,
                    'id_kategori' => 1,
                    'id_supplier' => $supplier->id_supplier,
                    'harga_beli' => $pricePerUnit,
                    'harga_jual' => $pricePerUnit,
                    'stok' => 0,
                    'shop_id' => $shopId > 0 ? $shopId : null,
                    'is_incomplete' => false,
                    'item_code' => $fallbackCode,
                    'mop' => $supplier->mop ?? 'Cash',
                    'date_in' => now()->toDateString(),
                ]);
            } catch (QueryException $e) {
                if ($shopId > 0 && $this->isDuplicateShopProductCodeException($e)) {
                    $produk = $this->findProdukByShopAndCode($shopId, $fallbackCode);
                }
                if (! $produk) {
                    throw $e;
                }
            }
        }
        
        // Deduct stock
        DB::table('produk')
            ->where('id_produk', $produk->id_produk)
            ->decrement('stok', (int) $totalItem);
        
        $itemRow = [
            'id_penjualan' => $penjualan->id_penjualan,
            'id_produk' => $produk->id_produk,
            'harga_jual' => $pricePerUnit,
            'jumlah' => (int) $totalItem,
            'diskon' => 0,
            'subtotal' => $totalHarga,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        if (Schema::hasColumn('penjualan_detail', 'item_confirmation_status')) {
            $itemRow['item_confirmation_status'] = 'pending';
        }
        
        $result = [
            'items' => [$itemRow],
            'cashItems' => [],
            'consignmentItems' => []
        ];
        
        if ($paymentMethod === 'CASH') {
            $result['cashItems'] = [[
                'produk' => $produk,
                'buyingPrice' => $pricePerUnit,
                'stockOut' => (int) $totalItem,
                'subtotal' => $totalHarga
            ]];
        }
        
        if ($paymentMethod === 'CONSIGNMENT') {
            // Fallback (no detail rows): use stock_out × unit cost; unit cost = totalHarga/totalItem (product harga_beli).
            $supplierPayable = (int) $totalItem * $pricePerUnit;
            $result['consignmentItems'][$supplier->id_supplier] = [
                'supplier' => $supplier,
                'items' => [[
                    'produk' => $produk,
                    'buyingPrice' => $pricePerUnit,
                    'stockOut' => (int) $totalItem,
                    'amount' => $supplierPayable
                ]],
                'total_amount' => $supplierPayable,
                'total_quantity' => (int) $totalItem
            ];
        }
        
        return $result;
    }

    /**
     * Parse date from various formats (Excel numeric, d/m/Y, m/d/Y, Y-m-d, etc.)
     */
    protected function parseDate($dateString)
    {
        if (empty($dateString) && $dateString !== 0 && $dateString !== '0') {
            return Carbon::today()->toDateString();
        }

        // Try to parse as Excel date (numeric serial)
        if (is_numeric($dateString)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString);
                return $date->format('Y-m-d');
            } catch (\Throwable $e) {
                // Not an Excel date, fall through
            }
        }

        $dateString = trim((string) $dateString);
        // Y-m-d H:i:s or Y-m-d (e.g. 2026-01-01 00:00:00)
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}):(\d{1,2}))?/', $dateString, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                try {
                    return Carbon::createFromDate($year, $month, $day)->format('Y-m-d');
                } catch (\Throwable $e) {
                    // fall through
                }
            }
        }
        // Slash-separated: try m/d/y first (e.g. 2/28/2026 = Feb 28), then d/m/y (e.g. 27/02/2026 = Feb 27)
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateString, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $year = (int) $m[3];
            if ($year >= 1900 && $year <= 2100) {
                if ($a >= 1 && $a <= 12 && $b >= 1 && $b <= 31) {
                    try {
                        return Carbon::createFromDate($year, $a, $b)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        // fall through
                    }
                }
                if ($b >= 1 && $b <= 12 && $a >= 1 && $a <= 31) {
                    try {
                        return Carbon::createFromDate($year, $b, $a)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        // fall through
                    }
                }
            }
        }

        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Throwable $e) {
            return Carbon::today()->toDateString();
        }
    }

    /**
     * Process a batch of receipts with retry logic for deadlocks
     * 
     * @param array $batch
     * @param int $batchIndex
     * @param int $processedCount
     * @param int $totalReceipts
     * @param array $columnMap
     * @param string $progressKey
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @return array
     */
    protected function processBatchWithRetry($batch, $batchIndex, $processedCount, $totalReceipts, $columnMap, $progressKey, array $batchLineCounts = [], $maxRetries = 3)
    {
        $importedCount = 0;
        $errors = [];
        $warnings = [];
        $attempt = 0;
        
        $batchProcessedCount = $processedCount;

        foreach ($batch as $receiptGroupKey => $saleRows) {
            $batchProcessedCount++;
            $parsed = $this->parseReceiptGroupKey($receiptGroupKey);
            $receiptLabel = $parsed['receipt'];
            $excelLines = isset($batchLineCounts[$receiptGroupKey])
                ? (int) $batchLineCounts[$receiptGroupKey]
                : null;

            if ($batchProcessedCount % 200 == 0 || $batchProcessedCount == $totalReceipts) {
                $percentage = 40 + round(($batchProcessedCount / $totalReceipts) * 55, 2);
                $skipNote = ($this->importMode === 'reimport' && $this->reimportFastSkipped > 0)
                    ? " · {$this->reimportFastSkipped} already complete"
                    : '';
                Cache::put($progressKey, [
                    'status' => 'processing',
                    'phase' => 'importing',
                    'current' => $batchProcessedCount,
                    'total' => $totalReceipts,
                    'percentage' => $percentage,
                    'message' => "Reimport {$batchProcessedCount}/{$totalReceipts}{$skipNote}",
                ], 900);
            }

            $fastSkip = $this->tryReimportFastSkip($receiptGroupKey, $saleRows, $columnMap, $excelLines);
            if ($fastSkip !== null) {
                continue;
            }

            $receiptAttempt = 0;
            $receiptDone = false;

            while ($receiptAttempt < $maxRetries && ! $receiptDone) {
                $receiptAttempt++;

                try {
                    DB::beginTransaction();

                    $result = $this->processReceipt(
                        $receiptGroupKey,
                        $saleRows,
                        $columnMap,
                        $progressKey,
                        $batchProcessedCount,
                        $totalReceipts
                    );

                    DB::commit();
                    $receiptDone = true;

                    if ($result['success'] && ! empty($result['actually_imported'])) {
                        $importedCount++;
                    }
                    $errors = array_merge($errors, $result['errors'] ?? []);
                    $warnings = array_merge($warnings, $result['warnings'] ?? []);
                } catch (\Exception $e) {
                    DB::rollBack();

                    $isDeadlock = strpos($e->getMessage(), 'Deadlock') !== false
                        || strpos($e->getMessage(), '1213') !== false
                        || strpos($e->getMessage(), '40001') !== false;

                    if ($isDeadlock && $receiptAttempt < $maxRetries) {
                        usleep(min(200000 * $receiptAttempt, 1000000));
                        Log::warning('Deadlock on receipt import, retrying', [
                            'receipt' => $receiptLabel,
                            'attempt' => $receiptAttempt,
                        ]);
                        continue;
                    }

                    Log::error("Error processing receipt {$receiptLabel}: " . $e->getMessage());
                    $errors[] = "Receipt {$receiptLabel}: " . $e->getMessage();
                    $receiptDone = true;
                }
            }
        }

        return [
            'processedCount' => $batchProcessedCount,
            'importedCount' => $importedCount,
            'errors' => $errors,
            'warnings' => $warnings,
            'batch_ok' => true,
        ];
    }

    /**
     * Decrement product stock with retry logic to handle deadlocks
     * 
     * @param int $produkId
     * @param int $quantity
     * @param int $maxRetries Maximum number of retry attempts (default: 3)
     * @return void
     * @throws \Exception If all retry attempts fail
     */
    protected function decrementStockWithRetry($produkId, $quantity, $maxRetries = 3)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            $attempt++;
            
            try {
                DB::table('produk')
                    ->where('id_produk', $produkId)
                    ->decrement('stok', $quantity);
                
                // Success - exit the retry loop
                return;
                
            } catch (\Exception $e) {
                // Check if it's a deadlock error
                $isDeadlock = strpos($e->getMessage(), 'Deadlock') !== false || 
                             strpos($e->getMessage(), '1213') !== false ||
                             strpos($e->getMessage(), '40001') !== false;
                
                if ($isDeadlock && $attempt < $maxRetries) {
                    // Wait before retrying (exponential backoff)
                    $waitTime = min(100000 * $attempt, 500000); // 100ms to 500ms
                    usleep($waitTime);
                    
                    Log::warning("Deadlock detected on stock update, retrying (attempt {$attempt}/{$maxRetries})", [
                        'produk_id' => $produkId,
                        'quantity' => $quantity,
                        'error' => $e->getMessage()
                    ]);
                    
                    continue;
                }
                
                // Not a deadlock, or max retries reached - throw the exception
                Log::error("Failed to decrement stock after {$attempt} attempts", [
                    'produk_id' => $produkId,
                    'quantity' => $quantity,
                    'error' => $e->getMessage()
                ]);
                
                throw $e;
            }
        }
    }
}