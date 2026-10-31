<?php

namespace App\Http\Controllers;

use App\Models\SyncRun;
use Inertia\Inertia;
use Inertia\Response;

class SyncRunController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('sync-runs/index', [
            'runs' => SyncRun::query()
                ->with('spreadsheet:id,name')
                ->latest('id')
                ->paginate(25),
        ]);
    }
}
