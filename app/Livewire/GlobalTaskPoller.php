<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use App\Models\PurchaseCattle;
use App\Models\CattleReceiving;
use App\Models\CattleWeighing;

class GlobalTaskPoller extends Component
{
    public function mount()
    {
        // Variabel polling sudah dihapus.
    }

    public function checkTasks()
    {
        // Semua toast lintas-pengguna buatan tangan sudah dihapus 
        // atas instruksi Project Owner, agar tidak tumpang tindih 
        // dengan notifikasi Web Push PWA yang akan/sedang dibangun.
    }

    public function render()
    {
        return <<<'HTML'
            <div wire:poll.10s="checkTasks" class="hidden"></div>
        HTML;
    }
}
