<?php

namespace App\Console\Commands;

use App\Enums\SyncStatus;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;
use App\Services\Sheets\SheetSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sheets:sync {--now : Run synchronously instead of queueing}')]
#[Description('Import all active Google Sheets into their mapped database tables')]
class SyncSheetsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SheetSyncService $service): int
    {
        $failed = false;

        foreach (Spreadsheet::query()->active()->get() as $spreadsheet) {
            if (! $this->option('now')) {
                SyncSpreadsheetJob::dispatch($spreadsheet);
                $this->components->info("Queued [{$spreadsheet->name}].");

                continue;
            }

            $run = $service->sync($spreadsheet);

            if ($run->status === SyncStatus::Failed) {
                $failed = true;
                $this->components->error("[{$spreadsheet->name}] failed: {$run->error}");
            } else {
                $this->components->info("[{$spreadsheet->name}] synced {$run->rows_processed} row(s).");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
