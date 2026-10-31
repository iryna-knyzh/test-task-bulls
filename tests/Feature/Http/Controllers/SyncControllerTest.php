<?php

use App\Enums\SyncStatus;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;
use Illuminate\Support\Facades\Queue;

test('sync now queues a sync of the spreadsheet', function () {
    $spreadsheet = Spreadsheet::factory()->create();
    Queue::fake([SyncSpreadsheetJob::class]);

    $this->from(route('spreadsheets.index'))
        ->post(route('spreadsheets.sync', $spreadsheet))
        ->assertRedirect(route('spreadsheets.index'));

    Queue::assertPushed(SyncSpreadsheetJob::class, fn (SyncSpreadsheetJob $job) => $job->spreadsheet->is($spreadsheet));
});

test('sync now does not queue a sync while one is running', function () {
    $spreadsheet = Spreadsheet::factory()->create();
    $spreadsheet->syncRuns()->create(['status' => SyncStatus::Running, 'started_at' => now()]);
    Queue::fake([SyncSpreadsheetJob::class]);

    $this->post(route('spreadsheets.sync', $spreadsheet))
        ->assertInertiaFlash('toast.type', 'warning');

    Queue::assertNothingPushed();
});
