<x-filament-panels::page>
    <div class="space-y-4">

        <div class="flex justify-between items-center bg-white p-4 rounded-lg shadow-sm dark:bg-gray-800">
            <div class="flex gap-2">
                <x-filament::button tag="a" href="{{ \App\Filament\Resources\TallySheetResource::getUrl('index') }}" color="gray" icon="heroicon-o-arrow-left">
                    Back To List
                </x-filament::button>
                <x-filament::button color="success" icon="heroicon-o-check-circle">
                    Approve
                </x-filament::button>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-primary-600 dark:text-primary-400">
                    {{ $record->salesOrder->customer->name ?? 'Unknown Customer' }}
                </h2>
                <p class="text-sm text-gray-500">SO: {{ $record->salesOrder->so_number }} | Tally: {{ $record->tally_number }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

            <div class="lg:col-span-7 bg-white rounded-lg shadow-sm dark:bg-gray-800 overflow-hidden">
                <div class="p-3 bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600 flex justify-between items-center">
                    <h3 class="font-bold text-gray-700 dark:text-gray-200">Riwayat Tally (History)</h3>
                </div>

                <div class="p-4">
                    <form wire:submit.prevent="submitBarcode" class="mb-4 flex items-center gap-3">
                        <input type="text"
                            wire:model.defer="barcode"
                            autofocus
                            placeholder="Scan Here..."
                            class="w-1/3 text-center font-bold text-lg border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                        <div wire:loading wire:target="submitBarcode" class="text-sm text-warning-500 font-bold">
                            Processing...
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead class="text-xs text-gray-800 uppercase bg-gray-200 dark:bg-gray-900 dark:text-gray-200 text-center border-b-2 border-gray-300 dark:border-gray-600">
                                <tr>
                                    <th class="px-2 py-3 font-bold">#</th>
                                    <th class="px-2 py-3 font-bold">Barcode</th>
                                    <th class="px-2 py-3 font-bold text-left">Product</th>
                                    <th class="px-2 py-3 font-bold">Grade</th>
                                    <th class="px-2 py-3 font-bold">Weight</th>
                                    <th class="px-2 py-3 font-bold">Pcs</th>
                                    <th class="px-2 py-3 font-bold">POD</th>
                                    <th class="px-2 py-3 font-bold">Origin</th>
                                    <th class="px-2 py-3 font-bold">pH</th>
                                    <th class="px-2 py-3 font-bold text-red-600">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->scannedItems as $index => $item)
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 text-center">
                                    <td class="px-2 py-2">{{ $this->scannedItems->firstItem() + $index }}</td>
                                    <td class="px-2 py-2 font-mono">{{ $item->barcode }}</td>
                                    <td class="px-2 py-2 text-left">{{ $item->product->name ?? '-' }}</td>
                                    <td class="px-2 py-2">{{ $item->grade->name ?? '-' }}</td>
                                    <td class="px-2 py-2 font-bold">{{ number_format($item->actual_weight, 2) }}</td>
                                    <td class="px-2 py-2">{{ $item->qty_pcs ?? '-' }}</td>
                                    <td class="px-2 py-2">{{ $item->pack_date ? \Carbon\Carbon::parse($item->pack_date)->format('d-M-y') : '-' }}</td>
                                    <td class="px-2 py-2">{{ $item->origin ?? '-' }}</td>
                                    <td class="px-2 py-2">{{ $item->ph_level ?? '-' }}</td>
                                    <td class="px-2 py-2">
                                        <button
                                            type="button"
                                            wire:click="hapusItem({{ $item->id }}, '{{ $item->barcode }}')"
                                            wire:confirm="Yakin ingin menghapus barang ini dan mengembalikannya ke stock?"
                                            class="text-red-500 hover:text-red-700"
                                            title="Hapus Barcode">
                                            <x-heroicon-o-x-circle class="w-5 h-5 mx-auto" />
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">Belum ada barang yang di-scan.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-8 mb-4 px-4">
                        {{ $this->scannedItems->links() }}
                    </div>

                </div>
            </div>

            <div class="lg:col-span-5 bg-white rounded-lg shadow-sm dark:bg-gray-800 overflow-hidden">
                <div class="p-3 bg-gray-50 dark:bg-gray-700 border-b dark:border-gray-600 flex justify-between items-center">
                    <h3 class="font-bold text-gray-700 dark:text-gray-200">Summary Penyiapan</h3>
                </div>

                <div class="overflow-x-auto p-4">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="text-xs text-gray-800 uppercase bg-gray-200 dark:bg-gray-900 dark:text-gray-200 text-center border-b-2 border-gray-300 dark:border-gray-600">
                            <tr>
                                <th class="px-2 py-3 font-bold text-left">Produk</th>
                                <th class="px-2 py-3 font-bold">Order Qty</th>
                                <th class="px-2 py-3 font-bold">Tally Qty</th>
                                <th class="px-2 py-3 font-bold">Box</th>
                                <th class="px-2 py-3 font-bold">Balance</th>
                                <th class="px-2 py-3 font-bold text-left">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($record->salesOrder && method_exists($record->salesOrder, 'items'))
                            @php
                            $totalOrder = 0;
                            $totalTally = 0;
                            $totalBox = 0;
                            @endphp

                            @foreach($record->salesOrder->items as $soDetail)
                            @php
                            $scannedWeight = $record->items()->where('product_id', $soDetail->product_id)->sum('actual_weight');
                            $scannedBox = $record->items()->where('product_id', $soDetail->product_id)->count();
                            $balance = $scannedWeight - $soDetail->weight;

                            $totalOrder += $soDetail->weight;
                            $totalTally += $scannedWeight;
                            $totalBox += $scannedBox;
                            @endphp
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 text-center hover:bg-gray-50">
                                <td class="px-2 py-2 text-left font-medium">{{ $soDetail->product->name ?? '-' }}</td>
                                <td class="px-2 py-2">{{ number_format($soDetail->weight, 2) }}</td>
                                <td class="px-2 py-2 text-primary-600 font-semibold">{{ number_format($scannedWeight, 2) }}</td>
                                <td class="px-2 py-2">{{ $scannedBox }}</td>
                                <td class="px-2 py-2 font-bold {{ $balance < 0 ? 'text-red-500' : 'text-green-500' }}">
                                    {{ number_format($balance, 2) }}
                                </td>
                                <td class="px-2 py-2 text-left text-xs text-gray-500">{{ $soDetail->notes ?? '-' }}</td>
                            </tr>
                            @endforeach

                            <tr class="bg-gray-100 dark:bg-gray-700 font-bold text-center border-t-2 border-gray-300 dark:border-gray-600">
                                <td class="px-2 py-3 text-right">TOTAL</td>
                                <td class="px-2 py-3">{{ number_format($totalOrder, 2) }}</td>
                                <td class="px-2 py-3 text-primary-600">{{ number_format($totalTally, 2) }}</td>
                                <td class="px-2 py-3">{{ $totalBox }}</td>
                                <td class="px-2 py-3 {{ ($totalTally - $totalOrder) < 0 ? 'text-red-500' : 'text-green-500' }}">
                                    {{ number_format($totalTally - $totalOrder, 2) }}
                                </td>
                                <td class="px-2 py-3"></td>
                            </tr>
                            @else
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-gray-500 text-xs">Detail item Sales Order tidak ditemukan atau relasi belum sesuai.</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</x-filament-panels::page>