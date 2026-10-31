<?php

namespace App\Http\Controllers;

use App\Enums\ColumnType;
use App\Http\Requests\TabMappingRequest;
use App\Models\SheetTab;
use App\Models\TabMapping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TabMappingController extends Controller
{
    public function create(SheetTab $tab): Response
    {
        return $this->form($tab, null);
    }

    public function store(TabMappingRequest $request, SheetTab $tab): RedirectResponse
    {
        DB::transaction(function () use ($request, $tab): void {
            $mapping = $tab->mappings()->create($request->safe()->only('target_table'));
            $mapping->columns()->createMany($request->validated('columns'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mapping created. The table is created on the next sync.']);

        return to_route('spreadsheets.edit', $tab->spreadsheet_id);
    }

    public function edit(TabMapping $mapping): Response
    {
        return $this->form($mapping->tab, $mapping->load('columns'));
    }

    /**
     * Columns are replaced as a whole. A removed column stays in the database
     * table and simply stops being filled.
     */
    public function update(TabMappingRequest $request, TabMapping $mapping): RedirectResponse
    {
        DB::transaction(function () use ($request, $mapping): void {
            $mapping->update($request->safe()->only('target_table'));
            $mapping->columns()->delete();
            $mapping->columns()->createMany($request->validated('columns'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mapping saved. New columns are added on the next sync.']);

        return to_route('spreadsheets.edit', $mapping->tab->spreadsheet_id);
    }

    public function destroy(TabMapping $mapping): RedirectResponse
    {
        $mapping->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Mapping to \"{$mapping->target_table}\" removed. Imported data was kept."]);

        return back();
    }

    private function form(SheetTab $tab, ?TabMapping $mapping): Response
    {
        return Inertia::render('mappings/form', [
            'tab' => $tab,
            'mapping' => $mapping,
            'columnTypes' => array_column(ColumnType::cases(), 'value'),
        ]);
    }
}
