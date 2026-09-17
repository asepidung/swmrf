<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CattleReceiving;

class CattleReceivingPrintController extends Controller
{
    public function __invoke($id)
    {
        abort_unless(auth()->user()?->hasPermission('view_cattle_receivings') ?? false, 403);

        $record = CattleReceiving::withTrashed()->findOrFail($id);
        $record->load(['supplier', 'purchaseCattle', 'items.cattleClass', 'creator']);
        return view('print.cattle-receiving', compact('record'));
    }
}
