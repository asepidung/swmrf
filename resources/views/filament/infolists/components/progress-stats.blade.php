@php
    $record = $getRecord();
    
    $matched = $record->items()->where('status', 'MATCHED')->get();
    $missing = $record->items()->where('status', 'MISSING')->get();
    $unexpected = $record->items()->where('status', 'UNEXPECTED')->get();
    
    $stats = [
        [
            'label' => __('Matched (Scanned)'),
            'items' => $matched->count(),
            'weight' => number_format($matched->sum('weight'), 2),
            'pcs' => number_format($matched->sum('qty_pcs')),
            'color' => 'success',
            'bg' => 'bg-success-50 dark:bg-success-900/20',
            'border' => 'border-success-200 dark:border-success-800',
            'text' => 'text-success-700 dark:text-success-400',
            'icon' => 'heroicon-o-check-circle'
        ],
        [
            'label' => __('Missing (Waiting)'),
            'items' => $missing->count(),
            'weight' => number_format($missing->sum('weight'), 2),
            'pcs' => number_format($missing->sum('qty_pcs')),
            'color' => 'danger',
            'bg' => 'bg-danger-50 dark:bg-danger-900/20',
            'border' => 'border-danger-200 dark:border-danger-800',
            'text' => 'text-danger-700 dark:text-danger-400',
            'icon' => 'heroicon-o-x-circle'
        ],
        [
            'label' => __('Unexpected (Found)'),
            'items' => $unexpected->count(),
            'weight' => number_format($unexpected->sum('weight'), 2),
            'pcs' => number_format($unexpected->sum('qty_pcs')),
            'color' => 'warning',
            'bg' => 'bg-warning-50 dark:bg-warning-900/20',
            'border' => 'border-warning-200 dark:border-warning-800',
            'text' => 'text-warning-700 dark:text-warning-400',
            'icon' => 'heroicon-o-exclamation-circle'
        ]
    ];
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full pt-4">
    @foreach($stats as $stat)
        <div class="rounded-xl border {{ $stat['border'] }} {{ $stat['bg'] }} p-6 shadow-sm flex flex-col justify-between transition hover:shadow-md relative overflow-hidden">
            <!-- Decorative accent line on top -->
            <div class="absolute top-0 left-0 w-full h-1 bg-{{ $stat['color'] }}-500"></div>
            
            <div class="flex items-center gap-3 mb-6 relative z-10">
                <div class="rounded-full bg-white dark:bg-gray-800 p-2 shadow-sm">
                    <x-dynamic-component :component="$stat['icon']" class="w-6 h-6 {{ $stat['text'] }}" />
                </div>
                <h3 class="text-lg font-bold {{ $stat['text'] }}">{{ $stat['label'] }}</h3>
            </div>
            
            <div class="grid grid-cols-3 gap-4 relative z-10">
                <div class="flex flex-col">
                    <span class="text-[0.7rem] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Items</span>
                    <span class="text-2xl font-black {{ $stat['text'] }}">{{ $stat['items'] }}</span>
                </div>
                <div class="flex flex-col border-l border-white/50 dark:border-gray-700/50 pl-4">
                    <span class="text-[0.7rem] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Weight</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-black {{ $stat['text'] }}">{{ $stat['weight'] }}</span>
                        <span class="text-xs font-bold {{ $stat['text'] }} opacity-75">Kg</span>
                    </div>
                </div>
                <div class="flex flex-col border-l border-white/50 dark:border-gray-700/50 pl-4">
                    <span class="text-[0.7rem] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-1">Qty</span>
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-black {{ $stat['text'] }}">{{ $stat['pcs'] }}</span>
                        <span class="text-xs font-bold {{ $stat['text'] }} opacity-75">Pcs</span>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
