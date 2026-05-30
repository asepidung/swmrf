<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin'); // Redirect to filament admin instead of welcome
});

use App\Http\Controllers\POCattlePrintController;
use App\Http\Controllers\CattleReceivingPrintController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/po-cattle/{record}/print', POCattlePrintController::class)->name('po-cattle.print');
    Route::get('/cattle-receiving/{record}/print', CattleReceivingPrintController::class)->name('cattle-receiving.print');
});
