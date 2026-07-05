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

    Route::get('/goods-receipt-product/{id}/print', function ($id) {
        $record = \App\Models\GoodsReceiptProduct::withTrashed()->with([
            'supplier',
            'purchaseProduct',
            'items.product',
            'items.grade',
            'createdBy'
        ])->findOrFail($id);
        return view('print.goods-receipt-product', compact('record'));
    })->name('goods-receipt-product.print');

    Route::get('/print-gr-beef-label/{id}', function ($id) {
        $item = \App\Models\GoodsReceiptProductItem::with(['product', 'goodsReceiptProduct', 'grade'])->findOrFail($id);
        return view('print.goods-receipt-product-label', compact('item'));
    })->name('goods-receipt-product.label');

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

    // ------------------------------------------
    // 6. MODUL SALES ORDER
    // ------------------------------------------
    Route::get('/print/salesorder/{record}', function (\App\Models\SalesOrder $record) {
        $record->load(['customer', 'items.product', 'creator']);
        return view('print.salesorder', compact('record'));
    })->name('print.salesorder');

    // ------------------------------------------
    // 7. MODUL TALLY
    // ------------------------------------------
    Route::get('/print/tally/{record}', function (\App\Models\Tally $record) {
        $record->load(['salesOrder.customer', 'items.product', 'creator']);
        
        $productData = [];
        foreach ($record->items as $item) {
            $productName = $item->product?->name ?? 'Unknown';
            if (!isset($productData[$productName])) {
                $productData[$productName] = [
                    'weights' => [],
                    'total' => 0,
                ];
            }
            $productData[$productName]['weights'][] = (float) $item->weight;
            $productData[$productName]['total'] += (float) $item->weight;
        }

        $totalBox = $record->items()->count();
        $totalQty = (float) $record->items()->sum('weight');

        return view('print.tally', compact('record', 'productData', 'totalBox', 'totalQty'));
    })->name('print.tally');

    Route::get('/print/delivery-order/{record}', function (\App\Models\DeliveryOrder $record) {
        return view('print.delivery-order', compact('record'));
    })->name('print.delivery-order');

    Route::get('/print/tally-item/{id}', function ($id) {
        $item = \App\Models\TallyItem::with(['product', 'grade'])->findOrFail($id);
        return view('print.tally-item-label', compact('item'));
    })->name('tally-item.label');

    // ------------------------------------------
    // 8. MODUL PLAN DELIVERY PREVIEW
    // ------------------------------------------
    Route::get('/print/delivery-plan/preview', function () {
        $tomorrow = now()->addDay()->toDateString();

        $records = \App\Models\DeliveryPlan::whereDate('delivery_date', $tomorrow)
            ->whereHas('salesOrders', function ($q) {
                $q->whereNotIn('status', ['canceled', 'cancelled']);
            })
            ->with(['customer', 'salesOrders'])
            ->get()
            ->sortBy('customer.name');

        return view('print.delivery-plan-preview', compact('records', 'tomorrow'));
    })->name('print.delivery-plan.preview');

    // ------------------------------------------
    // 9. MODUL INVOICE PRINT
    // ------------------------------------------
    Route::get('/print/invoice/{id}', function ($id) {
        $record = \App\Models\Invoice::withTrashed()->with(['customer', 'items.product'])->findOrFail($id);
        return view('print.invoice', compact('record'));
    })->name('print.invoice');

    // ------------------------------------------
    // 10. MODUL SALES RETURN
    // ------------------------------------------
    Route::get('/print-sales-return-label/{id}', function ($id) {
        $item = \App\Models\SalesReturnItem::with(['product', 'salesReturn', 'grade'])->findOrFail($id);
        return view('print.sales-return-label', compact('item'));
    })->name('sales-return.label');

    Route::get('/print/sales-return/{record}', function (\App\Models\SalesReturn $record) {
        $record->load(['customer', 'items.product']);
        return view('print.sales-return', compact('record'));
    })->name('sales-return.pdf');

    // ------------------------------------------
    // 11. MODUL MUTASI
    // ------------------------------------------
    Route::get('/print/mutation/{record}', function (\App\Models\Mutation $record) {
        $record->load(['fromWarehouse', 'toWarehouse', 'items.product', 'items.grade', 'createdBy', 'receivedBy']);
        return view('print.mutation', compact('record'));
    })->name('filament.admin.resources.mutations.print');

    // ------------------------------------------
    // 12. MODUL MATERIAL USAGE
    // ------------------------------------------
    Route::get('/print/material-usage/{id}', function ($id) {
        $record = \App\Models\MaterialUsageHeader::with(['usages.material'])->findOrFail($id);
        return view('print.material-usage', compact('record'));
    })->name('material-usage.print');
});
