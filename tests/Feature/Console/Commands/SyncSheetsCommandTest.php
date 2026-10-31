<?php

use App\Enums\ColumnType;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\ColumnMapping;
use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Models\TabMapping;
use App\Services\Sheets\SheetsClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\FakeSheetsClient;

test('only active spreadsheets are queued by default', function () {
    $active = Spreadsheet::factory()->create();
    Spreadsheet::factory()->create(['is_active' => false]);
    Queue::fake([SyncSpreadsheetJob::class]);

    $this->artisan('sheets:sync')->assertSuccessful();

    Queue::assertPushed(SyncSpreadsheetJob::class, 1);
    Queue::assertPushed(SyncSpreadsheetJob::class, fn (SyncSpreadsheetJob $job) => $job->spreadsheet->is($active));
});

test('the now option imports synchronously and reports failures', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    $mapping = TabMapping::factory()->for($tab, 'tab')->create(['target_table' => 'sales']);
    ColumnMapping::factory()->for($mapping)->create(['source_column' => 'name', 'target_column' => 'name', 'data_type' => ColumnType::String]);
    $broken = SheetTab::factory()->create(['tab_name' => 'Missing']);
    ColumnMapping::factory()->for(TabMapping::factory()->for($broken, 'tab'))->create();

    app()->instance(SheetsClient::class, (new FakeSheetsClient)->withTab(
        $tab->spreadsheet->google_spreadsheet_id, 'Sales', [['name'], ['Apple'], ['Pear']],
    ));

    $this->artisan('sheets:sync', ['--now' => true])
        ->expectsOutputToContain("[{$tab->spreadsheet->name}] synced 2 row(s).")
        ->expectsOutputToContain("[{$broken->spreadsheet->name}] failed: Unable to parse range: Missing")
        ->assertFailed();

    expect(DB::table('sales')->count())->toBe(2);
});

test('the daily sync is scheduled', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('sheets:sync');
});
