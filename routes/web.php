<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin'); // Redirect to filament admin instead of welcome
});

use App\Http\Controllers\POCattlePrintController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/po-cattle/{record}/print', POCattlePrintController::class)->name('po-cattle.print');
});
