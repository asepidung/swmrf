<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Read-only Header Information Card -->
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-2 text-sm">
                <div>
                    <table class="w-full text-left border-none table-auto">
                        <tbody>
                            <tr class="h-8">
                                <td class="w-32 text-gray-500 dark:text-gray-400 font-medium py-1">{{ __('Customer') }}</td>
                                <td class="w-4 text-gray-400 font-medium py-1">:</td>
                                <td class="font-bold text-gray-900 dark:text-white py-1">{{ $record->salesOrder?->customer?->name }}</td>
                            </tr>
                            <tr class="h-8">
                                <td class="w-32 text-gray-500 dark:text-gray-400 font-medium py-1">{{ __('PO Number') }}</td>
                                <td class="w-4 text-gray-400 font-medium py-1">:</td>
                                <td class="font-bold text-gray-900 dark:text-white py-1">{{ $record->salesOrder?->po_number }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <table class="w-full text-left border-none table-auto">
                        <tbody>
                            <tr class="h-8">
                                <td class="w-32 text-gray-500 dark:text-gray-400 font-medium py-1">{{ __('Delivery Date') }}</td>
                                <td class="w-4 text-gray-400 font-medium py-1">:</td>
                                <td class="font-bold text-gray-900 dark:text-white py-1">
                                    {{ $record->salesOrder?->delivery_date ? \Carbon\Carbon::parse($record->salesOrder->delivery_date)->format('d-M-Y') : '' }}
                                </td>
                            </tr>
                            <tr class="h-8">
                                <td class="w-32 text-gray-500 dark:text-gray-400 font-medium py-1">{{ __('SO Number') }}</td>
                                <td class="w-4 text-gray-400 font-medium py-1">:</td>
                                <td class="font-bold text-gray-900 dark:text-white py-1">{{ $record->salesOrder?->so_number }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Weight Grid Table -->
        @php
            $viewData = $this->getViewData();
            $productData = $viewData['productData'];
            $totalBox = $viewData['totalBox'];
            $totalQty = $viewData['totalQty'];
        @endphp
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <table class="w-full text-left border-collapse text-sm border border-gray-300 dark:border-gray-700">
                <thead>
                    <tr class="bg-gray-900 text-white dark:bg-gray-950 dark:text-gray-100 font-bold">
                        <th class="border border-gray-300 dark:border-gray-700 py-3 px-4 text-center text-xs font-bold uppercase tracking-wider">{{ __('Product') }}</th>
                        @for ($i = 1; $i <= 10; $i++)
                            <th class="border border-gray-300 dark:border-gray-700 py-3 px-2 text-center text-xs font-bold uppercase tracking-wider w-16">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</th>
                        @endfor
                        <th class="border border-gray-300 dark:border-gray-700 py-3 px-4 text-center text-xs font-bold uppercase tracking-wider">{{ __('TOTAL') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($productData as $productName => $data)
                        @php
                            $weights = $data['weights'];
                            $rowsNeeded = ceil(count($weights) / 10);
                        @endphp
                        @for ($rowIndex = 0; $rowIndex < $rowsNeeded; $rowIndex++)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/10">
                                <td class="border border-gray-300 dark:border-gray-700 px-4 py-3 font-semibold text-gray-900 dark:text-white uppercase text-left">
                                    @if ($rowIndex === 0)
                                        {{ $productName }}
                                    @endif
                                </td>
                                @for ($i = 0; $i < 10; $i++)
                                    @php
                                        $weightIndex = ($rowIndex * 10) + $i;
                                    @endphp
                                    <td class="border border-gray-300 dark:border-gray-700 px-2 py-3 text-center text-gray-700 dark:text-gray-300 w-16">
                                        @if (isset($weights[$weightIndex]))
                                            {{ number_format($weights[$weightIndex], 2) }}
                                        @endif
                                    </td>
                                @endfor
                                <td class="border border-gray-300 dark:border-gray-700 px-4 py-3 text-right font-bold text-gray-900 dark:text-white">
                                    @if ($rowIndex == $rowsNeeded - 1)
                                        {{ number_format($data['total'], 2) }}
                                    @endif
                                </td>
                            </tr>
                        @endfor
                    @endforeach
                    <tr class="font-bold text-gray-900 dark:text-white">
                        <td colspan="8" class="border border-gray-300 dark:border-gray-700 py-3"></td>
                        <td class="border border-gray-300 dark:border-gray-700 px-4 py-3 text-center font-bold bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            {{ __('Box') }}
                        </td>
                        <td class="border border-gray-300 dark:border-gray-700 px-4 py-3 text-center font-bold text-gray-900 dark:text-white bg-white dark:bg-gray-900">
                            {{ $totalBox }}
                        </td>
                        <td class="border border-gray-300 dark:border-gray-700 px-4 py-3 text-center font-bold bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            {{ __('Kg') }}
                        </td>
                        <td class="border border-gray-300 dark:border-gray-700 px-4 py-3 text-right font-bold text-gray-900 dark:text-white bg-white dark:bg-gray-900">
                            {{ number_format($totalQty, 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
