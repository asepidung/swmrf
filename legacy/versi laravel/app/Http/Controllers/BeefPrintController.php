<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BeefRequisition;
use App\Models\BeefPurchaseOrder;

class BeefPrintController extends Controller
{
    // Fungsi untuk memuat data request beef dan menampilkannya ke view print
    public function printRequest($id)
    {
        $request = BeefRequisition::with(['user', 'supplier', 'items.product'])->findOrFail($id);
        return view('print.beef-request', compact('request'));
    }

    // Fungsi untuk memuat data PO beef dan menampilkannya ke view print
    public function printPO($id)
    {
        $po = BeefPurchaseOrder::with(['requisition', 'supplier', 'approver', 'items.product'])->findOrFail($id);
        return view('print.beef-po', compact('po'));
    }
}
