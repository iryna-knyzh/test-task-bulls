<?php

namespace App\Models;

use Database\Factories\SheetTabFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $spreadsheet_id
 * @property string $tab_name
 * @property int $header_row
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tab_name', 'header_row'])]
class SheetTab extends Model
{
    /** @use HasFactory<SheetTabFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Spreadsheet, $this>
     */
    public function spreadsheet(): BelongsTo
    {
        return $this->belongsTo(Spreadsheet::class);
    }

    /**
     * @return HasMany<TabMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(TabMapping::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'header_row' => 'integer',
        ];
    }
}
