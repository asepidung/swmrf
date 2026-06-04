<?php

namespace App\Filament\Admin\Widgets;

use App\Models\CattleReceiving;
use App\Models\PurchaseCattle;
use Filament\Widgets\Widget;

class PendingTaskWidget extends Widget
{
    protected static string $view = 'filament.admin.widgets.pending-task-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public function getPendingReceivingCount(): int
    {
        if (!auth()->user()->hasPermission('create_cattle_receivings')) {
            return 0;
        }
        return PurchaseCattle::doesntHave('receivings')->count();
    }

    public function getPendingWeighingCount(): int
    {
        if (!auth()->user()->hasPermission('create_cattle_weighings')) {
            return 0;
        }
        return CattleReceiving::doesntHave('weighing')->count();
    }

    public function getPendingCarcassCount(): int
    {
        if (!auth()->user()->hasPermission('create_carcasses')) {
            return 0;
        }
        return \App\Models\CattleWeighing::whereHas('items', function ($query) {
            $query->whereDoesntHave('carcassItems');
        })->count();
    }

    public function getPendingMaterialRequestCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('review_material_requisitions')) {
            return 0;
        }
        return \App\Models\MaterialRequisition::where('status', 'Requested')->count();
    }

    public function getPendingMaterialFinanceCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('approve_material_requisitions')) {
            return 0;
        }
        return \App\Models\MaterialRequisition::where('status', 'Pending Finance')->count();
    }
}
