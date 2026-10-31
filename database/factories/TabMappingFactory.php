<?php

namespace Database\Factories;

use App\Models\SheetTab;
use App\Models\TabMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TabMapping>
 */
class TabMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sheet_tab_id' => SheetTab::factory(),
            'target_table' => 'sheet_'.fake()->unique()->lexify('??????'),
        ];
    }
}
