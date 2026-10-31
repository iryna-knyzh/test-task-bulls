<?php

namespace App\Enums;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Carbon;
use Throwable;

enum ColumnType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Date = 'date';
    case DateTime = 'datetime';
    case Boolean = 'boolean';

    /**
     * Add a nullable column of this type to the given blueprint.
     */
    public function addTo(Blueprint $table, string $column): ColumnDefinition
    {
        return (match ($this) {
            self::String => $table->string($column),
            self::Text => $table->text($column),
            self::Integer => $table->bigInteger($column),
            self::Decimal => $table->decimal($column, 15, 4),
            self::Date => $table->date($column),
            self::DateTime => $table->dateTime($column),
            self::Boolean => $table->boolean($column),
        })->nullable();
    }

    /**
     * Whether a database column type name (as reported by Schema::getColumns) stores this type.
     */
    public function matchesDatabaseType(string $typeName): bool
    {
        return in_array(strtolower($typeName), match ($this) {
            self::String => ['varchar'],
            self::Text => ['text'],
            self::Integer => ['bigint'],
            self::Decimal => ['decimal'],
            self::Date => ['date'],
            self::DateTime => ['datetime'],
            self::Boolean => ['tinyint'],
        }, true);
    }

    /**
     * Convert a sheet cell to this type. Values that cannot be read become null.
     */
    public function cast(?string $value): string|int|bool|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return match ($this) {
            self::String => mb_substr($value, 0, 255),
            self::Text => $value,
            self::Integer => self::toNumber($value) === null ? null : (int) round((float) self::toNumber($value)),
            self::Decimal => self::toNumber($value),
            self::Date => self::toDate($value)?->toDateString(),
            self::DateTime => self::toDate($value)?->toDateTimeString(),
            self::Boolean => match (mb_strtolower($value)) {
                '1', 'true', 'yes', 'y' => true,
                '0', 'false', 'no', 'n' => false,
                default => null,
            },
        };
    }

    /**
     * Normalise "1 234,56", "1,234.56" or "$12.50" into a plain numeric string.
     */
    private static function toNumber(string $value): ?string
    {
        $number = (string) preg_replace('/[^\d,.\-]/', '', $value);

        if (str_contains($number, ',') && str_contains($number, '.')) {
            // The separator that comes last is the decimal one.
            $number = strrpos($number, ',') > strrpos($number, '.')
                ? str_replace(['.', ','], ['', '.'], $number)
                : str_replace(',', '', $number);
        } elseif (substr_count($number, ',') === 1 && preg_match('/,\d{3}$/', $number) !== 1) {
            $number = str_replace(',', '.', $number);
        } else {
            $number = str_replace(',', '', $number);
        }

        return is_numeric($number) ? $number : null;
    }

    private static function toDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
