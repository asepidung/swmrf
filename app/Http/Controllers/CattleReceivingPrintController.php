<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CattleReceiving;

class CattleReceivingPrintController extends Controller
{
    public function __invoke(CattleReceiving $record)
    {
        $record->load(['supplier', 'purchaseCattle', 'items.cattleClass', 'creator']);
        return view('print.cattle-receiving', compact('record'));
    }
}
