<?php

namespace App\Http\Controllers;

use App\Models\TabMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class TargetTableController extends Controller
{
    /**
     * Preview a table filled by the sync. Only configured target tables are exposed.
     */
    public function show(string $table): Response
    {
        $isTargetTable = TabMapping::query()->where('target_table', $table)->exists();

        abort_unless($isTargetTable && Schema::hasTable($table), 404);

        return Inertia::render('tables/show', [
            'table' => $table,
            'columns' => Schema::getColumnListing($table),
            'rows' => DB::table($table)->orderByDesc('id')->paginate(50),
        ]);
    }
}
