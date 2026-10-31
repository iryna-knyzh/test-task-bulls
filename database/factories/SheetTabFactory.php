<?php

namespace Database\Factories;

use App\Models\SheetTab;
use App\Models\Spreadsheet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SheetTab>
 */
class SheetTabFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spreadsheet_id' => Spreadsheet::factory(),
            'tab_name' => fake()->unique()->word(),
            'header_row' => 1,
        ];
    }
}
