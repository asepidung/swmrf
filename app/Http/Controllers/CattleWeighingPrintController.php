<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CattleWeighing;

class CattleWeighingPrintController extends Controller
{
    public function __invoke($id)
    {
        $record = CattleWeighing::withTrashed()->findOrFail($id);
        $record->load(['receiving.supplier', 'receiving.purchaseCattle', 'items.receivingItem', 'creator', 'financialLoss']);
        return view('print.cattle-weighing', compact('record'));
    }
}
