<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use App\Models\PurchaseCattle;
use App\Models\CattleReceiving;

class GlobalTaskPoller extends Component
{
    public int $lastPoId = 0;
    public int $lastReceivingId = 0;

    public function mount()
    {
        if (auth()->check()) {
            $this->lastPoId = (int) PurchaseCattle::max('id');
            $this->lastReceivingId = (int) CattleReceiving::max('id');
        }
    }

    public function checkTasks()
    {
        if (!auth()->check()) {
            return;
        }

        $currentPoId = (int) PurchaseCattle::max('id');
        $currentReceivingId = (int) CattleReceiving::max('id');

        if ($currentPoId > $this->lastPoId) {
            $this->lastPoId = $currentPoId;
            if (auth()->user()->hasPermission('create_cattle_receivings')) {
                Notification::make()
                    ->title(auth()->user()->name . ', ada tugas penerimaan sapi baru')
                    ->warning()
                    ->send();
            }
        }

        if ($currentReceivingId > $this->lastReceivingId) {
            $this->lastReceivingId = $currentReceivingId;
            if (auth()->user()->hasPermission('create_cattle_weighings')) {
                Notification::make()
                    ->title(auth()->user()->name . ', ada tugas timbang baru')
                    ->warning()
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
