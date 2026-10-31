<?php

use App\Enums\ColumnType;
use App\Models\ColumnMapping;
use App\Models\SheetTab;
use App\Models\TabMapping;

function mappingPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'target_table' => 'sales',
        'columns' => [
            ['source_column' => 'date', 'target_column' => 'date', 'data_type' => 'date'],
            ['source_column' => 'product_name', 'target_column' => 'name', 'data_type' => 'string'],
        ],
    ], $overrides);
}

test('a mapping is stored with its columns', function () {
    $tab = SheetTab::factory()->create();

    $this->post(route('tabs.mappings.store', $tab), mappingPayload())
        ->assertRedirect(route('spreadsheets.edit', $tab->spreadsheet_id));

    $mapping = $tab->mappings()->sole();

    expect($mapping->target_table)->toBe('sales')
        ->and($mapping->columns()->orderBy('id')->get(['source_column', 'target_column', 'data_type'])->toArray())
        ->toBe([
            ['source_column' => 'date', 'target_column' => 'date', 'data_type' => 'date'],
            ['source_column' => 'product_name', 'target_column' => 'name', 'data_type' => 'string'],
        ]);
});

test('invalid mappings are rejected', function (array $overrides, string $field, string $message) {
    $tab = SheetTab::factory()->create();

    $this->post(route('tabs.mappings.store', $tab), mappingPayload($overrides))
        ->assertSessionHasErrors([$field => $message]);

    expect(TabMapping::count())->toBe(0);
})->with([
    'reserved table' => [['target_table' => 'sessions'], 'target_table', 'This table is used by the application.'],
    'table name with sql' => [['target_table' => 'sales; drop table users'], 'target_table', 'Use lowercase latin letters, digits and underscores, starting with a letter.'],
    'column name with uppercase' => [['columns' => [1 => ['target_column' => 'Name']]], 'columns.1.target_column', 'Use lowercase latin letters, digits and underscores, starting with a letter.'],
    'id column' => [['columns' => [1 => ['target_column' => 'id']]], 'columns.1.target_column', 'The selected columns.1.target_column is invalid.'],
    'duplicate column' => [['columns' => [1 => ['target_column' => 'date']]], 'columns.1.target_column', 'The columns.1.target_column field has a duplicate value.'],
    'unknown type' => [['columns' => [1 => ['data_type' => 'blob']]], 'columns.1.data_type', 'The selected columns.1.data_type is invalid.'],
]);

test('a mapping without columns is rejected', function () {
    $tab = SheetTab::factory()->create();

    $this->post(route('tabs.mappings.store', $tab), ['target_table' => 'sales', 'columns' => []])
        ->assertSessionHasErrors(['columns' => 'The columns field is required.']);
});

test('updating a mapping replaces its columns', function () {
    $mapping = TabMapping::factory()->create(['target_table' => 'sales']);
    ColumnMapping::factory()->for($mapping)->create(['target_column' => 'old_column']);

    $this->put(route('mappings.update', $mapping), [
        'target_table' => 'sales',
        'columns' => [['source_column' => 'Region', 'target_column' => 'region', 'data_type' => ColumnType::String->value]],
    ])
        ->assertSessionHasNoErrors();

    expect($mapping->columns()->pluck('target_column')->all())->toBe(['region']);
});
