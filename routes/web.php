<?php

use App\Http\Controllers\SheetTabController;
use App\Http\Controllers\SpreadsheetController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\SyncRunController;
use App\Http\Controllers\TabMappingController;
use App\Http\Controllers\TargetTableController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/spreadsheets')->name('home');

Route::resource('spreadsheets', SpreadsheetController::class)->except('show');
Route::post('spreadsheets/{spreadsheet}/sync', [SyncController::class, 'store'])->name('spreadsheets.sync');

Route::resource('spreadsheets.tabs', SheetTabController::class)->only(['store', 'update', 'destroy'])->shallow();

Route::resource('tabs.mappings', TabMappingController::class)->except(['index', 'show'])->shallow();

Route::get('sync-runs', [SyncRunController::class, 'index'])->name('sync-runs.index');
Route::get('tables/{table}', [TargetTableController::class, 'show'])->name('tables.show');
