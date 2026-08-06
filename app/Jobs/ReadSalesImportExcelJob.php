<?php

namespace App\Jobs;

use App\Imports\SalesImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads a stored Excel file and groups receipts into cache, then dispatches ProcessSalesImportJob.
 * Runs on the imports queue so the HTTP upload request can return immediately.
 */
class ReadSalesImportExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200;

    protected $progressKey;

    protected $storedPath;

    protected $userId;

    public function __construct(string $progressKey, string $storedPath, int $userId)
    {
        $this->progressKey = $progressKey;
        $this->storedPath = $storedPath;
        $this->userId = $userId;

        ini_set('memory_limit', '2048M');
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
    }

    public function handle(): void
    {
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $fullPath = Storage::disk('local')->path($this->storedPath);

        if (! is_file($fullPath)) {
            $this->failProgress('Uploaded file not found on server. Please try again.');

            return;
        }

        Cache::put($this->progressKey, [
            'status' => 'processing',
            'current' => 0,
            'total' => 0,
            'percentage' => 5,
            'message' => 'Reading Excel file (this may take several minutes for large files)...',
        ], 900);

        try {
            $import = new SalesImport($this->progressKey, $this->userId);
            Excel::import($import, $fullPath);
            $import->flushRemainderToCache();
        } catch (\Throwable $e) {
            Log::error('ReadSalesImportExcelJob failed: ' . $e->getMessage(), [
                'progress_key' => $this->progressKey,
                'path' => $this->storedPath,
                'trace' => $e->getTraceAsString(),
            ]);
            $this->failProgress('Failed to read Excel file: ' . $e->getMessage());

            return;
        } finally {
            try {
                Storage::disk('local')->delete($this->storedPath);
            } catch (\Throwable $e) {
                Log::warning('Could not delete temp import file: ' . $e->getMessage());
            }
        }

        $this->finalizeGroupedBatches($this->progressKey);

        $totalReceipts = (int) Cache::get("import_total_receipts_{$this->progressKey}", 0);
        $totalBatches = (int) Cache::get("import_batch_index_{$this->progressKey}", 0);

        Cache::forget("import_grouped_{$this->progressKey}");

        if ($totalReceipts === 0) {
            $this->failProgress('No valid receipts found in file. Check receipt numbers and sales dates.');

            return;
        }

        $importMode = Cache::get("import_mode_{$this->progressKey}", 'standard');
        $yearFrom = (int) Cache::get("import_year_from_{$this->progressKey}", 0);
        $yearTo = (int) Cache::get("import_year_to_{$this->progressKey}", 0);
        $isReimport = $importMode === 'reimport' && $yearFrom > 0 && $yearTo > 0;

        Cache::put($this->progressKey, [
            'status' => 'processing',
            'phase' => $isReimport ? 'prefiltering' : 'importing',
            'current' => 0,
            'total' => $totalReceipts,
            'receipt_groups' => $totalReceipts,
            'percentage' => 35,
            'message' => $isReimport
                ? "Excel read complete ({$totalReceipts} receipts). Starting reimport {$yearFrom}–{$yearTo} — comparing to database…"
                : "Excel read complete. Importing {$totalReceipts} receipt groups into sales (this is the slow step)…",
        ], 900);

        ProcessSalesImportJob::dispatch($this->progressKey, $totalBatches, $totalReceipts, $this->userId)
            ->onQueue('imports');
    }

    protected function finalizeGroupedBatches(string $progressKey): void
    {
        $remainder = Cache::get("import_grouped_{$progressKey}", []);
        if (! empty($remainder)) {
            $batchIndex = (int) Cache::get("import_batch_index_{$progressKey}", 0);
            Cache::put("import_grouped_{$progressKey}_batch_{$batchIndex}", $remainder, 900);
            Cache::put(
                "import_total_receipts_{$progressKey}",
                (int) Cache::get("import_total_receipts_{$progressKey}", 0) + count($remainder),
                900
            );
            Cache::put("import_batch_index_{$progressKey}", $batchIndex + 1, 900);
        }
    }

    protected function failProgress(string $message): void
    {
        Cache::put($this->progressKey, [
            'status' => 'error',
            'current' => 0,
            'total' => 0,
            'percentage' => 0,
            'message' => $message,
        ], 600);
    }
}
