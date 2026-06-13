<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use App\Models\PurchaseCattle;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;

class GlobalTaskPoller extends Component
{
    public int $lastPoId = 0;
    public int $lastReceivingId = 0;
    public int $lastWeighingId = 0;
    public int $lastSalesOrderId = 0;
    public string $lastMaterialCheckAt = '';
    public string $lastProductCheckAt = '';

    public function mount()
    {
        if (auth()->check()) {
            $this->lastPoId = (int) PurchaseCattle::max('id');
            $this->lastReceivingId = (int) CattleReceiving::max('id');
            $this->lastWeighingId = (int) CattleWeighing::max('id');
            $this->lastSalesOrderId = (int) \App\Models\SalesOrder::max('id');
            $this->lastMaterialCheckAt = now()->toDateTimeString();
            $this->lastProductCheckAt = now()->toDateTimeString();
        }
    }

    public function checkTasks()
    {
        if (!auth()->check()) {
            return;
        }

        $currentPoId = (int) PurchaseCattle::max('id');
        $currentReceivingId = (int) CattleReceiving::max('id');
        $currentWeighingId = (int) CattleWeighing::max('id');
        $currentSalesOrderId = (int) \App\Models\SalesOrder::max('id');
        $currentMaterialRequestId = (int) \App\Models\MaterialRequisition::max('id');

        if ($currentPoId > $this->lastPoId) {
            $this->lastPoId = $currentPoId;
            if (auth()->user()->hasPermission('create_cattle_receivings')) {
                Notification::make()
                    ->title(__('Ada tugas penerimaan sapi baru', ['name' => auth()->user()->name]))
                    ->warning()
                    ->send();
            }
        }

        if ($currentReceivingId > $this->lastReceivingId) {
            $this->lastReceivingId = $currentReceivingId;
            if (auth()->user()->hasPermission('create_cattle_weighings')) {
                Notification::make()
                    ->title(__('Ada tugas timbang baru', ['name' => auth()->user()->name]))
                    ->warning()
                    ->send();
            }
        }

        if ($currentWeighingId > $this->lastWeighingId) {
            $this->lastWeighingId = $currentWeighingId;
            if (auth()->user()->hasPermission('create_carcasses')) {
                Notification::make()
                    ->title(__(':name, ada tugas karkas baru', ['name' => auth()->user()->name]))
                    ->warning()
                    ->send();
            }
        }

        if ($currentSalesOrderId > $this->lastSalesOrderId) {
            $this->lastSalesOrderId = $currentSalesOrderId;
            if (auth()->user()->hasPermission('create_tallies')) {
                Notification::make()
                    ->title(__('Ada Sales Order baru yang siap dibuatkan Tally'))
                    ->warning()
                    ->send();
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

        if (empty($this->lastProductCheckAt)) {
            $this->lastProductCheckAt = now()->toDateTimeString();
        }

        $recentProductUpdates = \App\Models\ProductRequisition::where('updated_at', '>', $this->lastProductCheckAt)->get();
        if ($recentProductUpdates->isNotEmpty()) {
            $this->lastProductCheckAt = now()->toDateTimeString();
            foreach ($recentProductUpdates as $req) {
                if ($req->status === 'Requested' && $req->created_at->diffInSeconds($req->updated_at) <= 2) {
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('review_product_requisitions')) {
                        Notification::make()->title('ada request beef baru')->warning()->icon('heroicon-o-document-text')->send();
                    }
                }
                
                if ($req->status === 'Pending Finance') {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('Request Beef kamu sudah disetujui oleh purchasing dan diteruskan ke finance')->success()->icon('heroicon-o-check-circle')->send();
                    }
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('approve_product_requisitions')) {
                        Notification::make()->title('ada request beef baru yang menunggu persetujuanmu')->warning()->icon('heroicon-o-document-text')->send();
                    }
                }
                
                if ($req->status === 'Rejected') {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('Request beef anda ditolak')->danger()->icon('heroicon-o-x-circle')->send();
                    }
                }
                
                if (in_array($req->status, ['Approved', 'PO Generated'])) {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('request beef kamu disetujui finance')->success()->icon('heroicon-o-check-circle')->send();
                    }
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('review_product_requisitions')) {
                        Notification::make()->title('request beef di setujui finance')->success()->icon('heroicon-o-check-circle')->send();
                    }
                }
                
                if ($req->status === 'Returned to Purchasing') {
                    if ($req->user_id === auth()->id()) {
                        Notification::make()->title('Request beef anda dikembalikan oleh Finance')->danger()->icon('heroicon-o-x-circle')->send();
                    }
                    if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('review_product_requisitions')) {
                        Notification::make()->title('Request beef dikembalikan oleh Finance')->danger()->icon('heroicon-o-x-circle')->send();
                    }
                }
            }
        }
    }

    public function render()
    {
        return <<<'HTML'
            <div wire:poll.5s="checkTasks" class="hidden"></div>
        HTML;
    }
}
