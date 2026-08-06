<?php

namespace App\Console\Commands;

use App\Imports\SalesImport;
use App\Models\Penjualan;
use App\Models\PenjualanDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class AuditSalesImportCommand extends Command
{
    protected $signature = 'sales:audit-import
                            {file : Path to the Excel file (.xlsx/.xls)}
                            {--limit=50 : Max missing receipts to print}';

    protected $description = 'Compare an Excel sales file to the database and list receipts not imported or with fewer lines than Excel';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $progressKey = 'audit_import_' . time() . '_' . getmypid();
        $this->info('Reading Excel and grouping receipts (same rules as import)...');

        try {
            Excel::import(new SalesImport($progressKey, 0), $path);
        } catch (\Throwable $e) {
            $this->error('Failed to read Excel: ' . $e->getMessage());

            return self::FAILURE;
        }

        $remainder = Cache::get("import_grouped_{$progressKey}", []);
        $totalBatches = (int) Cache::get("import_batch_index_{$progressKey}", 0);
        $grouped = [];

        for ($i = 0; $i < $totalBatches; $i++) {
            $batch = Cache::get("import_grouped_{$progressKey}_batch_{$i}", []);
            $grouped = array_merge($grouped, $batch);
            Cache::forget("import_grouped_{$progressKey}_batch_{$i}");
        }
        $grouped = array_merge($grouped, $remainder);

        $skippedRows = (int) Cache::get("import_skipped_rows_{$progressKey}", 0);
        $totalGroups = count($grouped);

        if ($totalGroups === 0) {
            $this->warn('No receipt groups found in file.');
            if ($skippedRows > 0) {
                $this->warn("{$skippedRows} row(s) skipped — no readable receipt number.");
            }

            return self::SUCCESS;
        }

        $missing = [];
        $partial = [];

        foreach ($grouped as $groupKey => $rows) {
            $receiptNo = $this->cleanReceiptFromGroupKey($groupKey);
            $salesDate = $this->salesDateFromGroupKey($groupKey);
            if ($salesDate === '' && ! empty($rows)) {
                $first = is_array($rows[0]) ? $rows[0] : (array) $rows[0];
                $col = 3;
                $salesDate = trim((string) ($first[$col] ?? ''));
            }
            $excelLines = $this->countValidExcelLines($rows);

            $sale = null;
            if ($salesDate !== '') {
                $sale = Penjualan::where('receiptno', $receiptNo)->whereDate('saledate', $salesDate)->first();
                if (! $sale) {
                    $sale = Penjualan::where('receiptno', 'like', $receiptNo . '|%')
                        ->whereDate('saledate', $salesDate)
                        ->first();
                }
            }
            if (! $sale) {
                $sale = Penjualan::where('receiptno', $groupKey)->first();
            }

            if (! $sale) {
                $missing[] = [
                    'receipt' => $receiptNo,
                    'date' => $salesDate,
                    'excel_lines' => $excelLines,
                    'group_key' => $groupKey,
                ];
                continue;
            }

            $dbLines = (int) PenjualanDetail::where('id_penjualan', $sale->id_penjualan)->count();
            if ($dbLines < $excelLines) {
                $partial[] = [
                    'receipt' => $receiptNo,
                    'date' => $salesDate,
                    'excel_lines' => $excelLines,
                    'db_lines' => $dbLines,
                    'penjualan_id' => $sale->id_penjualan,
                ];
            }
        }

        $this->line('');
        $this->info("Receipt groups in Excel: {$totalGroups}");
        $this->info('Missing from sales list: ' . count($missing));
        $this->info('In DB but fewer lines than Excel: ' . count($partial));
        if ($skippedRows > 0) {
            $this->warn("Excel rows without receipt number: {$skippedRows}");
        }

        $limit = (int) $this->option('limit');

        if (count($missing) > 0) {
            $this->line('');
            $this->error('Missing receipts (not in penjualan):');
            foreach (array_slice($missing, 0, $limit) as $row) {
                $datePart = ! empty($row['date']) ? ' ' . $row['date'] : '';
                $this->line("  - {$row['receipt']}{$datePart} ({$row['excel_lines']} line(s) in file)");
            }
            if (count($missing) > $limit) {
                $this->line('  ... and ' . (count($missing) - $limit) . ' more.');
            }
        }

        if (count($partial) > 0) {
            $this->line('');
            $this->warn('Partial imports (sale exists but fewer detail lines):');
            foreach (array_slice($partial, 0, $limit) as $row) {
                $datePart = ! empty($row['date']) ? ' ' . $row['date'] : '';
                $this->line("  - {$row['receipt']}{$datePart}: Excel {$row['excel_lines']} lines, DB {$row['db_lines']} (sale #{$row['penjualan_id']})");
            }
            if (count($partial) > $limit) {
                $this->line('  ... and ' . (count($partial) - $limit) . ' more.');
            }
            $this->line('');
            $this->comment('Re-run import for the same file; new lines will be appended and stock deducted for missing items.');
        }

        Cache::forget("import_grouped_{$progressKey}");
        Cache::forget("import_skipped_rows_{$progressKey}");
        Cache::forget("import_column_map_{$progressKey}");

        return self::SUCCESS;
    }

    protected function cleanReceiptFromGroupKey(string $groupKey): string
    {
        $parts = explode('|', $groupKey);
        return trim($parts[0] ?? $groupKey);
    }

    protected function salesDateFromGroupKey(string $groupKey): string
    {
        $parts = explode('|', $groupKey);
        $second = trim($parts[1] ?? '');
        if ($second !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $second)) {
            return $second;
        }

        return '';
    }

    protected function countValidExcelLines(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $arr = is_array($row) ? $row : (array) $row;
            $qty = (int) ($arr[1] ?? 0);
            $commodity = trim((string) ($arr[2] ?? ''));
            $total = (float) preg_replace('/[^\d.-]/', '', (string) ($arr[9] ?? 0));
            if ($commodity !== '' || $qty > 0 || $total > 0) {
                $count++;
            }
        }

        return $count;
    }
}
