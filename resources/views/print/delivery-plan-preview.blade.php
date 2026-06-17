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
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            -webkit-print-color-adjust: exact;
        }
        
        /* Top Navigation Bar */
        .top-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 16px;
        }
        .btn-close {
            display: inline-flex;
            align-items: center;
            background-color: #ef4444; /* Red color */
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
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
            text-align: center;
            color: #0f172a;
            margin-top: 10px;
            margin-bottom: 4px;
            font-size: 22px;
            font-weight: 700;
        }
        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        /* Desktop Table Layout */
        .desktop-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .desktop-table th, .desktop-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        .desktop-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid #e2e8f0;
        }
        .desktop-table td {
            font-size: 14px;
            color: #334155;
        }
        .desktop-table tbody tr:last-child td {
            border-bottom: none;
        }
        .desktop-table tbody tr:hover {
            background-color: #f8fafc;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Mobile Cards Layout (Hidden on Desktop) */
        .mobile-cards {
            display: none;
        }

        /* Print Specifics */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                background-color: #ffffff;
            }
        }

        /* Responsive Breakpoint for Phone / Mobile View */
        @media (max-width: 768px) {
            body {
                padding: 16px;
                background-color: #f1f5f9;
            }
            .top-bar {
                margin-bottom: 12px;
            }
            h2 {
                font-size: 18px;
                margin-top: 5px;
            }
            .subtitle {
                font-size: 12px;
                margin-bottom: 16px;
            }
            .desktop-table {
                display: none;
            }
            .mobile-cards {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .delivery-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 14px;
                box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
                overflow: hidden;
                border-left: 4px solid #3b82f6; /* Blue accent line */
            }
            .card-header {
                background: #f8fafc;
                padding: 12px 14px;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
            }
            .customer-name {
                font-weight: 700;
                font-size: 14px;
                color: #0f172a;
            }
            .delivery-date {
                font-size: 11px;
                color: #475569;
                background: #e2e8f0;
                padding: 2px 6px;
                border-radius: 9999px;
                white-space: nowrap;
                font-weight: 500;
            }
            .card-body {
                padding: 14px;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .info-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                font-size: 13px;
                gap: 12px;
            }
            .info-label {
                color: #64748b;
                font-weight: 500;
            }
            .info-value {
                color: #334155;
                text-align: right;
                flex-grow: 1;
            }
            .note-row {
                flex-direction: column;
                gap: 4px;
                border-top: 1px dashed #e2e8f0;
                padding-top: 8px;
                margin-top: 2px;
            }
            .note-row .info-label {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .note-row .info-value {
                text-align: left;
                width: 100%;
                color: #475569;
                font-style: italic;
                font-size: 12px;
            }
            .no-records {
                text-align: center;
                padding: 30px 16px;
                background: #ffffff;
                border-radius: 14px;
                border: 1px solid #e2e8f0;
                color: #64748b;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Action Toolbar -->
    <div class="top-bar no-print">
        <button onclick="handleClose('{{ route('filament.admin.resources.delivery-plans.index') }}')" class="btn-close">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 14px; height: 14px; margin-right: 6px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Tutup
        </button>
    </div>

    <h2>Plan Delivery Preview</h2>
    <div class="subtitle">Jadwal Kirim Besok ({{ \Carbon\Carbon::parse($tomorrow)->format('d M Y') }})</div>

    <!-- Desktop Table View -->
    <table class="desktop-table">
        <thead>
            <tr>
                <th class="text-center" width="5%">No</th>
                <th width="12%">Tgl Kirim</th>
                <th width="20%">Customer</th>
                <th class="text-center" width="8%">Total PO</th>
                <th class="text-right" width="10%">Qty (Kg)</th>
                <th width="15%">Driver</th>
                <th width="12%">Armada</th>
                <th class="text-center" width="10%">Jam Loading</th>
                <th width="18%">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $index => $record)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $record->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->format('d-m-Y') : '-' }}</td>
                <td>{{ optional($record->customer)->name ?? '-' }}</td>
                <td class="text-center">{{ $record->sales_orders_count }}</td>
                <td class="text-right">{{ number_format($record->total_qty) }}</td>
                <td>{{ $record->driver ?? '-' }}</td>
                <td>{{ $record->armada ?? '-' }}</td>
                <td class="text-center">{{ $record->load_time ? \Carbon\Carbon::parse($record->load_time)->format('H:i') : '-' }}</td>
                <td>{{ $record->notes ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="color: #64748b;">Tidak ada jadwal pengiriman besok.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Mobile Cards View -->
    <div class="mobile-cards">
        @forelse($records as $index => $record)
        <div class="delivery-card">
            <div class="card-header">
                <span class="customer-name">{{ optional($record->customer)->name ?? '-' }}</span>
                <span class="delivery-date">{{ $record->delivery_date ? \Carbon\Carbon::parse($record->delivery_date)->format('d-m-Y') : '-' }}</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Driver / Armada</span>
                    <span class="info-value"><strong>{{ $record->driver ?? '-' }}</strong> @if($record->armada) ({{ $record->armada }}) @endif</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jam Loading</span>
                    <span class="info-value">{{ $record->load_time ? \Carbon\Carbon::parse($record->load_time)->format('H:i') : '-' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">PO / Qty (Kg)</span>
                    <span class="info-value">{{ $record->sales_orders_count }} PO | <strong>{{ number_format($record->total_qty) }} Kg</strong></span>
                </div>
                @if($record->notes)
                <div class="info-row note-row">
                    <span class="info-label">Notes</span>
                    <span class="info-value">{{ $record->notes }}</span>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="no-records">
            Tidak ada jadwal pengiriman besok.
        </div>
        @endforelse
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
