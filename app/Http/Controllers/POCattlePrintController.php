<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\PurchaseCattle;

class POCattlePrintController extends Controller
{
    public function __invoke($id)
    {
        $record = PurchaseCattle::withTrashed()->findOrFail($id);
        $record->load(['supplier', 'items.cattleClass', 'creator']);
        return view('print.po-cattle', compact('record'));
    }
}
