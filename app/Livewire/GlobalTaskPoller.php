<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use App\Models\PurchaseCattle;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;

class GlobalTaskPoller extends Component
{
    public string $lastPurchaseCattleCheckAt = '';
    public string $lastCattleReceivingCheckAt = '';
    public string $lastCattleWeighingCheckAt = '';
    public string $lastSalesOrderCheckAt = '';
    public string $lastMaterialCheckAt = '';
    public string $lastProductCheckAt = '';
    public string $lastPurchaseMaterialCheckAt = '';
    public string $lastPurchaseProductCheckAt = '';
    public string $lastTallyCheckAt = '';

    public function mount()
    {
        if (auth()->check()) {
            $now = now()->toDateTimeString();
            $this->lastPurchaseCattleCheckAt = $now;
            $this->lastCattleReceivingCheckAt = $now;
            $this->lastCattleWeighingCheckAt = $now;
            $this->lastSalesOrderCheckAt = $now;
            $this->lastMaterialCheckAt = $now;
            $this->lastProductCheckAt = $now;
            $this->lastPurchaseMaterialCheckAt = $now;
            $this->lastPurchaseProductCheckAt = $now;
            $this->lastTallyCheckAt = $now;
        }
    }

    public function checkTasks()
    {
        if (!auth()->check()) {
            return;
        }

        if (empty($this->lastPurchaseMaterialCheckAt)) $this->lastPurchaseMaterialCheckAt = now()->toDateTimeString();
        $recentPurchaseMaterials = \App\Models\PurchaseMaterial::where('created_at', '>', $this->lastPurchaseMaterialCheckAt)->get();
        if ($recentPurchaseMaterials->isNotEmpty()) {
            $this->lastPurchaseMaterialCheckAt = now()->toDateTimeString();
            if (auth()->user()->hasPermission('create_gr_materials')) {
                foreach ($recentPurchaseMaterials as $item) {
                    Notification::make()->title(__('Ada PO Material baru yang siap diterima/dibuatkan GRM.'))->warning()->send();
                }
            }
        }

        if (empty($this->lastPurchaseProductCheckAt)) $this->lastPurchaseProductCheckAt = now()->toDateTimeString();
        $recentPurchaseProducts = \App\Models\PurchaseProduct::where('created_at', '>', $this->lastPurchaseProductCheckAt)->get();
        if ($recentPurchaseProducts->isNotEmpty()) {
            $this->lastPurchaseProductCheckAt = now()->toDateTimeString();
            if (auth()->user()->hasPermission('create_goods_receipt_products')) {
                foreach ($recentPurchaseProducts as $item) {
                    Notification::make()->title(__('Ada PO Beef baru yang siap diterima/dibuatkan GRB.'))->warning()->send();
                }
            }
        }

        if (empty($this->lastTallyCheckAt)) $this->lastTallyCheckAt = now()->toDateTimeString();
        $recentTallies = \App\Models\Tally::where('updated_at', '>', $this->lastTallyCheckAt)->get();
        if ($recentTallies->isNotEmpty()) {
            $this->lastTallyCheckAt = now()->toDateTimeString();
            if (auth()->user()->hasPermission('create_delivery_orders')) {
                foreach ($recentTallies as $item) {
                    if ($item->status === 'locked') {
                        Notification::make()->title(__('Ada Tally baru yang selesai dikunci (Locked) dan siap dibuatkan DO.'))->warning()->send();
                    }
                }
            }
        }

        if (empty($this->lastPurchaseCattleCheckAt)) $this->lastPurchaseCattleCheckAt = now()->toDateTimeString();
        $recentPurchaseCattles = PurchaseCattle::where('created_at', '>', $this->lastPurchaseCattleCheckAt)->get();
        if ($recentPurchaseCattles->isNotEmpty()) {
            $this->lastPurchaseCattleCheckAt = now()->toDateTimeString();
            if (auth()->user()->hasPermission('create_cattle_receivings')) {
                foreach ($recentPurchaseCattles as $item) {
                    Notification::make()->title(__('Ada tugas penerimaan sapi baru', ['name' => auth()->user()->name]))->warning()->send();
                }
            }
        }

        if (empty($this->lastCattleReceivingCheckAt)) $this->lastCattleReceivingCheckAt = now()->toDateTimeString();
        $recentReceivings = CattleReceiving::where('created_at', '>', $this->lastCattleReceivingCheckAt)->get();
        if ($recentReceivings->isNotEmpty()) {
            $this->lastCattleReceivingCheckAt = now()->toDateTimeString();
            if (auth()->user()->hasPermission('create_cattle_weighings')) {
                foreach ($recentReceivings as $item) {
                    Notification::make()->title(__('Ada tugas timbang baru', ['name' => auth()->user()->name]))->warning()->send();
                }
            }
        }

        if (empty($this->lastCattleWeighingCheckAt)) $this->lastCattleWeighingCheckAt = now()->toDateTimeString();
        $recentWeighings = CattleWeighing::where('created_at', '>', $this->lastCattleWeighingCheckAt)->get();
        if ($recentWeighings->isNotEmpty()) {
            $this->lastCattleWeighingCheckAt = now()->toDateTimeString();
            if (auth()->user()->hasPermission('create_carcasses')) {
                foreach ($recentWeighings as $item) {
                    Notification::make()->title(__(':name, ada tugas karkas baru', ['name' => auth()->user()->name]))->warning()->send();
                }
            }
        }

        if (empty($this->lastSalesOrderCheckAt)) $this->lastSalesOrderCheckAt = now()->toDateTimeString();
        $recentSalesOrders = \App\Models\SalesOrder::where('created_at', '>', $this->lastSalesOrderCheckAt)->get();
        if ($recentSalesOrders->isNotEmpty()) {
            $this->lastSalesOrderCheckAt = now()->toDateTimeString();
            if (auth()->user()->hasPermission('create_tallies')) {
                foreach ($recentSalesOrders as $so) {
                    Notification::make()->title(__('Ada Sales Order baru yang siap dibuatkan Tally'))->warning()->send();
                }
            }
        }

        if (empty($this->lastMaterialCheckAt)) {
            $this->lastMaterialCheckAt = now()->toDateTimeString();
        }

        $recentUpdates = \App\Models\MaterialRequisition::where('updated_at', '>', $this->lastMaterialCheckAt)->get();
        if ($recentUpdates->isNotEmpty()) {
            $this->lastMaterialCheckAt = now()->toDateTimeString();
            foreach ($recentUpdates as $req) {
                if ($req->status === 'Requested' && $req->created_at->diffInSeconds($req->updated_at) <= 2) {
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('review_material_requisitions')) {
                        Notification::make()->title('ada request material baru')->warning()->icon('heroicon-o-document-text')->send();
                    }
                }
                
                if ($req->status === 'Pending Finance') {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('Request Material kamu sudah disetujui oleh purchasing dan diteruskan ke finance')->success()->icon('heroicon-o-check-circle')->send();
                    }
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('approve_material_requisitions')) {
                        Notification::make()->title('ada request baru yang menunggu persetujuanmu')->warning()->icon('heroicon-o-document-text')->send();
                    }
                }
                
                if ($req->status === 'Rejected') {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('Request anda ditolak')->danger()->icon('heroicon-o-x-circle')->send();
                    }
                }
                
                if (in_array($req->status, ['Approved', 'PO Generated'])) {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('request kamu disetujui finance')->success()->icon('heroicon-o-check-circle')->send();
                    }
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('review_material_requisitions')) {
                        Notification::make()->title('request material di setujui finance')->success()->icon('heroicon-o-check-circle')->send();
                    }
                }
                
                if ($req->status === 'Returned to Purchasing') {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('Request anda dikembalikan oleh Finance')->danger()->icon('heroicon-o-x-circle')->send();
                    }
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('review_material_requisitions')) {
                        Notification::make()->title('Request dikembalikan oleh Finance')->danger()->icon('heroicon-o-x-circle')->send();
                    }
                }
            }
        }

        // Notifikasi Request Beef sudah PINDAH ke Web Push (TaskAlert),
        // dikirim langsung dari halaman yang mengubah statusnya. Toast
        // lintas-pengguna di sini dihapus supaya pemberitahuan tidak
        // datang dua kali.
        //
        // Modul lain masih memakai poller ini dan akan menyusul sambil
        // disisir, sesuai keputusan Owner untuk tidak mengubah semuanya
        // sekaligus.
    }

    public function render()
    {
        return <<<'HTML'
            <div wire:poll.5s="checkTasks" class="hidden"></div>
        HTML;
    }
}
