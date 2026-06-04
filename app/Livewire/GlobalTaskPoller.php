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
    public int $lastMaterialRequestId = 0;

    public function mount()
    {
        if (auth()->check()) {
            $this->lastPoId = (int) PurchaseCattle::max('id');
            $this->lastReceivingId = (int) CattleReceiving::max('id');
            $this->lastWeighingId = (int) CattleWeighing::max('id');
            $this->lastMaterialRequestId = (int) \App\Models\MaterialRequisition::max('id');
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

        if ($currentMaterialRequestId > $this->lastMaterialRequestId) {
            $this->lastMaterialRequestId = $currentMaterialRequestId;
            if (auth()->user()->isProgrammer() || auth()->user()->hasPermission('review_material_requisitions')) {
                Notification::make()
                    ->title(__('Hay :name ada request material baru', ['name' => auth()->user()->name]))
                    ->info()
                    ->send();
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
