<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Plan Delivery</title>
    <!-- Modern Google Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            color: #000000;
            margin: 0;
            padding: 8px; /* Dikurangi drastis untuk layar HP */
            -webkit-print-color-adjust: exact;
        }
        
        /* Bottom Navigation Bar */
        .bottom-bar {
            display: flex;
            justify-content: center;
            margin-top: 24px;
        }
        .btn-close {
            display: inline-flex;
            align-items: center;
            background-color: #ef4444; /* Red color */
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s;
            text-decoration: none;
        }
        .btn-close:hover {
            background-color: #dc2626;
        }

        h2 {
            text-align: left;
            color: #000000;
            margin-top: 0;
            margin-bottom: 12px;
            font-size: 17px;
            font-weight: 700;
        }
        
        /* Pivot Table Layout */
        .pivot-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            font-size: 14px; /* Sedikit dibesarkan agar jelas di layar HP */
        }
        .pivot-table th, .pivot-table td {
            padding: 6px 4px; /* Padding sel direduksi */
            text-align: left;
        }
        .pivot-table th {
            background-color: #ffffff;
            color: #000000;
            font-weight: 700;
            border-bottom: 1px solid #000000;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        /* Group Header */
        .group-header td {
            font-weight: 700;
            padding-top: 12px;
        }

        /* Item Row */
        .item-row td {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
        .item-row .row-label {
            padding-left: 12px;
        }

        /* Group Total */
        .group-total td {
            font-weight: 700;
            border-top: 1px solid #000000;
            border-bottom: 3px double #000000; /* Double line separator as requested */
            padding-top: 6px;
            padding-bottom: 6px;
        }

        /* Grand Total */
        .pivot-table tfoot th {
            background-color: #ffffff;
            color: #000000;
            font-weight: 700;
            border-bottom: 1px solid #000000;
        }

        .icon {
            font-family: monospace;
            margin-right: 4px;
            color: #000000;
        }

        /* Print Specifics */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>


    @php
        // Grouping logic (Driver | Armada | Jam)
        $groupedRecords = $records->groupBy(function($r) {
            $driverName = $r->driver ? $r->driver->name : 'N/A';
            $vehicleLabel = $r->vehicle ? $r->vehicle->vehicle_type . ' (' . $r->vehicle->police_number . ')' : 'N/A';
            $loadTime = $r->load_time ? \Carbon\Carbon::parse($r->load_time)->format('H:i') : 'N/A';
            return $driverName . ' | ' . $vehicleLabel . ' | ' . $loadTime;
        });
        $grandTotalPO = $records->sum('sales_orders_count');
        $grandTotalQty = $records->sum('total_qty');
    @endphp

    <h2>Plan Delivery {{ \Carbon\Carbon::parse($targetDate)->translatedFormat('d M Y') }}</h2>

    <table class="pivot-table">
        <thead>
            <tr>
                <th width="70%"></th>
                <th class="text-center" width="10%">PO</th>
                <th class="text-right" width="20%">QTY</th>
            </tr>
        </thead>
        <tbody>
            @forelse($groupedRecords as $groupLabel => $groupItems)
                @php
                    $groupTotalPO = $groupItems->sum('sales_orders_count');
                    $groupTotalQty = $groupItems->sum('total_qty');
                @endphp
                
                <!-- Group Header Row -->
                <tr class="group-header">
                    <td colspan="3">{{ $groupLabel }}</td>
                </tr>
                
                <!-- Items Rows -->
                @foreach($groupItems as $record)
                <tr class="item-row">
                    <td class="row-label">
                        <span class="icon">[-]</span> {{ optional($record->customer)->name ?? 'Tanpa Customer' }}
                        @if($record->notes)
                            <div style="font-size: 11px; color: #6b7280; font-style: italic; margin-top: 2px; padding-left: 18px;">
                                * {{ $record->notes }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center" style="vertical-align: top;">{{ $record->sales_orders_count }}</td>
                    <td class="text-right" style="vertical-align: top;">{{ number_format($record->total_qty) }}</td>
                </tr>
                @endforeach
                
                <!-- Group Total Row -->
                <tr class="group-total">
                    <td class="text-right"><strong>TOTAL</strong></td>
                    <td class="text-center"><strong>{{ $groupTotalPO }}</strong></td>
                    <td class="text-right"><strong>{{ number_format($groupTotalQty) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center" style="padding-top: 20px;">Tidak ada jadwal pengiriman.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th>Grand Total</th>
                <th class="text-center">{{ $grandTotalPO }}</th>
                <th class="text-right">{{ number_format($grandTotalQty) }}</th>
            </tr>
        </tfoot>
    </table>

    <!-- Bottom Action Toolbar -->
    <div class="bottom-bar no-print" style="gap: 12px; padding-bottom: 24px;">
        <button onclick="window.print()" style="background-color: #3b82f6; color: #ffffff; border: none; border-radius: 6px; padding: 10px 24px; font-size: 15px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.1);">
            🖨️ Cetak / PDF
        </button>
        <button onclick="handleClose('{{ route('filament.admin.resources.delivery-plans.index') }}')" class="btn-close" style="padding: 10px 24px; font-size: 15px;">
            Tutup
        </button>
    </div>

    <!-- Smart Close Window / Redirect Script -->
    <script>
        function handleClose(fallbackUrl) {
            window.close();
            setTimeout(function() {
                window.location.href = fallbackUrl;
            }, 100);
        }
    </script>
</body>
</html>
