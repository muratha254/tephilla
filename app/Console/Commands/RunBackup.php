<?php

namespace App\Console\Commands;

use App\Services\BackupRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:run-backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run database backup (scheduled)';

    protected BackupRunnerService $backupRunner;

    public function __construct(BackupRunnerService $backupRunner)
    {
        parent::__construct();
        $this->backupRunner = $backupRunner;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $result = $this->backupRunner->run('auto', true);

            if ($result === null) {
                $this->info('Auto backup is disabled. Skipping...');

                return Command::SUCCESS;
            }

            $this->info('Backup created: '.$result['filename']);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Backup failed: '.$e->getMessage());
            Log::error('[Backup] Auto backup failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }
}
