<?php

use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Models\TabMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('the list shows configured spreadsheets', function () {
    $spreadsheet = Spreadsheet::factory()->create(['name' => 'Sales 2026']);

    $this->get(route('spreadsheets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('spreadsheets/index')
            ->has('spreadsheets', 1)
            ->where('spreadsheets.0.name', 'Sales 2026')
            ->where('spreadsheets.0.id', $spreadsheet->id));
});

test('the list tells whether a google api key is configured', function (?string $apiKey, bool $hasApiKey) {
    config(['sheets.google.api_key' => $apiKey]);

    $this->get(route('spreadsheets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('hasApiKey', $hasApiKey));
})->with([
    'key set' => ['AIzaSy-test-key', true],
    'no key' => [null, false],
]);

test('a spreadsheet is stored from a pasted google sheets url', function () {
    $this->post(route('spreadsheets.store'), [
        'name' => 'Sales',
        'google_spreadsheet_id' => 'https://docs.google.com/spreadsheets/d/1AbC_d-9/edit#gid=0',
        'is_active' => '1',
    ])
        ->assertRedirect(route('spreadsheets.edit', Spreadsheet::sole()))
        ->assertInertiaFlash('toast.type', 'success');

    expect(Spreadsheet::sole())
        ->google_spreadsheet_id->toBe('1AbC_d-9')
        ->is_active->toBeTrue();
});

test('a spreadsheet cannot be added twice', function () {
    Spreadsheet::factory()->create(['google_spreadsheet_id' => 'abc123']);

    $this->post(route('spreadsheets.store'), ['name' => 'Copy', 'google_spreadsheet_id' => 'abc123'])
        ->assertSessionHasErrors(['google_spreadsheet_id' => 'The google spreadsheet id has already been taken.']);
});

test('an unchecked daily sync checkbox disables the spreadsheet', function () {
    $spreadsheet = Spreadsheet::factory()->create(['is_active' => true]);

    $this->put(route('spreadsheets.update', $spreadsheet), [
        'name' => $spreadsheet->name,
        'google_spreadsheet_id' => $spreadsheet->google_spreadsheet_id,
    ])
        ->assertSessionHasNoErrors();

    expect($spreadsheet->fresh()->is_active)->toBeFalse();
});

test('a spreadsheet can be removed', function () {
    $spreadsheet = Spreadsheet::factory()->create();

    $this->delete(route('spreadsheets.destroy', $spreadsheet))
        ->assertRedirect(route('spreadsheets.index'));

    $this->assertModelMissing($spreadsheet);
});

test('changing the google spreadsheet id moves its imported rows to the new id', function () {
    $tab = SheetTab::factory()->create(['tab_name' => 'Sales']);
    TabMapping::factory()->for($tab, 'tab')->create(['target_table' => 'sales']);
    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->string('_source_spreadsheet_id');
        $table->string('_source_tab');
    });
    DB::table('sales')->insert(['_source_spreadsheet_id' => $tab->spreadsheet->google_spreadsheet_id, '_source_tab' => 'Sales']);
    $newSpreadsheetId = Str::random(44);

    $this->put(route('spreadsheets.update', $tab->spreadsheet), [
        'name' => $tab->spreadsheet->name,
        'google_spreadsheet_id' => $newSpreadsheetId,
    ])
        ->assertSessionHasNoErrors();

    expect(DB::table('sales')->pluck('_source_spreadsheet_id')->all())->toBe([$newSpreadsheetId]);
});
