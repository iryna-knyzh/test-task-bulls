<?php

namespace Database\Factories;

use App\Models\Spreadsheet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Spreadsheet>
 */
class SpreadsheetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'google_spreadsheet_id' => Str::random(44),
            'is_active' => true,
        ];
    }
}
