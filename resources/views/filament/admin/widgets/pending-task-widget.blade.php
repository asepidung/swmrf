<x-filament-widgets::widget class="fi-wi-pending-task">
    @if($this->getPendingReceivingCount() > 0 || $this->getPendingWeighingCount() > 0 || $this->getPendingCarcassCount() > 0 || $this->getPendingMaterialRequestCount() > 0 || $this->getPendingMaterialFinanceCount() > 0 || $this->getPendingProductRequestCount() > 0 || $this->getPendingProductFinanceCount() > 0 || $this->getPendingRepackLockCount() > 0)
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

        @if($this->getPendingCarcassCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count draft timbangan yang belum dipotong (karkas).', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingCarcassCount().'</strong>']) !!}
            </p>
        </div>
        @endif

        @if($this->getPendingMaterialRequestCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request material yang belum di-review.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingMaterialRequestCount().'</strong>']) !!}
            </p>
        </div>
        @endif

        @if($this->getPendingMaterialFinanceCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request material yang belum di-approve.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingMaterialFinanceCount().'</strong>']) !!}
            </p>
        </div>
        @endif

        @if($this->getPendingProductRequestCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request beef yang belum di-review.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingProductRequestCount().'</strong>']) !!}
            </p>
        </div>
        @endif

        @if($this->getPendingProductFinanceCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request beef yang belum di-approve.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingProductFinanceCount().'</strong>']) !!}
            </p>
        </div>
        @endif

        @if($this->getPendingRepackLockCount() > 0)
        <div class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count repack yang belum dikunci.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingRepackLockCount().'</strong>']) !!}
            </p>
        </div>
        @endif
    </div>
    @endif
</x-filament-widgets::widget>
