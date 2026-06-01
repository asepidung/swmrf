<x-filament-widgets::widget class="fi-wi-pending-task">
    @if($this->getPendingReceivingCount() > 0 || $this->getPendingWeighingCount() > 0)
    <div class="space-y-4">
        @if($this->getPendingReceivingCount() > 0)
        <div class="p-4 rounded-xl shadow-sm ring-1 ring-red-500 bg-red-500/10 dark:bg-red-900/30 flex items-center gap-x-4">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-8 h-8 text-red-600 dark:text-red-400" />
            <div>
                <h3 class="text-lg font-bold text-red-600 dark:text-red-400">PERINGATAN TUGAS TERTUNDA</h3>
                <p class="text-sm font-medium text-red-600 dark:text-red-300">Ada <span class="text-xl font-black px-1">{{ $this->getPendingReceivingCount() }}</span> penerimaan sapi yang belum dikerjakan.</p>
            </div>
        </div>
        @endif

        @if($this->getPendingWeighingCount() > 0)
        <div class="p-4 rounded-xl shadow-sm ring-1 ring-amber-500 bg-amber-500/10 dark:bg-amber-900/30 flex items-center gap-x-4">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-8 h-8 text-amber-600 dark:text-amber-400" />
            <div>
                <h3 class="text-lg font-bold text-amber-600 dark:text-amber-400">PERINGATAN TUGAS TERTUNDA</h3>
                <p class="text-sm font-medium text-amber-600 dark:text-amber-300">Ada <span class="text-xl font-black px-1">{{ $this->getPendingWeighingCount() }}</span> timbangan sapi yang belum dikerjakan.</p>
            </div>
        </div>
        @endif
    </div>
    @endif
</x-filament-widgets::widget>
