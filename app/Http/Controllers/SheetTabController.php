<?php

namespace App\Http\Controllers;

use App\Http\Requests\SheetTabRequest;
use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Services\Sheets\SheetSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SheetTabController extends Controller
{
    public function store(SheetTabRequest $request, Spreadsheet $spreadsheet): RedirectResponse
    {
        $spreadsheet->tabs()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tab added.']);

        return back();
    }

    public function update(SheetTabRequest $request, SheetTab $tab, SheetSyncService $service): RedirectResponse
    {
        $previousTabName = $tab->tab_name;

        $tab->update($request->validated());

        if ($tab->wasChanged('tab_name')) {
            $service->relabelImportedRows($tab, $tab->spreadsheet->google_spreadsheet_id, $previousTabName);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tab saved.']);

        return back();
    }

    public function destroy(SheetTab $tab): RedirectResponse
    {
        $tab->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tab \"{$tab->tab_name}\" removed."]);

        return back();
    }
}
