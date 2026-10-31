<?php

namespace App\Models;

use Database\Factories\TabMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sheet_tab_id
 * @property string $target_table
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['target_table'])]
class TabMapping extends Model
{
    /** @use HasFactory<TabMappingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<SheetTab, $this>
     */
    public function tab(): BelongsTo
    {
        return $this->belongsTo(SheetTab::class, 'sheet_tab_id');
    }

    /**
     * @return HasMany<ColumnMapping, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(ColumnMapping::class);
    }
}
