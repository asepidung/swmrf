<x-filament-widgets::widget class="fi-wi-pending-task">
    @if($this->getPendingReceivingCount() > 0 || $this->getPendingWeighingCount() > 0)
    <div class="space-y-4">
        @if($this->getPendingReceivingCount() > 0)
        <x-filament::section class="bg-warning-50 ring-warning-200 dark:bg-warning-900/30 dark:ring-warning-900">
            <div class="flex items-center gap-x-3 text-warning-600 dark:text-warning-400">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6" />
                <p>Ada <strong>{{ $this->getPendingReceivingCount() }}</strong> penerimaan sapi yang belum dikerjakan.</p>
            </div>
        </x-filament::section>
        @endif

        @if($this->getPendingWeighingCount() > 0)
        <x-filament::section class="bg-warning-50 ring-warning-200 dark:bg-warning-900/30 dark:ring-warning-900">
            <div class="flex items-center gap-x-3 text-warning-600 dark:text-warning-400">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-6 h-6" />
                <p>Ada <strong>{{ $this->getPendingWeighingCount() }}</strong> timbangan sapi yang belum dikerjakan.</p>
            </div>
        </x-filament::section>
        @endif
    </div>
    @endif
</x-filament-widgets::widget>
