<?php

namespace Database\Seeders;

use App\Enums\ColumnType;
use App\Models\Spreadsheet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $spreadsheet = Spreadsheet::query()->firstOrCreate(
            ['google_spreadsheet_id' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms'],
            ['name' => 'Google sample: class data'],
        );

        if ($spreadsheet->tabs()->exists()) {
            return;
        }

        $tab = $spreadsheet->tabs()->create(['tab_name' => 'Class Data', 'header_row' => 1]);

        $tab->mappings()->create(['target_table' => 'students'])->columns()->createMany([
            ['source_column' => 'Student Name', 'target_column' => 'name', 'data_type' => ColumnType::String],
            ['source_column' => 'Gender', 'target_column' => 'gender', 'data_type' => ColumnType::String],
            ['source_column' => 'Class Level', 'target_column' => 'class_level', 'data_type' => ColumnType::String],
            ['source_column' => 'Home State', 'target_column' => 'state', 'data_type' => ColumnType::String],
        ]);

        $tab->mappings()->create(['target_table' => 'student_majors'])->columns()->createMany([
            ['source_column' => 'Student Name', 'target_column' => 'student', 'data_type' => ColumnType::String],
            ['source_column' => 'Major', 'target_column' => 'major', 'data_type' => ColumnType::String],
            ['source_column' => 'Extracurricular Activity', 'target_column' => 'activity', 'data_type' => ColumnType::String],
        ]);
    }
}
