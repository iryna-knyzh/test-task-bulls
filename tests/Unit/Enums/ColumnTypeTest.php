<?php

use App\Enums\ColumnType;

test('cells are cast to the column type', function (ColumnType $type, ?string $raw, mixed $expected) {
    expect($type->cast($raw))->toBe($expected);
})->with([
    'empty cell' => [ColumnType::String, '   ', null],
    'missing cell' => [ColumnType::Integer, null, null],
    'trimmed string' => [ColumnType::String, '  Apple ', 'Apple'],
    'integer with thousands' => [ColumnType::Integer, '1,234', 1234],
    'decimal with comma separator' => [ColumnType::Decimal, '1 234,56', '1234.56'],
    'decimal with currency' => [ColumnType::Decimal, '$1,234.56', '1234.56'],
    'decimal not a number' => [ColumnType::Decimal, 'n/a', null],
    'iso date' => [ColumnType::Date, '2026-10-30', '2026-10-30'],
    'dotted date' => [ColumnType::Date, '30.10.2026', '2026-10-30'],
    'unparseable date' => [ColumnType::Date, 'soon', null],
    'datetime' => [ColumnType::DateTime, '2026-10-30 14:05', '2026-10-30 14:05:00'],
    'boolean yes' => [ColumnType::Boolean, 'Yes', true],
    'boolean no' => [ColumnType::Boolean, '0', false],
    'boolean unknown' => [ColumnType::Boolean, 'maybe', null],
]);
