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

    public function getPendingProductRequestCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('review_product_requisitions')) {
            return 0;
        }
        return \App\Models\ProductRequisition::where('status', 'Requested')->count();
    }

    public function getPendingProductFinanceCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('approve_product_requisitions')) {
            return 0;
        }
        return \App\Models\ProductRequisition::where('status', 'Pending Finance')->count();
    }

    public function getPendingRepackLockCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('lock_repacks')) {
            return 0;
        }
        return \App\Models\Repack::where('kunci', '!=', 1)->count();
    }

    public function getPendingTallyCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('create_tallies')) {
            return 0;
        }
        return \App\Models\SalesOrder::where('status', 'waiting')->count();
    }

    public function getPendingDeliveryPlanCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('edit_delivery_plans')) {
            return 0;
        }
        $tomorrow = now()->addDay()->toDateString();
        return \App\Models\DeliveryPlan::whereDate('delivery_date', $tomorrow)
            ->where(function ($query) {
                $query->whereNull('driver')
                    ->orWhere('driver', '')
                    ->orWhereNull('armada')
                    ->orWhere('armada', '')
                    ->orWhereNull('load_time');
            })
            ->count();
    }

    public function getPendingGrMaterialCount(): int
    {
        if (!auth()->user()->hasPermission('create_gr_materials')) {
            return 0;
        }
        return \App\Models\PurchaseMaterial::whereIn('status', ['pending', 'partial'])->count();
    }

    public function getPendingGrProductCount(): int
    {
        if (!auth()->user()->hasPermission('create_goods_receipt_products')) {
            return 0;
        }
        return \App\Models\PurchaseProduct::whereIn('status', ['pending', 'partial'])->count();
    }

    public function getPendingBoningLockCount(): int
    {
        if (!auth()->user()->hasPermission('lock_bonings')) {
            return 0;
        }
        return \App\Models\Boning::where('kunci', false)->count();
    }

    public function getPendingDeliveryOrderCount(): int
    {
        if (!auth()->user()->hasPermission('create_delivery_orders')) {
            return 0;
        }
        return \App\Models\Tally::where('status', 'locked')->whereDoesntHave('deliveryOrder')->count();
    }

    public function getPendingDeliveryReceiptCount(): int
    {
        if (!auth()->user()->hasPermission('view_delivery_receipts')) {
            return 0;
        }
        return \App\Models\DeliveryOrder::where('status', 'Ready')->count();
    }

    public function getPendingInvoiceExchangeCount(): int
    {
        $user = auth()->user();
        if (!$user->isProgrammer() && !$user->hasPermission('tukar_faktur')) {
            return 0;
        }
        return \App\Models\Invoice::whereNull('invoice_exchange_date')
            ->whereHas('customer', function ($query) {
                $query->where('invoice_exchange', true);
            })->count();
    }

    public function getPendingMutationCount(): int
    {
        if (!auth()->user()->hasPermission('view_mutations')) {
            return 0;
        }
        return \App\Models\Mutation::where('status', 'SENT')->count();
    }

    public function getPendingBeefStockTakeCount(): int
    {
        return \App\Models\StockTake::whereIn('status', ['DRAFT', 'IN_PROGRESS'])->count();
    }

    public function getPendingMaterialStockTakeCount(): int
    {
        return \App\Models\MaterialStockTake::whereIn('status', ['DRAFT', 'IN_PROGRESS'])->count();
    }

    public function getAging60DaysCount(): int
    {
        return \App\Models\BeefStock::where('status', 'IN_STOCK')
            ->where('pack_date', '<=', \Carbon\Carbon::now()->subDays(60))
            ->whereHas('grade', function ($q) {
                $q->where('name', 'like', '%CHILL%');
            })
            ->count();
    }
}
