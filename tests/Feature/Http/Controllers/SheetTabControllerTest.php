<?php

use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Models\TabMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('a tab is added to the spreadsheet', function () {
    $spreadsheet = Spreadsheet::factory()->create();

    $this->post(route('spreadsheets.tabs.store', $spreadsheet), ['tab_name' => 'Sales', 'header_row' => 2])
        ->assertSessionHasNoErrors();

    expect($spreadsheet->tabs()->sole())
        ->tab_name->toBe('Sales')
        ->header_row->toBe(2);
});

test('the same tab cannot be configured twice for one spreadsheet', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);

    $this->post(route('spreadsheets.tabs.store', $tab->spreadsheet), ['tab_name' => 'Sales', 'header_row' => 1])
        ->assertSessionHasErrors(['tab_name' => 'The tab name has already been taken.']);
});

test('renaming a tab moves its imported rows to the new name', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    TabMapping::factory()->for($tab, 'tab')->create(['target_table' => 'sales']);
    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->string('_source_spreadsheet_id');
        $table->string('_source_tab');
    });
    DB::table('sales')->insert(['_source_spreadsheet_id' => $tab->spreadsheet->google_spreadsheet_id, '_source_tab' => 'Sales']);

    $this->put(route('tabs.update', $tab), ['tab_name' => 'Orders', 'header_row' => 1])
        ->assertSessionHasNoErrors();

    expect(DB::table('sales')->pluck('_source_tab')->all())->toBe(['Orders']);
});
