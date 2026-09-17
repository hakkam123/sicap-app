<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        // - is_resolved = 1 AND resolved_at < 30 days ago
        // - OR created_at < 90 days ago
        $this->comment('Cleaning up obsolete system_error_logs...');
        do {
            $ids = DB::table('system_error_logs')
                ->where(function ($query) {
                    $query->where('is_resolved', 1)
                          ->where('resolved_at', '<', now()->subDays(30))
                          ->orWhere('created_at', '<', now()->subDays(90));
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

        $summary = "Log cleanup finished. Deleted {$deletedErrorLogs} system_error_logs and {$deletedImportLogs} import_logs.";
        $this->info($summary);

        // Log hasil cleanup ke daily logger
        try {
            Log::channel('daily')->info("[Database Maintenance] {$summary}");
        } catch (\Throwable $e) {
            Log::info("[Database Maintenance] {$summary}");
        }

        return Command::SUCCESS;
    }
}

