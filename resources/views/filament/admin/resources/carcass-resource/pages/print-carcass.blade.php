<x-filament-panels::page>
    <div class="print-container bg-white text-black" x-data x-init="$nextTick(() => { window.print(); })">
        <style>
            :root {
                --accent: #f0ad4e;
                --ink: #111;
                --muted: #666;
                --line: #e7e7e7;
            }

            .print-container {
                color: var(--ink);
                font-size: 13px;
                font-family: sans-serif;
            }

            .doc {
                max-width: 960px;
                margin: 24px auto 48px;
                padding: 0 16px;
            }

            .header {
                display: flex;
                align-items: center;
                gap: 16px;
                border-bottom: 2px solid var(--ink);
                padding-bottom: 12px;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .brand .name {
                font-size: 20px;
                font-weight: 700;
                letter-spacing: .3px;
            }

            .brand .tag {
                font-size: 12px;
                color: var(--muted);
            }

            .title {
                margin: 18px 0 8px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .title h1 {
                font-size: 18px;
                font-weight: 700;
                margin: 0;
                letter-spacing: .5px;
            }

            .meta {
                margin-top: 12px;
                display: grid;
                grid-template-columns: auto 1fr auto 1fr;
                column-gap: 16px;
                row-gap: 6px;
                align-items: center;
            }

            .meta dt {
                font-weight: 600;
                margin: 0;
            }

            .meta dd {
                margin: 0;
            }

            table.car-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 16px;
            }

            .car-table thead th {
                background: #fafafa;
                border: 1px solid var(--line);
                font-weight: 600;
                text-align: center;
                padding: 8px;
            }

            .car-table td {
                border: 1px solid var(--line);
                padding: 8px;
            }

            .car-table td.num {
                text-align: right;
                white-space: nowrap;
            }

            .car-table td.center {
                text-align: center;
            }

            .car-table tbody tr:nth-child(even) {
                background: #fcfcfc;
            }

            .totals {
                margin-top: 8px;
                display: flex;
                justify-content: flex-end;
            }

            .totals table {
                border-collapse: collapse;
            }

            .totals th,
            .totals td {
                padding: 6px 10px;
            }

            .totals th {
                text-align: right;
                color: var(--muted);
                font-weight: 600;
            }

            .totals td {
                text-align: right;
                min-width: 140px;
                border-bottom: 1px solid var(--line);
            }

            .note {
                margin-top: 12px;
            }

            .note .label {
                font-weight: 600;
                margin-bottom: 4px;
            }

            .signs {
                margin-top: 34px;
                display: flex;
                justify-content: flex-end;
            }

            .sign-card {
                width: 260px;
                text-align: center;
            }

            .sign-card .muted {
                margin-bottom: 56px;
                color: var(--muted);
            }

            .sign-line {
                border-top: 1px dashed var(--line);
                padding-top: 6px;
            }

            @media print {
                body * { visibility: hidden; }
                .print-container, .print-container * { visibility: visible; }
                .print-container { position: absolute; left: 0; top: 0; width: 100%; }
                .fi-topbar, .fi-sidebar { display: none !important; }
                .doc { margin: 0; padding: 0; }
                .car-table thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #fafafa !important; }
                .car-table tbody tr:nth-child(even) { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #fcfcfc !important; }
            }
        </style>

        <div class="doc">
            <!-- Header -->
            <div class="header">
                <div class="brand">
                    <div>
                        <div class="name">PT. SANTI WIJAYA MEAT</div>
                        <div class="tag">Committed to Meeting Your Need</div>
                    </div>
                </div>
            </div>

            <div class="title">
                <h1>Carcass Report</h1>
                <div class="text-gray-500">
                    Kill Date: {{ \Carbon\Carbon::parse($record->kill_date)->format('d-M-Y') }}
                </div>
            </div>

            <!-- Meta -->
            <dl class="meta">
                <dt>Supplier</dt>
                <dd>{{ optional(optional(optional($record->weighing)->receiving)->supplier)->name ?? '-' }}</dd>

                <dt>No Weighing</dt>
                <dd>{{ optional($record->weighing)->weighing_number ?? '-' }}</dd>

                <dt>Tgl Timbang</dt>
                <dd>{{ optional($record->weighing)->weighing_date ? \Carbon\Carbon::parse($record->weighing->weighing_date)->format('d-M-Y') : '-' }}</dd>

                <dt>Heads</dt>
                <dd>{{ number_format($record->items->count(), 0, ',', '.') }}</dd>
            </dl>


            <!-- Items -->
            <table class="car-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:90px;">Eartag</th>
                        <th style="width:80px;">Class</th>
                        <th style="width:110px;">Receive Wt (Kg)</th>
                        <th style="width:110px;">Carcase A (Kg)</th>
                        <th style="width:110px;">Carcase B (Kg)</th>
                        <th style="width:120px;">Total Carcase (Kg)</th>
                        <th style="width:90px;">Hides (Kg)</th>
                        <th style="width:90px;">Tail (Kg)</th>
                        <th style="width:90px;">Yield (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $tLive = 0; $tC1 = 0; $tC2 = 0; $tH = 0; $tT = 0;
                    @endphp
                    @forelse($record->items as $index => $item)
                        @php
                            $liveWeight = (float) optional($item->weighingItem)->actual_weight;
                            $c1 = (float) $item->carcass_1;
                            $c2 = (float) $item->carcass_2;
                            $h = (float) $item->hides;
                            $t = (float) $item->tail;
                            $totCarc = $c1 + $c2;
                            $yield = $liveWeight > 0 ? ($totCarc / $liveWeight * 100) : 0;

                            $tLive += $liveWeight;
                            $tC1 += $c1;
                            $tC2 += $c2;
                            $tH += $h;
                            $tT += $t;
                        @endphp
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td class="center">{{ optional($item->weighingItem)->eartag }}</td>
                            <td class="center">{{ optional(optional(optional($item->weighingItem)->receivingItem)->cattleClass)->name ?? '-' }}</td>
                            <td class="num">{{ number_format($liveWeight, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($c1, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($c2, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($totCarc, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($h, 2, ',', '.') }}</td>
                            <td class="num">{{ number_format($t, 2, ',', '.') }}</td>
                            <td class="num">
                                {{ $yield > 0 ? number_format($yield, 2, ',', '.') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="center text-gray-500">No details</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @php
                $totalCarcass = $tC1 + $tC2;
                $offal = $totalCarcass + $tT;
                $totalYield = $tLive > 0 ? ($totalCarcass / $tLive * 100) : 0;
            @endphp

            <!-- Totals -->
            <div class="totals">
                <table>
                    <tr>
                        <th>Total Receive</th>
                        <td>{{ number_format($tLive, 2, ',', '.') }} Kg</td>
                    </tr>
                    <tr>
                        <th>Offal</th>
                        <td>{{ number_format($offal, 2, ',', '.') }} Kg</td>
                    </tr>
                    <tr>
                        <th>Total Hides</th>
                        <td>{{ number_format($tH, 2, ',', '.') }} Kg</td>
                    </tr>
                    <tr>
                        <th>Total Tails</th>
                        <td>{{ number_format($tT, 2, ',', '.') }} Kg</td>
                    </tr>
                    <tr>
                        <th>Carcase Yield</th>
                        <td>{{ $tLive > 0 ? number_format($totalYield, 2, ',', '.') . ' %' : '-' }}</td>
                    </tr>
                </table>
            </div>

            @if(!empty($record->note))
                <div class="note">
                    <div class="label">Catatan</div>
                    <div>{!! nl2br(e($record->note)) !!}</div>
                </div>
            @endif

            <!-- Signature -->
            <div class="signs">
                <div class="sign-card">
                    <div class="text-gray-500 mb-14">Prepared by</div>
                    <div class="sign-line">{{ optional($record->creator)->name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
