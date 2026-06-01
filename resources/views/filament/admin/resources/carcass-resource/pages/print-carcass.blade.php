<x-filament-panels::page>
    <div class="print-container bg-white p-8 text-black" x-data x-init="$nextTick(() => { window.print(); })">
        <style>
            @media print {
                body * { visibility: hidden; }
                .print-container, .print-container * { visibility: visible; }
                .print-container { position: absolute; left: 0; top: 0; width: 100%; }
                .fi-topbar, .fi-sidebar { display: none !important; }
            }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; color: #000; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; }
            .header-info { display: flex; justify-content: space-between; margin-bottom: 20px; color: #000; }
            h1 { font-size: 24px; font-weight: bold; margin-bottom: 20px; text-align: center; color: #000; }
        </style>

        <h1>Carcass Report</h1>

        <div class="header-info">
            <div>
                <p><strong>Carcass No:</strong> {{ $record->carcass_number }}</p>
                <p><strong>Weighing No:</strong> {{ optional($record->weighing)->weighing_number }}</p>
                <p><strong>PO No:</strong> {{ optional(optional(optional($record->weighing)->receiving)->purchaseCattle)->document_number }}</p>
            </div>
            <div>
                <p><strong>Kill Date:</strong> {{ \Carbon\Carbon::parse($record->kill_date)->format('d M Y') }}</p>
                <p><strong>Supplier:</strong> {{ optional(optional(optional($record->weighing)->receiving)->supplier)->name }}</p>
                <p><strong>Operator:</strong> {{ optional($record->creator)->name }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Eartag</th>
                    <th>Carcass 1 (Kg)</th>
                    <th>Carcass 2 (Kg)</th>
                    <th>Hides (Kg)</th>
                    <th>Tail (Kg)</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $tC1 = 0; $tC2 = 0; $tH = 0; $tT = 0;
                @endphp
                @foreach($record->items as $index => $item)
                    @php
                        $tC1 += $item->carcass_1;
                        $tC2 += $item->carcass_2;
                        $tH += $item->hides;
                        $tT += $item->tail;
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ optional($item->weighingItem)->eartag }}</td>
                        <td>{{ number_format($item->carcass_1, 2) }}</td>
                        <td>{{ number_format($item->carcass_2, 2) }}</td>
                        <td>{{ number_format($item->hides, 2) }}</td>
                        <td>{{ number_format($item->tail, 2) }}</td>
                        <td>{{ $item->notes }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="font-weight: bold; background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact;">
                    <td colspan="2" style="text-align: right;">Total</td>
                    <td>{{ number_format($tC1, 2) }}</td>
                    <td>{{ number_format($tC2, 2) }}</td>
                    <td>{{ number_format($tH, 2) }}</td>
                    <td>{{ number_format($tT, 2) }}</td>
                    <td></td>
                </tr>
                <tr style="font-weight: bold; background-color: #e5e7eb !important; -webkit-print-color-adjust: exact;">
                    <td colspan="2" style="text-align: right;">Total Offal (C1+C2+Tail)</td>
                    <td colspan="5">{{ number_format($tC1 + $tC2 + $tT, 2) }} Kg</td>
                </tr>
            </tfoot>
        </table>

        @if($record->note)
        <div style="margin-top: 20px; color: #000;">
            <p><strong>Note:</strong></p>
            <p>{{ $record->note }}</p>
        </div>
        @endif
    </div>
</x-filament-panels::page>
