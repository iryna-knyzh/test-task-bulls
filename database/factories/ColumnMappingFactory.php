<?php

namespace Database\Factories;

use App\Enums\ColumnType;
use App\Models\ColumnMapping;
use App\Models\TabMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColumnMapping>
 */
class ColumnMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tab_mapping_id' => TabMapping::factory(),
            'source_column' => fake()->word(),
            'target_column' => fake()->unique()->lexify('col_????'),
            'data_type' => ColumnType::String,
        ];
    }
}
