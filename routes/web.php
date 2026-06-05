<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin'); // Redirect to filament admin instead of welcome
});

use App\Http\Controllers\POCattlePrintController;
use App\Http\Controllers\CattleReceivingPrintController;
use App\Http\Controllers\CattleWeighingPrintController;

Route::middleware(['web', 'auth'])->group(function () {
    // ------------------------------------------
    // 1. MODUL REQUEST MATERIAL
    // ------------------------------------------
    Route::get('/print/material-request/{id}', function ($id) {
        $record = \App\Models\MaterialRequisition::with(['user', 'supplier', 'items.material'])->findOrFail($id);
        return view('print.material-request', compact('record'));
    })->name('print.material-request');

    Route::get('/po-material/{id}/print', function ($id) {
        $record = \App\Models\PurchaseMaterial::with(['supplier', 'items.material', 'approvedBy'])->findOrFail($id);
        return view('print.po-material', compact('record'));
    })->name('print.po-material');

    Route::get('/po-cattle/{record}/print', POCattlePrintController::class)->name('po-cattle.print');
    Route::get('/cattle-receiving/{record}/print', CattleReceivingPrintController::class)->name('cattle-receiving.print');
    Route::get('/cattle-weighing/{record}/print', CattleWeighingPrintController::class)->name('cattle-weighing.print');
});
