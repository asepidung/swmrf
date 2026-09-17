<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\PurchaseCattle;

class POCattlePrintController extends Controller
{
    public function __invoke($id)
    {
        abort_unless(auth()->user()?->hasPermission('view_purchase_cattles') ?? false, 403);

        $record = PurchaseCattle::withTrashed()->findOrFail($id);
        $record->load(['supplier', 'items.cattleClass', 'creator']);
        return view('print.po-cattle', compact('record'));
    }
}
