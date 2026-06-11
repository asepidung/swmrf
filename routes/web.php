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

    Route::get('/goods-receipt-material/{id}/print', function ($id) {
        $record = \App\Models\GoodsReceiptMaterial::withTrashed()->with([
            'supplier',
            'purchaseMaterial',
            'items.material',
            'createdBy'
        ])->findOrFail($id);
        return view('print.goods-receipt-material', compact('record'));
    })->name('goods-receipt-material.print');

    // ------------------------------------------
    // 2. MODUL REQUEST BEEF (PRODUCT)
    // ------------------------------------------
    Route::get('/print/product-request/{id}', function ($id) {
        $record = \App\Models\ProductRequisition::with(['user', 'supplier', 'items.product'])->findOrFail($id);
        return view('print.product-request', compact('record'));
    })->name('print.product-request');

    Route::get('/po-product/{id}/print', function ($id) {
        $record = \App\Models\PurchaseProduct::with(['supplier', 'items.product', 'approver'])->findOrFail($id);
        return view('print.po-product', compact('record'));
    })->name('print.po-product');

    Route::get('/po-cattle/{record}/print', POCattlePrintController::class)->name('po-cattle.print');
    Route::get('/cattle-receiving/{record}/print', CattleReceivingPrintController::class)->name('cattle-receiving.print');
    Route::get('/cattle-weighing/{record}/print', CattleWeighingPrintController::class)->name('cattle-weighing.print');

    // ------------------------------------------
    // 3. MODUL BONING
    // ------------------------------------------
    Route::get('/print-label/{id}', function ($id) {
        $item = \App\Models\BoningItem::with(['product', 'boning', 'grade'])->findOrFail($id);
        return view('print.boning-label', compact('item'));
    })->name('boning.label');

    // ------------------------------------------
    // 4. MODUL REPACK
    // ------------------------------------------
    Route::get('/print-repack-label/{id}', function ($id) {
        $item = \App\Models\RepackResult::with(['product', 'repack', 'grade'])->findOrFail($id);
        return view('print.repack-label', compact('item'));
    })->name('repack.label');

    Route::get('/print-repack-summary/{id}', function ($id) {
        $repack = \App\Models\Repack::findOrFail($id);
        $bahan = \App\Models\RepackMaterial::with('product', 'grade')->where('repack_id', $id)->get();
        $hasil = \App\Models\RepackResult::with('product', 'grade')->where('repack_id', $id)->get();

        return view('print.repack-summary', compact('repack', 'bahan', 'hasil'));
    })->name('repack.summary');

    // ------------------------------------------
    // 5. MODUL PRICELIST
    // ------------------------------------------
    Route::get('/print/pricelist/{record}', function (\App\Models\PriceList $record) {
        $record->load(['customerGroup', 'items.product', 'creator']);
        return view('print.pricelist', compact('record'));
    })->name('print.pricelist');
});
