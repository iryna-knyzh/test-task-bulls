<?php

use App\Enums\SyncStatus;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;

test('a failed job closes the sync run left running', function () {
    $spreadsheet = Spreadsheet::factory()->create();
    $finished = $spreadsheet->syncRuns()->create(['status' => SyncStatus::Success, 'started_at' => now()]);
    $running = $spreadsheet->syncRuns()->create(['status' => SyncStatus::Running, 'started_at' => now()]);

    (new SyncSpreadsheetJob($spreadsheet))->failed(new RuntimeException('Job has timed out.'));

    expect($running->fresh())
        ->status->toBe(SyncStatus::Failed)
        ->error->toBe('Job has timed out.')
        ->finished_at->not->toBeNull()
        ->and($finished->fresh()->status)->toBe(SyncStatus::Success);
});
