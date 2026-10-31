<?php

namespace App\Services\Sheets;

use App\Enums\SyncStatus;
use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Models\SyncRun;
use App\Models\TabMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SheetSyncService
{
    /**
     * Longer than the queued job's timeout, so a crashed sync frees the lock on its own.
     */
    private const int LOCK_SECONDS = 960;

    public function __construct(private readonly SheetsClient $sheets) {}

    /**
     * Import every tab of the spreadsheet into its mapped tables.
     */
    public function sync(Spreadsheet $spreadsheet): SyncRun
    {
        $lock = Cache::lock("sheets-sync:{$spreadsheet->id}", self::LOCK_SECONDS);

        if (! $lock->get()) {
            /** @var SyncRun */
            return $spreadsheet->syncRuns()->create([
                'status' => SyncStatus::Failed,
                'error' => 'Another sync of this spreadsheet is already running.',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        try {
            return $this->run($spreadsheet);
        } finally {
            $lock->release();
        }
    }

    /**
     * Point rows already imported from a tab at the tab's current source,
     * so after a tab rename or a spreadsheet ID change the next sync replaces them instead of duplicating them.
     */
    public function relabelImportedRows(SheetTab $tab, string $previousSpreadsheetId, string $previousTabName): void
    {
        $tab->loadMissing('mappings', 'spreadsheet');

        foreach ($tab->mappings->pluck('target_table')->unique() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where(['_source_spreadsheet_id' => $previousSpreadsheetId, '_source_tab' => $previousTabName])
                ->update(['_source_spreadsheet_id' => $tab->spreadsheet->google_spreadsheet_id, '_source_tab' => $tab->tab_name]);
        }
    }

    private function run(Spreadsheet $spreadsheet): SyncRun
    {
        /** @var SyncRun $run */
        $run = $spreadsheet->syncRuns()->create(['status' => SyncStatus::Running, 'started_at' => now()]);

        /** @var list<string> $warnings */
        $warnings = [];
        $rowsImported = 0;

        try {
            foreach ($spreadsheet->tabs()->with('mappings.columns')->get() as $tab) {
                if ($tab->mappings->isEmpty()) {
                    $warnings[] = "Tab \"{$tab->tab_name}\": no mappings configured, skipped.";

                    continue;
                }

                $rows = $this->sheets->getValues($spreadsheet->google_spreadsheet_id, $tab->tab_name);
                $headers = $this->headerPositions($rows[$tab->header_row - 1] ?? []);
                $dataRows = array_slice($rows, $tab->header_row);

                foreach ($tab->mappings as $mapping) {
                    $rowsImported += $this->import($spreadsheet, $tab, $mapping, $headers, $dataRows, $warnings);
                }
            }

            $run->update(['status' => SyncStatus::Success, 'rows_processed' => $rowsImported]);
            $spreadsheet->update(['last_synced_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);

            $run->update(['status' => SyncStatus::Failed, 'error' => $exception->getMessage()]);
        }

        $run->update(['warnings' => $warnings === [] ? null : $warnings, 'finished_at' => now()]);

        return $run;
    }

    /**
     * Replace the rows this tab previously wrote to the mapping's table.
     *
     * @param  array<string, int>  $headers
     * @param  list<list<string>>  $dataRows
     * @param  list<string>  $warnings
     */
    private function import(Spreadsheet $spreadsheet, SheetTab $tab, TabMapping $mapping, array $headers, array $dataRows, array &$warnings): int
    {
        $positions = [];

        foreach ($mapping->columns as $column) {
            $positions[$column->target_column] = $headers[$this->normalize($column->source_column)] ?? null;
        }

        if (array_filter($positions, fn (?int $position): bool => $position !== null) === []) {
            $warnings[] = "Tab \"{$tab->tab_name}\": none of the columns mapped to {$mapping->target_table} found in header row {$tab->header_row}, existing rows kept.";

            return 0;
        }

        $this->prepareTable($mapping);

        foreach ($mapping->columns as $column) {
            if ($positions[$column->target_column] === null) {
                $warnings[] = "Tab \"{$tab->tab_name}\": column \"{$column->source_column}\" not found, {$mapping->target_table}.{$column->target_column} left empty.";
            }
        }

        $source = ['_source_spreadsheet_id' => $spreadsheet->google_spreadsheet_id, '_source_tab' => $tab->tab_name];
        $syncedAt = now();
        $records = [];

        /** @var array<string, int> $unreadableCounts */
        $unreadableCounts = [];

        foreach ($dataRows as $row) {
            $record = [];

            foreach ($mapping->columns as $column) {
                $position = $positions[$column->target_column];
                $cell = $position === null ? null : ($row[$position] ?? null);
                $record[$column->target_column] = $column->data_type->cast($cell);

                if ($record[$column->target_column] === null && trim((string) $cell) !== '') {
                    $unreadableCounts[$column->target_column] = ($unreadableCounts[$column->target_column] ?? 0) + 1;
                }
            }

            if (array_filter($record, fn (mixed $value): bool => $value !== null) !== []) {
                $records[] = $record + $source + ['_synced_at' => $syncedAt];
            }
        }

        foreach ($mapping->columns as $column) {
            if (isset($unreadableCounts[$column->target_column])) {
                $warnings[] = "Tab \"{$tab->tab_name}\": {$unreadableCounts[$column->target_column]} value(s) in column \"{$column->source_column}\" could not be read as {$column->data_type->value}, {$mapping->target_table}.{$column->target_column} left empty for them.";
            }
        }

        DB::transaction(function () use ($mapping, $source, $records): void {
            DB::table($mapping->target_table)->where($source)->delete();

            foreach (array_chunk($records, 500) as $chunk) {
                DB::table($mapping->target_table)->insert($chunk);
            }
        });

        return count($records);
    }

    /**
     * Create the target table if needed, add mapped columns it is missing
     * and change columns whose type no longer matches the mapping.
     */
    private function prepareTable(TabMapping $mapping): void
    {
        $table = $mapping->target_table;

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->id();
                $blueprint->string('_source_spreadsheet_id');
                $blueprint->string('_source_tab');
                $blueprint->timestamp('_synced_at');
                // The default index name is too long for MySQL's 64-character limit on longer table names.
                $blueprint->index(['_source_spreadsheet_id', '_source_tab'], 'sync_source_'.substr(md5($table), 0, 16));
            });
        }

        /** @var array<string, string> $existingTypes */
        $existingTypes = collect(Schema::getColumns($table))->pluck('type_name', 'name')->all();

        $missing = $mapping->columns->reject(fn ($column): bool => isset($existingTypes[$column->target_column]));
        $changed = $mapping->columns->filter(fn ($column): bool => isset($existingTypes[$column->target_column])
            && ! $column->data_type->matchesDatabaseType($existingTypes[$column->target_column]));

        if ($missing->isEmpty() && $changed->isEmpty()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing, $changed): void {
            foreach ($missing as $column) {
                $column->data_type->addTo($blueprint, $column->target_column);
            }

            foreach ($changed as $column) {
                $column->data_type->addTo($blueprint, $column->target_column)->change();
            }
        });
    }

    /**
     * @param  list<string>  $headerRow
     * @return array<string, int>
     */
    private function headerPositions(array $headerRow): array
    {
        $positions = [];

        foreach ($headerRow as $position => $header) {
            $positions[$this->normalize($header)] ??= $position;
        }

        unset($positions['']);

        return $positions;
    }

    private function normalize(string $header): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $header)));
    }
}
