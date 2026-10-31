<?php

use App\Enums\ColumnType;
use App\Enums\SyncStatus;
use App\Models\ColumnMapping;
use App\Models\SheetTab;
use App\Models\TabMapping;
use App\Services\Sheets\SheetsClient;
use App\Services\Sheets\SheetSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fakes\FakeSheetsClient;

/**
 * @param  array<string, array{0: string, 1: ColumnType}>  $columns  target => [source header, type]
 */
function mapTab(SheetTab $tab, string $table, array $columns): TabMapping
{
    $mapping = TabMapping::factory()->for($tab, 'tab')->create(['target_table' => $table]);

    foreach ($columns as $target => [$source, $type]) {
        ColumnMapping::factory()->for($mapping)->create([
            'source_column' => $source,
            'target_column' => $target,
            'data_type' => $type,
        ]);
    }

    return $mapping;
}

function fakeSheets(): FakeSheetsClient
{
    $client = new FakeSheetsClient;
    app()->instance(SheetsClient::class, $client);

    return $client;
}

function salesColumns(): array
{
    return [
        'date' => ['date', ColumnType::Date],
        'name' => ['product_name', ColumnType::String],
        'product_price' => ['price', ColumnType::Decimal],
        'amount' => ['amount', ColumnType::Integer],
    ];
}

test('rows are matched by header name regardless of column order and extra columns', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, 'sales', salesColumns());

    fakeSheets()->withTab($tab->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['Amount', 'Comment', 'Product_Name', 'Price', 'Date'],
        ['3', 'first', 'Apple', '1 234,50', '2026-10-01'],
        ['', '', '', '', ''],
        ['7', 'second', 'Pear', '$2.25', '02.10.2026'],
    ]);

    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Success)
        ->and($run->rows_processed)->toBe(2)
        ->and(DB::table('sales')->orderBy('id')->get(['date', 'name', 'product_price', 'amount'])->map(fn ($row) => (array) $row)->all())
        ->toEqual([
            ['date' => '2026-10-01', 'name' => 'Apple', 'product_price' => '1234.5000', 'amount' => 3],
            ['date' => '2026-10-02', 'name' => 'Pear', 'product_price' => '2.2500', 'amount' => 7],
        ])
        ->and(Schema::hasColumn('sales', 'comment'))->toBeFalse();
});

test('one tab can be stored into several tables with different column names', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, 'sales', salesColumns());
    mapTab($tab, 'products', ['title' => ['product_name', ColumnType::String]]);

    fakeSheets()->withTab($tab->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'price', 'amount'],
        ['2026-10-01', 'Apple', '1.5', '3'],
    ]);

    app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect(DB::table('sales')->value('product_price'))->toEqual('1.5000')
        ->and(DB::table('products')->pluck('title')->all())->toBe(['Apple']);
});

test('a newly mapped column is added to the existing table and filled', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    $mapping = mapTab($tab, 'sales', salesColumns());

    fakeSheets()->withTab($tab->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'price', 'amount', 'Region'],
        ['2026-10-01', 'Apple', '1.5', '3', 'North'],
    ]);

    app(SheetSyncService::class)->sync($tab->spreadsheet);

    ColumnMapping::factory()->for($mapping)->create([
        'source_column' => 'Region',
        'target_column' => 'region',
        'data_type' => ColumnType::String,
    ]);

    app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect(DB::table('sales')->pluck('region')->all())->toBe(['North']);
});

test('a changed column type is applied to the existing table column', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    $mapping = mapTab($tab, 'sales', ['amount' => ['amount', ColumnType::Integer]]);
    $sheets = fakeSheets();
    $spreadsheetId = $tab->spreadsheet->google_spreadsheet_id;

    $sheets->withTab($spreadsheetId, 'Sales', [['amount'], ['3']]);
    app(SheetSyncService::class)->sync($tab->spreadsheet);

    $mapping->columns()->update(['data_type' => ColumnType::String]);
    $sheets->withTab($spreadsheetId, 'Sales', [['amount'], ['n/a']]);
    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Success)
        ->and(Schema::getColumnType('sales', 'amount'))->toBe('varchar')
        ->and(DB::table('sales')->pluck('amount')->all())->toBe(['n/a']);
});

test('the source index name fits the identifier limit for long table names', function () {
    $table = str_repeat('a', 64);
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, $table, ['name' => ['product_name', ColumnType::String]]);
    fakeSheets()->withTab($tab->spreadsheet->google_spreadsheet_id, 'Sales', [['product_name'], ['Apple']]);

    app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect(collect(Schema::getIndexes($table))->pluck('name')->every(fn (string $name): bool => strlen($name) <= 64))->toBeTrue();
});

test('existing rows are kept when none of the mapped headers are found', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, 'sales', ['name' => ['product_name', ColumnType::String]]);
    $sheets = fakeSheets();
    $spreadsheetId = $tab->spreadsheet->google_spreadsheet_id;

    $sheets->withTab($spreadsheetId, 'Sales', [['product_name'], ['Apple']]);
    app(SheetSyncService::class)->sync($tab->spreadsheet);

    $sheets->withTab($spreadsheetId, 'Sales', [['title'], ['Pear']]);
    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Success)
        ->and($run->rows_processed)->toBe(0)
        ->and($run->warnings)->toBe(['Tab "Sales": none of the columns mapped to sales found in header row 1, existing rows kept.'])
        ->and(DB::table('sales')->pluck('name')->all())->toBe(['Apple']);
});

test('each sync replaces the rows previously imported from the tab', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, 'sales', salesColumns());
    $sheets = fakeSheets();
    $spreadsheetId = $tab->spreadsheet->google_spreadsheet_id;

    $sheets->withTab($spreadsheetId, 'Sales', [['product_name'], ['Apple'], ['Pear']]);
    app(SheetSyncService::class)->sync($tab->spreadsheet);

    $sheets->withTab($spreadsheetId, 'Sales', [['product_name'], ['Plum']]);
    app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect(DB::table('sales')->pluck('name')->all())->toBe(['Plum']);
});

test('rows imported from other spreadsheets into the same table are kept', function () {
    $first = SheetTab::factory()->create(['tab_name' => 'Sales']);
    $second = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($first, 'sales', salesColumns());
    mapTab($second, 'sales', salesColumns());

    $sheets = fakeSheets();
    $sheets->withTab($first->spreadsheet->google_spreadsheet_id, 'Sales', [['product_name'], ['Apple']]);
    $sheets->withTab($second->spreadsheet->google_spreadsheet_id, 'Sales', [['product_name'], ['Pear']]);

    app(SheetSyncService::class)->sync($first->spreadsheet);
    app(SheetSyncService::class)->sync($second->spreadsheet);
    app(SheetSyncService::class)->sync($first->spreadsheet);

    expect(DB::table('sales')->orderBy('name')->pluck('name')->all())->toBe(['Apple', 'Pear']);
});

test('a mapped header missing from the sheet is reported and left empty', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, 'sales', salesColumns());

    fakeSheets()->withTab($tab->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'amount'],
        ['2026-10-01', 'Apple', '3'],
    ]);

    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Success)
        ->and($run->warnings)->toBe(['Tab "Sales": column "price" not found, sales.product_price left empty.'])
        ->and(DB::table('sales')->value('product_price'))->toBeNull();
});

test('values that cannot be read as the column type are counted in a warning', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, 'sales', salesColumns());

    fakeSheets()->withTab($tab->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['date', 'product_name', 'price', 'amount'],
        ['n/a', 'Apple', '1.5', '3'],
        ['13/01/2026', 'Pear', '2', ''],
        ['2026-10-01', 'Plum', '3', '4'],
    ]);

    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Success)
        ->and($run->rows_processed)->toBe(3)
        ->and($run->warnings)->toBe(['Tab "Sales": 2 value(s) in column "date" could not be read as date, sales.date left empty for them.']);
});

test('a tab without mappings is skipped with a warning', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Unmapped']);
    fakeSheets();

    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Success)
        ->and($run->rows_processed)->toBe(0)
        ->and($run->warnings)->toBe(['Tab "Unmapped": no mappings configured, skipped.']);
});

test('a header row below the first row is respected', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales', 'header_row' => 2]);
    mapTab($tab, 'sales', ['name' => ['product_name', ColumnType::String]]);

    fakeSheets()->withTab($tab->spreadsheet->google_spreadsheet_id, 'Sales', [
        ['Monthly report'],
        ['product_name'],
        ['Apple'],
    ]);

    app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect(DB::table('sales')->pluck('name')->all())->toBe(['Apple']);
});

test('a failing google request marks the run as failed', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Missing']);
    mapTab($tab, 'sales', salesColumns());
    fakeSheets();

    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Failed)
        ->and($run->error)->toBe('Unable to parse range: Missing')
        ->and($run->finished_at)->not->toBeNull()
        ->and($tab->spreadsheet->fresh()->last_synced_at)->toBeNull();
});

test('a sync is refused while another sync of the spreadsheet holds the lock', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    mapTab($tab, 'sales', salesColumns());
    fakeSheets();
    Cache::lock("sheets-sync:{$tab->spreadsheet->id}", 60)->get();

    $run = app(SheetSyncService::class)->sync($tab->spreadsheet);

    expect($run->status)->toBe(SyncStatus::Failed)
        ->and($run->error)->toBe('Another sync of this spreadsheet is already running.')
        ->and(Schema::hasTable('sales'))->toBeFalse();
});
