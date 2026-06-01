<x-filament-widgets::widget class="fi-wi-pending-task">
    @if($this->getPendingReceivingCount() > 0 || $this->getPendingWeighingCount() > 0)
    <div class="space-y-2">
        @if($this->getPendingReceivingCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count penerimaan sapi yang belum dikerjakan.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingReceivingCount().'</strong>']) !!}
            </p>
        </div>
        @endif

        @if($this->getPendingWeighingCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count timbangan sapi yang belum dikerjakan.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingWeighingCount().'</strong>']) !!}
            </p>
        </div>
        @endif
    </div>
    @endif
</x-filament-widgets::widget>
