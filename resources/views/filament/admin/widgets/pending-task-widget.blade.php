<x-filament-widgets::widget class="fi-wi-pending-task">
    <style>
        .fi-wi-pending-task a {
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease !important;
        }
        .fi-wi-pending-task a:hover {
            transform: scale(1.01) !important;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1) !important;
            background-color: #f9fafb !important;
        }
        .dark .fi-wi-pending-task a:hover {
            background-color: rgba(255, 255, 255, 0.05) !important;
        }
    </style>
    @if($this->getPendingReceivingCount() > 0 || $this->getPendingWeighingCount() > 0 || $this->getPendingCarcassCount() > 0 || $this->getPendingMaterialRequestCount() > 0 || $this->getPendingMaterialFinanceCount() > 0 || $this->getPendingProductRequestCount() > 0 || $this->getPendingProductFinanceCount() > 0 || $this->getPendingRepackLockCount() > 0 || $this->getPendingTallyCount() > 0 || $this->getPendingDeliveryPlanCount() > 0 || $this->getPendingGrMaterialCount() > 0 || $this->getPendingGrProductCount() > 0 || $this->getPendingBoningLockCount() > 0 || $this->getPendingDeliveryOrderCount() > 0 || $this->getPendingDeliveryReceiptCount() > 0 || $this->getPendingInvoiceExchangeCount() > 0 || $this->getPendingMutationCount() > 0 || $this->getPendingBeefStockTakeCount() > 0 || $this->getPendingMaterialStockTakeCount() > 0)
    <div class="space-y-2">
        @if($this->getPendingBeefStockTakeCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\StockTakeResource::getUrl('index') }}" class="p-4 rounded-lg shadow-md border-2 border-red-500 bg-red-50 dark:bg-red-900/30 flex items-center gap-x-4 hover:bg-red-100 dark:hover:bg-red-900/50 transition duration-200 block animate-pulse">
            <span class="text-red-600 dark:text-red-400">
                <x-filament::icon icon="heroicon-s-exclamation-circle" class="w-8 h-8" />
            </span>
            <div>
                <p class="text-base font-bold text-red-700 dark:text-red-400">
                    SEDANG DILAKUKAN STOCK OPNAME DAGING!
                </p>
                <p class="text-sm font-medium text-red-600 dark:text-red-300">
                    Beberapa transaksi tidak bisa dilakukan (terkunci) selama proses Stock Opname belum selesai.
                </p>
            </div>
        </a>
        @endif

        @if($this->getPendingMaterialStockTakeCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\MaterialStockTakeResource::getUrl('index') }}" class="p-4 rounded-lg shadow-md border-2 border-red-500 bg-red-50 dark:bg-red-900/30 flex items-center gap-x-4 hover:bg-red-100 dark:hover:bg-red-900/50 transition duration-200 block animate-pulse">
            <span class="text-red-600 dark:text-red-400">
                <x-filament::icon icon="heroicon-s-exclamation-circle" class="w-8 h-8" />
            </span>
            <div>
                <p class="text-base font-bold text-red-700 dark:text-red-400">
                    SEDANG DILAKUKAN STOCK OPNAME MATERIAL!
                </p>
                <p class="text-sm font-medium text-red-600 dark:text-red-300">
                    Beberapa transaksi tidak bisa dilakukan (terkunci) selama proses Stock Opname belum selesai.
                </p>
            </div>
        </a>
        @endif

        @if($this->getPendingReceivingCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\CattleReceivingResource::getUrl('draft') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count penerimaan sapi yang belum dikerjakan.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingReceivingCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingWeighingCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\CattleWeighingResource::getUrl('draft') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count timbangan sapi yang belum dikerjakan.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingWeighingCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingCarcassCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\CarcassResource::getUrl('draft') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count draft timbangan yang belum dipotong (karkas).', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingCarcassCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingMaterialRequestCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request material yang belum di-review.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingMaterialRequestCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingMaterialFinanceCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\MaterialRequisitionResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request material yang belum di-approve.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingMaterialFinanceCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingProductRequestCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request beef yang belum di-review.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingProductRequestCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingProductFinanceCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\ProductRequisitionResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count request beef yang belum di-approve.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingProductFinanceCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingRepackLockCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\RepackResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count repack yang belum dikunci.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingRepackLockCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingTallyCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\TallyResource::getUrl('draft') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count Sales Order yang belum dibuatkan Tally.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingTallyCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingDeliveryPlanCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\DeliveryPlanResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count rencana pengiriman besok yang belum ditentukan driver/armadanya.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingDeliveryPlanCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingGrMaterialCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\GoodsReceiptMaterialResource::getUrl('drafts') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count PO Material baru yang siap diterima/dibuatkan GRM.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingGrMaterialCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingGrProductCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\GoodsReceiptProductResource::getUrl('drafts') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count PO Beef baru yang siap diterima/dibuatkan GRB.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingGrProductCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingBoningLockCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\BoningResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count boning yang belum dikunci.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingBoningLockCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingDeliveryOrderCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\DeliveryOrderResource::getUrl('draft') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count Tally yang siap dibuatkan DO.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingDeliveryOrderCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingDeliveryReceiptCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\DeliveryOrderResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count Delivery Order status Ready yang siap dilakukan pemeriksaan kiriman.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingDeliveryReceiptCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingInvoiceExchangeCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\InvoiceResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count invoice yang belum di-tukar faktur.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingInvoiceExchangeCount().'</strong>']) !!}
            </p>
        </a>
        @endif

        @if($this->getPendingMutationCount() > 0)
        <a href="{{ \App\Filament\Admin\Resources\MutationResource::getUrl('index') }}" class="p-3 rounded-lg shadow-sm border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex items-center gap-x-3 hover:bg-gray-55 dark:hover:bg-white/5 transition duration-200 block">
            <span style="color: #f59e0b !important;">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {!! __('Ada :count mutasi yang belum diterima.', ['count' => '<strong style="color: #f59e0b !important;">'.$this->getPendingMutationCount().'</strong>']) !!}
            </p>
        </a>
        @endif
    </div>
    @endif
</x-filament-widgets::widget>
