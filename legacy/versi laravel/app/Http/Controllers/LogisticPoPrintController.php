<?php

namespace App\Http\Controllers;

use App\Models\LogisticPurchaseOrder;
use Illuminate\Http\Request;

class LogisticPoPrintController extends Controller
{
    public function print($id)
    {
        $po = LogisticPurchaseOrder::with(['supplier', 'items.item', 'requisition.user', 'approver'])
            ->findOrFail($id);

        return view('print.logistic-po', compact('po'));
    }
}
