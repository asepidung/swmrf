<?php

use Illuminate\Support\Facades\Route;

// ==========================================
// IMPORT CONTROLLERS
// ==========================================
use App\Http\Controllers\BeefPrintController;
use App\Http\Controllers\LogisticPoPrintController;

// ==========================================
// IMPORT MODELS
// ==========================================
use App\Models\AccountPayableInstallment;
use App\Models\BoningItem;
use App\Models\Carcass;
use App\Models\CattlePurchaseOrder;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;
use App\Models\LogisticReceiving;
use App\Models\LogisticRequisition;
use App\Models\PriceList;
use App\Models\Repack;
use App\Models\RepackMaterial;
use App\Models\RepackResult;
use App\Models\SalesOrder;

Route::get('/', function () {
    return redirect('/admin');
});

// ==========================================
// KUMPULAN ROUTE PRINT & VOUCHER (PROTECTED)
// ==========================================
Route::middleware(['web', 'auth'])->group(function () {

    // ------------------------------------------
    // 1. MODUL LOGISTIC
    // ------------------------------------------
    Route::get('/print/logistic-request/{id}', function ($id) {
        $record = LogisticRequisition::with(['user', 'supplier', 'items.item'])->findOrFail($id);
        return view('print.logistic-request', compact('record'));
    })->name('print.logistic-request');

    Route::get('/print/logistic-po/{id}', [LogisticPoPrintController::class, 'print'])->name('print.logistic-po');

    Route::get('/print/logistic-receiving/{id}', function ($id) {
        $receiving = LogisticReceiving::with(['purchaseOrder', 'supplier', 'items.item'])->findOrFail($id);
        return view('print.logistic-receiving', compact('receiving'));
    })->name('print.logistic-receiving');


    // ------------------------------------------
    // 2. MODUL BEEF (DAGING)
    // ------------------------------------------
    Route::get('/print/beef-request/{id}', [BeefPrintController::class, 'printRequest'])->name('print.beef-request');

    Route::get('/print/beef-po/{id}', [BeefPrintController::class, 'printPO'])->name('print.beef-po');


    // ------------------------------------------
    // 3. MODUL CATTLE (SAPI HIDUP)
    // ------------------------------------------
    Route::get('/print/cattle-po/{id}', function ($id) {
        $po = CattlePurchaseOrder::with(['supplier', 'items.cattleCategory', 'creator'])->findOrFail($id);
        return view('print.cattle-po', compact('po'));
    })->name('print.cattle-po');

    Route::get('/print/cattle-receiving/{id}', function ($id) {
        $record = CattleReceiving::with(['supplier', 'purchaseOrder', 'items.category', 'creator'])->findOrFail($id);
        return view('print.cattle-receiving', compact('record'));
    })->name('print.cattle-receiving');

    Route::get('/print/cattle-weighing/{id}', function ($id) {
        $record = CattleWeighing::with([
            'receiving.supplier',
            'receiving.purchaseOrder',
            'items.receivingItem',
            'creator'
        ])->findOrFail($id);

        return view('print.cattle-weighing', compact('record'));
    })->name('print.weighing');


    // ------------------------------------------
    // 4. MODUL CARCASS & BONING
    // ------------------------------------------
    Route::get('/print/carcass/{id}', function ($id) {
        $record = Carcass::with([
            'weighing.receiving.supplier',
            'items.weighingItem.receivingItem',
            'creator'
        ])->findOrFail($id);

        return view('print.carcass', compact('record'));
    })->name('print.carcass');

    Route::get('/print-label/{id}', function ($id) {
        $item = BoningItem::with(['product', 'boning', 'grade'])->findOrFail($id);
        return view('print.boning-label', compact('item'));
    })->name('boning.label');


    // ------------------------------------------
    // 5. MODUL REPACK
    // ------------------------------------------
    Route::get('/print-repack-label/{id}', function ($id) {
        $item = RepackResult::with(['product', 'repack', 'grade'])->findOrFail($id);
        return view('print.repack-label', compact('item'));
    })->name('repack.label');

    Route::get('/print-repack-summary/{id}', function ($id) {
        $repack = Repack::findOrFail($id);
        $bahan = RepackMaterial::with('product', 'grade')->where('repack_id', $id)->get();
        $hasil = RepackResult::with('product', 'grade')->where('repack_id', $id)->get();

        return view('print.repack-summary', compact('repack', 'bahan', 'hasil'));
    })->name('repack.summary');


    // ------------------------------------------
    // 6. SALES & MASTER DATA
    // ------------------------------------------
    Route::get('/print/pricelist/{priceList}', function (PriceList $priceList) {
        $priceList->load(['customerGroup', 'items.product']);
        return view('print.pricelist', compact('priceList'));
    })->name('print.pricelist');

    Route::get('/admin/sales-orders/{salesOrder}/print', function (SalesOrder $salesOrder) {
        $salesOrder->load(['customer', 'items.product', 'creator']);
        $totalWeight = $salesOrder->items->sum('weight');

        // Memanggil file view resources/views/sales-order-print.blade.php
        return view('print.sales-order-print', compact('salesOrder', 'totalWeight'));
    })->name('print.salesorder');


    // ------------------------------------------
    // 7. MODUL FINANCE (KEUANGAN)
    // ------------------------------------------
    Route::get('/vouchers/bank-out/{id}', function ($id) {
        $installment = AccountPayableInstallment::with(['payable.supplier', 'creator'])->findOrFail($id);
        return view('vouchers.bank-out', compact('installment'));
    })->name('vouchers.bank-out.print');
});
