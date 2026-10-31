<?php

namespace App\Http\Controllers;

use App\Enums\SyncStatus;
use App\Jobs\SyncSpreadsheetJob;
use App\Models\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SyncController extends Controller
{
    public function store(Spreadsheet $spreadsheet): RedirectResponse
    {
        if ($spreadsheet->syncRuns()->where('status', SyncStatus::Running)->exists()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => "A sync of \"{$spreadsheet->name}\" is already running. Check the sync log for the result."]);

            return back();
        }

        // The job is unique per spreadsheet, so a second click while it is still queued doesn't queue it twice.
        SyncSpreadsheetJob::dispatch($spreadsheet);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sync of \"{$spreadsheet->name}\" queued. Check the sync log for the result."]);

        return back();
    }
}
