<?php

namespace App\Imports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class SalesImport implements ToCollection, WithChunkReading, WithBatchInserts, WithEvents
{
    /** Max receipts to hold in memory before flushing to a batch cache key */
    const RECEIPT_BATCH_SIZE = 1000;

    protected $progressKey;
    protected $userId;
    protected $totalRows = 0;
    protected $processedRows = 0;
    protected $isFirstChunk = true;

    /** In-memory grouping — avoids serializing huge arrays to file cache every chunk */
    protected $groupedData = [];
    protected $lastReceiptNo = null;
    protected $lastReceiptKey = null;
    protected $columnMap = null;
    protected $skippedRowsCount = 0;
    protected $lastProgressWrite = 0;

    /** @var array<string, int> receipt group key => valid Excel line count (for fast reimport skip) */
    protected $excelLineCounts = [];

    public function __construct($progressKey, $userId)
    {
        $this->progressKey = $progressKey;
        $this->userId = $userId;

        ini_set('memory_limit', '2048M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        Cache::put("import_batch_index_{$progressKey}", 0, 900);
        Cache::put("import_total_receipts_{$progressKey}", 0, 900);
        Cache::put("import_skipped_rows_{$progressKey}", 0, 900);
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                Cache::put("import_skipped_rows_{$this->progressKey}", $this->skippedRowsCount, 900);
            },
        ];
    }

    public function chunkSize(): int
    {
        $mode = Cache::get("import_mode_{$this->progressKey}", 'standard');

        return $mode === 'reimport' ? 10000 : 2500;
    }

    public function batchSize(): int
    {
        $mode = Cache::get("import_mode_{$this->progressKey}", 'standard');

        return $mode === 'reimport' ? 10000 : 2500;
    }

    /** Default column indices when no header is detected (Supplier, StockOut, Commodity, SalesDate, Means, ConfirmPr, Shops, Receipt, Discount, Total Amc, Buying pri, Selling Pri) */
    const DEFAULT_COL_MAP = [
        'supplier' => 0, 'stockOut' => 1, 'commodity' => 2, 'salesDate' => 3, 'paymentMethod' => 4,
        'confirmPrice' => 5, 'shop' => 6, 'receipt' => 7, 'totalAmount' => 9, 'buyingPrice' => 10, 'sellingPrice' => 11,
    ];

    /**
     * Process each chunk of rows. Group by receipt and flush to batch cache keys
     * so we never hold more than RECEIPT_BATCH_SIZE receipts in one cache key.
     */
    public function collection(Collection $rows)
    {
        if ($this->isFirstChunk) {
            $headerRow = $rows->first();
            if ($headerRow) {
                $headerArray = is_array($headerRow) ? $headerRow : $headerRow->toArray();
                $this->columnMap = $this->detectColumnMap($headerArray);
                Cache::put("import_column_map_{$this->progressKey}", $this->columnMap, 900);
            }
            $rows = $rows->slice(1);
            $this->isFirstChunk = false;
        }

        $chunkCount = $rows->count();
        $this->totalRows += $chunkCount;
        $this->processedRows += $chunkCount;

        $receiptGroups = (int) Cache::get("import_total_receipts_{$this->progressKey}", 0)
            + count($this->groupedData);

        if ($this->processedRows - $this->lastProgressWrite >= 5000) {
            $this->lastProgressWrite = $this->processedRows;
            $percentage = min(28, round(($this->processedRows / max($this->processedRows, 1)) * 28, 2));
            Cache::put($this->progressKey, [
                'status' => 'processing',
                'phase' => 'reading',
                'current' => $this->processedRows,
                'total' => $this->processedRows,
                'rows_read' => $this->processedRows,
                'receipt_groups' => $receiptGroups,
                'percentage' => $percentage,
                'message' => "Reading Excel file… {$this->processedRows} rows scanned"
                    . ($receiptGroups > 0 ? ", {$receiptGroups} receipt groups found so far" : ''),
            ], 900);
        }

        $groupedData = &$this->groupedData;
        $columnMap = $this->columnMap ?? self::DEFAULT_COL_MAP;
        $lastReceiptNo = $this->lastReceiptNo;
        $lastReceiptKey = $this->lastReceiptKey;

        $importMode = Cache::get("import_mode_{$this->progressKey}", 'standard');
        $yearFrom = (int) Cache::get("import_year_from_{$this->progressKey}", 0);
        $yearTo = (int) Cache::get("import_year_to_{$this->progressKey}", 0);
        $filterByYear = $importMode === 'reimport' && $yearFrom > 0 && $yearTo > 0;

        foreach ($rows as $row) {
            if ($row->filter()->isEmpty()) {
                continue;
            }

            $rowArray = $this->normalizeImportRowArray($row->toArray());
            $receiptFromCell = $this->extractReceiptNo($rowArray, $columnMap);
            $supplierFromRow = $this->getSupplierFromRow($rowArray, $columnMap);

            $receiptNo = $receiptFromCell;
            // Excel often has receipt number only on first row of a receipt; continuation rows have blank Receipt
            if (empty($receiptNo) && $lastReceiptNo !== null) {
                $receiptNo = $lastReceiptNo;
            }
            // If still empty (e.g. merged cells), scan receipt/shop columns only (avoid matching Total Amount etc.)
            // Last resort when mapped columns returned empty: only receipt column ±1 (never shop/totals — those look "receipt-like" with loose rules)
            if (empty($receiptNo)) {
                $receiptCol = $columnMap['receipt'] ?? 7;
                foreach ([$receiptCol, $receiptCol - 1, $receiptCol + 1] as $col) {
                    if ($col < 0 || ! isset($rowArray[$col])) {
                        continue;
                    }
                    $v = $this->normalizeReceiptCandidate((string) ($rowArray[$col] ?? ''));
                    if ($v !== '' && $this->looksLikeReceiptStrict($v)) {
                        $receiptNo = $v;
                        break;
                    }
                }
            }
            if (empty($receiptNo)) {
                $this->skippedRowsCount++;
                continue;
            }

            $currentDate = $this->parseDateFromRow($rowArray, $columnMap);

            if ($filterByYear) {
                $rowYear = (int) substr($currentDate, 0, 4);
                if ($rowYear < $yearFrom || $rowYear > $yearTo) {
                    continue;
                }
            }

            // Group by receipt + sales date so the same receipt number in different years stays separate (e.g. A44576 in 2013 vs 2024).
            $groupKey = $this->buildReceiptGroupKey($receiptNo, $currentDate);

            if (empty($receiptFromCell) && $lastReceiptKey !== null) {
                $lastMeta = $this->parseGroupKeyMeta($lastReceiptKey);
                if ($lastMeta['receipt'] === $receiptNo && $lastMeta['date'] === $currentDate) {
                    $groupKey = $lastReceiptKey;
                }
            }

            // When receipt appears again in the sheet, move trailing rows from the previous block only if same receipt + date + supplier.
            if (!empty($receiptFromCell) && $lastReceiptKey !== null && $groupKey !== $lastReceiptKey && isset($groupedData[$lastReceiptKey])) {
                $prevGroup = $groupedData[$lastReceiptKey];
                $toMove = [];
                $toKeep = [];
                foreach ($prevGroup as $r) {
                    $rDate = $this->parseDateFromRow($r, $columnMap);
                    $rSupplier = $this->getSupplierFromRow($r, $columnMap);
                    $sameDate = ($rDate === $currentDate);
                    $sameSupplier = (trim($rSupplier) === trim($supplierFromRow));
                    if ($sameDate && $sameSupplier) {
                        $toMove[] = $r;
                    } else {
                        $toKeep[] = $r;
                    }
                }
                if (!empty($toMove)) {
                    $groupedData[$lastReceiptKey] = $toKeep;
                    if (!isset($groupedData[$groupKey])) {
                        $groupedData[$groupKey] = [];
                    }
                    $groupedData[$groupKey] = array_merge($toMove, $groupedData[$groupKey]);
                }
            }

            if (!empty($receiptFromCell)) {
                $lastReceiptKey = $groupKey;
                $lastReceiptNo = $receiptNo;
            } elseif ($lastReceiptKey === null) {
                $lastReceiptKey = $groupKey;
                $lastReceiptNo = $receiptNo;
            }

            if (!isset($groupedData[$groupKey])) {
                $groupedData[$groupKey] = [];
            }
            $groupedData[$groupKey][] = $rowArray;

            if ($this->isValidImportRow($rowArray, $columnMap)) {
                $this->excelLineCounts[$groupKey] = ($this->excelLineCounts[$groupKey] ?? 0) + 1;
            }
        }

        $this->lastReceiptNo = $lastReceiptNo;
        $this->lastReceiptKey = $lastReceiptKey;

        $this->flushFullBatches();

        if ($this->processedRows % 15000 === 0) {
            gc_collect_cycles();
        }
    }

    /**
     * Move full receipt batches from memory into cache (called after each chunk).
     */
    protected function flushFullBatches(): void
    {
        while (count($this->groupedData) >= self::RECEIPT_BATCH_SIZE) {
            $batch = array_slice($this->groupedData, 0, self::RECEIPT_BATCH_SIZE, true);
            $this->groupedData = array_slice($this->groupedData, self::RECEIPT_BATCH_SIZE, null, true);

            $batchIndex = (int) Cache::get("import_batch_index_{$this->progressKey}", 0);
            Cache::put("import_grouped_{$this->progressKey}_batch_{$batchIndex}", $batch, 900);
            $countSlice = array_intersect_key($this->excelLineCounts, $batch);
            if ($countSlice !== []) {
                Cache::put("import_line_counts_{$this->progressKey}_batch_{$batchIndex}", $countSlice, 900);
                $this->excelLineCounts = array_diff_key($this->excelLineCounts, $countSlice);
            }
            Cache::put("import_batch_index_{$this->progressKey}", $batchIndex + 1, 900);
            Cache::put(
                "import_total_receipts_{$this->progressKey}",
                (int) Cache::get("import_total_receipts_{$this->progressKey}", 0) + count($batch),
                900
            );
        }
    }

    /**
     * Persist any remaining grouped receipts after the last Excel chunk.
     */
    public function flushRemainderToCache(): void
    {
        $this->flushFullBatches();
        if (! empty($this->groupedData)) {
            Cache::put("import_grouped_{$this->progressKey}", $this->groupedData, 900);
        }
        if (! empty($this->excelLineCounts)) {
            Cache::put("import_line_counts_{$this->progressKey}", $this->excelLineCounts, 900);
        }
    }

    /**
     * Whether a row counts as a sale line (matches ProcessSalesImportJob::countValidExcelLines).
     */
    protected function isValidImportRow(array $rowArray, array $columnMap): bool
    {
        $stockOut = (int) ($rowArray[$columnMap['stockOut'] ?? 1] ?? 0);
        $commodity = trim((string) ($rowArray[$columnMap['commodity'] ?? 2] ?? ''));
        $confirmPrice = (float) preg_replace('/[^\d.-]/', '', (string) ($rowArray[$columnMap['confirmPrice'] ?? 5] ?? 0));
        $totalAmount = (float) preg_replace('/[^\d.-]/', '', (string) ($rowArray[$columnMap['totalAmount'] ?? 9] ?? 0));
        $buyingPrice = (float) preg_replace('/[^\d.-]/', '', (string) ($rowArray[$columnMap['buyingPrice'] ?? 10] ?? 0));

        return $commodity !== '' || $stockOut > 0 || $confirmPrice > 0 || $totalAmount > 0 || $buyingPrice > 0;
    }

    /**
     * Get total rows processed
     */
    public function getTotalRows()
    {
        return $this->totalRows;
    }

    /**
     * Detect column indices from header row (case-insensitive partial match).
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

    protected function detectColumnMap(array $headerRow): array
    {
        $headerRow = $this->normalizeImportRowArray($headerRow);
        $map = self::DEFAULT_COL_MAP;

        // Prefer "Total Amount" column: must contain both "total" and "amount", and must NOT be "Discount"
        foreach ($headerRow as $colIndex => $cell) {
            $val = is_string($cell) ? trim($cell) : (string) $cell;
            $lower = strtolower($val);
            if (strpos($lower, 'total') !== false && strpos($lower, 'amount') !== false && strpos($lower, 'discount') === false) {
                $map['totalAmount'] = (int) $colIndex;
                break;
            }
        }

        // Commodity first — do not use bare "item" (matches "Item #", "Line Item", etc. and reads 1,2,3 as names).
        foreach ($headerRow as $colIndex => $cell) {
            $lower = strtolower(trim(is_string($cell) ? $cell : (string) $cell));
            if ($lower === 'commodity' || str_contains($lower, 'commodity')) {
                $map['commodity'] = (int) $colIndex;
                break;
            }
        }

        $keywords = [
            'supplier' => ['supplier'],
            'stockOut' => ['stock out', 'stockout', 'stock', 'qty', 'quantity'],
            'commodity' => ['product name', 'product', 'description', 'nama produk'],
            'salesDate' => ['sales date', 'saledate', 'salesdat', 'date'],
            'paymentMethod' => ['means of payment', 'means', 'payment', 'mop'],
            'confirmPrice' => ['confirm price', 'confirm pr', 'confirm'],
            'shop' => ['shop'],
            'receipt' => ['receipt'],
            'totalAmount' => ['total', 'amc', 'amount'],
            'buyingPrice' => ['buying price', 'buying pri', 'buying', 'cost', 'harga beli'],
            'sellingPrice' => ['selling price', 'selling pri', 'selling', 'sell', 'harga jual'],
        ];

        foreach ($headerRow as $colIndex => $cell) {
            $val = is_string($cell) ? trim($cell) : (string) $cell;
            if ($val === '') {
                continue;
            }
            $lower = strtolower($val);
            foreach ($keywords as $key => $terms) {
                if ($key === 'totalAmount' && isset($map['totalAmount'])) {
                    continue;
                }
                if ($key === 'commodity' && isset($map['commodity'])) {
                    continue;
                }
                foreach ($terms as $term) {
                    if (strpos($lower, $term) !== false) {
                        $map[$key] = (int) $colIndex;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Cache key for one sale: receipt number + sales date (Y-m-d).
     */
    protected function buildReceiptGroupKey(string $receiptNo, string $salesDate): string
    {
        return $receiptNo . '|' . $salesDate;
    }

    /**
     * Parse group key metadata (supports legacy receipt|supplier keys from older imports).
     *
     * @return array{receipt: string, date: string, supplier: string, legacy_supplier_only: bool}
     */
    protected function parseGroupKeyMeta(string $groupKey): array
    {
        $parts = explode('|', $groupKey);
        $receipt = trim($parts[0] ?? '');
        $second = trim($parts[1] ?? '');
        $third = trim($parts[2] ?? '');

        if ($second !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $second)) {
            return [
                'receipt' => $receipt,
                'date' => $second,
                'supplier' => $third,
                'legacy_supplier_only' => false,
            ];
        }

        return [
            'receipt' => $receipt,
            'date' => '',
            'supplier' => $second,
            'legacy_supplier_only' => $second !== '',
        ];
    }

    /**
     * Get supplier name from a row for grouping (receipt + supplier key).
     */
    protected function getSupplierFromRow(array $row, array $columnMap): string
    {
        $row = $this->normalizeImportRowArray($row);
        $col = $columnMap['supplier'] ?? 0;
        $val = isset($row[$col]) ? trim((string) ($row[$col] ?? '')) : '';

        return $val !== '' ? $val : 'Unknown';
    }

    /**
     * Extract Receipt No from row using column map when provided.
     * Uses strict matching only: shop names, supplier names, and amounts must not be mistaken for receipts.
     */
    protected function extractReceiptNo(array $row, array $columnMap = []): ?string
    {
        $row = $this->normalizeImportRowArray($row);
        $colMap = !empty($columnMap) ? $columnMap : self::DEFAULT_COL_MAP;
        $receiptCol = $colMap['receipt'] ?? 7;

        // Only receipt column and immediate neighbours — never shop column (e.g. "SIAFU MPYA" was wrongly used as receipt).
        $tryColumns = array_values(array_unique([$receiptCol, $receiptCol - 1, $receiptCol + 1]));
        foreach ($tryColumns as $col) {
            if ($col < 0) {
                continue;
            }
            if (isset($row[$col]) && (string) trim($row[$col] ?? '') !== '') {
                $value = $this->normalizeReceiptCandidate((string) $row[$col]);
                if ($value !== '' && $this->looksLikeReceiptStrict($value)) {
                    return $value;
                }
            }
        }

        // Scan row but skip known data columns (avoids supplier name, commodity, dates, prices).
        $skip = [];
        foreach (['supplier', 'stockOut', 'commodity', 'salesDate', 'paymentMethod', 'confirmPrice', 'shop', 'receipt', 'totalAmount', 'buyingPrice', 'sellingPrice'] as $key) {
            if (isset($colMap[$key])) {
                $skip[(int) $colMap[$key]] = true;
            }
        }
        foreach ($row as $idx => $cell) {
            if (isset($skip[(int) $idx])) {
                continue;
            }
            $val = $this->normalizeReceiptCandidate((string) ($cell ?? ''));
            if ($val !== '' && $this->looksLikeReceiptStrict($val)) {
                return $val;
            }
        }

        return null;
    }

    /**
     * Normalize receipt cell (trim, remove internal spaces from values like "UTAM 301").
     */
    protected function normalizeReceiptCandidate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_replace('/\s+/', '', $value);
    }

    /**
     * Strict receipt check: no spaces (rules out "SIAFU MPYA"), must look like A51029 / UTAM301 / 12345 — not arbitrary text with letters.
     */
    protected function looksLikeReceiptStrict(string $value): bool
    {
        $value = $this->normalizeReceiptCandidate($value);
        if ($value === '') {
            return false;
        }
        if (strpos($value, ' ') !== false) {
            return false;
        }
        // Alphanumeric receipt: optional letters then digits (and optional trailing alnum), e.g. A51029, UTAM301
        if (preg_match('/^[A-Za-z]{0,10}\d{2,14}[A-Za-z0-9-]*$/', $value)) {
            return true;
        }
        // Plain numeric receipt (short), e.g. 50123 — avoid treating decimals as receipts
        if (preg_match('/^\d{3,10}$/', $value)) {
            $n = (float) $value;

            return $n > 0 && $n < 100000000;
        }

        return false;
    }

    /**
     * @deprecated Prefer looksLikeReceiptStrict; kept for any external callers.
     */
    protected function looksLikeReceipt(string $value): bool
    {
        return $this->looksLikeReceiptStrict($value);
    }

    /**
     * Parse sales date from a row for same-date reassignment (Y-m-d).
     */
    protected function parseDateFromRow(array $row, array $columnMap): string
    {
        $row = $this->normalizeImportRowArray($row);
        $col = $columnMap['salesDate'] ?? 3;
        $dateString = isset($row[$col]) ? trim((string) $row[$col]) : '';
        if ($dateString === '' && $dateString !== 0 && $dateString !== '0') {
            return Carbon::today()->toDateString();
        }
        if (is_numeric($dateString)) {
            try {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dateString);
                return $date->format('Y-m-d');
            } catch (\Throwable $e) {
                // fall through
            }
        }
        $dateString = trim((string) $dateString);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $dateString, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $dateString, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            if ($a >= 1 && $a <= 12 && $b >= 1 && $b <= 31) {
                return sprintf('%04d-%02d-%02d', $y, $a, $b);
            }
            if ($b >= 1 && $b <= 12 && $a >= 1 && $a <= 31) {
                return sprintf('%04d-%02d-%02d', $y, $b, $a);
            }
        }
        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Throwable $e) {
            return Carbon::today()->toDateString();
        }
    }
}

