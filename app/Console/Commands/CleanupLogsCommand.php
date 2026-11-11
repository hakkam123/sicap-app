<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copa:cleanup-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up obsolete system error logs and import logs in chunks to prevent database storage bloat';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database log cleanup process...');

        $deletedErrorLogs = 0;
        $deletedImportLogs = 0;
        $chunkSize = 500;

        // 1. Cleanup system_error_logs:
        // - status = resolved AND resolved_at < 7 hari lalu
        // - ATAU created_at < 30 hari lalu
        $this->comment('Cleaning up obsolete system_error_logs...');
        do {
            $ids = DB::table('system_error_logs')
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->where('status', 'resolved')
                          ->where('resolved_at', '<', now()->subDays(7));
                    })->orWhere('created_at', '<', now()->subDays(30));
                })
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $count = DB::table('system_error_logs')->whereIn('id', $ids)->delete();
            $deletedErrorLogs += $count;
        } while ($count > 0);

        // 2. Cleanup import_logs:
        // - created_at < 30 days ago
        $this->comment('Cleaning up old import_logs...');
        do {
            $ids = DB::table('import_logs')
                ->where('created_at', '<', now()->subDays(30))
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $count = DB::table('import_logs')->whereIn('id', $ids)->delete();
            $deletedImportLogs += $count;
        } while ($count > 0);

        // 3. Hapus file log lama: format per-hari (sebelum rotasi bulanan) & exception-trace.log
        $deletedFiles = 0;
        $logDir = storage_path('logs');
        $legacy = array_merge(
            glob($logDir . '/laravel-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].log') ?: [],
            glob($logDir . '/security-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].log') ?: [],
            glob($logDir . '/exception-trace.log') ?: [],
        );
        foreach ($legacy as $file) {
            if (@unlink($file)) {
                $deletedFiles++;
            }
        }

        $summary = "Log cleanup finished. Deleted {$deletedErrorLogs} system_error_logs and {$deletedImportLogs} import_logs, {$deletedFiles} file log lama.";
        $this->info($summary);

        return Command::SUCCESS;
    }
}

