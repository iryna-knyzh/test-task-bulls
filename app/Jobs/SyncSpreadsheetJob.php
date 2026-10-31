<?php

namespace App\Jobs;

use App\Enums\SyncStatus;
use App\Models\Spreadsheet;
use App\Services\Sheets\SheetSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncSpreadsheetJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Failures are recorded on the sync run, so the job is not retried.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public Spreadsheet $spreadsheet) {}

    public function uniqueId(): string
    {
        return (string) $this->spreadsheet->id;
    }

    public function handle(SheetSyncService $service): void
    {
        $service->sync($this->spreadsheet);
    }

    /**
     * Close the run left in "running" when the worker stops the job, e.g. on timeout.
     */
    public function failed(?Throwable $exception): void
    {
        $this->spreadsheet->syncRuns()
            ->where('status', SyncStatus::Running)
            ->update([
                'status' => SyncStatus::Failed,
                'error' => $exception?->getMessage() ?? 'The sync job failed.',
                'finished_at' => now(),
            ]);
    }
}
