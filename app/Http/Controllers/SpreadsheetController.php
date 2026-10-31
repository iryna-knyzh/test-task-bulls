<?php

namespace App\Http\Controllers;

use App\Http\Requests\SpreadsheetRequest;
use App\Models\Spreadsheet;
use App\Services\Sheets\SheetSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SpreadsheetController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('spreadsheets/index', [
            'spreadsheets' => Spreadsheet::query()
                ->withCount('tabs')
                ->with('latestSyncRun')
                ->orderBy('name')
                ->get(),
            'hasApiKey' => filled(config('sheets.google.api_key')),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('spreadsheets/create');
    }

    public function store(SpreadsheetRequest $request): RedirectResponse
    {
        $spreadsheet = Spreadsheet::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Spreadsheet added. Now add the tabs to import.']);

        return to_route('spreadsheets.edit', $spreadsheet);
    }

    public function edit(Spreadsheet $spreadsheet): Response
    {
        return Inertia::render('spreadsheets/edit', [
            'spreadsheet' => $spreadsheet->load(['tabs.mappings.columns', 'latestSyncRun']),
            'hasApiKey' => filled(config('sheets.google.api_key')),
        ]);
    }

    public function update(SpreadsheetRequest $request, Spreadsheet $spreadsheet, SheetSyncService $service): RedirectResponse
    {
        $previousSpreadsheetId = $spreadsheet->google_spreadsheet_id;

        $spreadsheet->update($request->validated());

        if ($spreadsheet->wasChanged('google_spreadsheet_id')) {
            foreach ($spreadsheet->tabs()->with('mappings')->get() as $tab) {
                $service->relabelImportedRows($tab->setRelation('spreadsheet', $spreadsheet), $previousSpreadsheetId, $tab->tab_name);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Spreadsheet saved.']);

        return back();
    }

    /**
     * Removes the configuration only; data already imported stays in its tables.
     */
    public function destroy(Spreadsheet $spreadsheet): RedirectResponse
    {
        $spreadsheet->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Spreadsheet \"{$spreadsheet->name}\" removed."]);

        return to_route('spreadsheets.index');
    }
}
