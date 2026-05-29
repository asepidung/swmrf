<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\PurchaseCattle;

class POCattlePrintController extends Controller
{
    public function __invoke(PurchaseCattle $record)
    {
        $record->load(['supplier', 'items.cattleClass', 'creator']);
        return view('print.po-cattle', compact('record'));
    }
}
