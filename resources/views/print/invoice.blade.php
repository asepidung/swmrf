<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $record->customer->name ?? 'Customer' }} - {{ $record->invoice_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Cambria', Georgia, serif;
            font-size: 13px;
            color: #333;
        }

        .page-container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            margin-top: 30px;
            margin-bottom: 50px;
            position: relative;
        }

        .text-underline {
            text-decoration: underline;
        }

        /* Header Styling */
        .header-section {
            display: flex;
            align-items: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo-box {
            width: 75px;
            margin-right: 20px;
        }

        .logo-box img {
            width: 100%;
            height: auto;
        }

        .company-info {
            flex-grow: 1;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #111;
            letter-spacing: 0.5px;
        }

        .company-address {
            font-size: 10px;
            color: #555;
            margin-top: 2px;
            line-height: 1.4;
        }

        .doc-title-box {
            text-align: right;
        }

        .doc-title-box h2 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 1px;
        }

        .doc-meta {
            font-size: 12px;
            color: #111;
            margin-top: 3px;
            font-weight: bold;
        }

        /* Floating Panel Controls */
        .floating-controls {
            position: fixed;
            top: 50%;
            right: 30px;
            transform: translateY(-50%);
            z-index: 1050;
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 150px;
        }

        .floating-controls .btn {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-weight: bold;
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .floating-controls .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        /* Table Styling */
        .table-collapse {
            border-collapse: collapse;
            width: 100%;
            font-size: 12px;
        }

        .table-collapse th, .table-collapse td {
            border: 1px solid #333;
            padding: 6px;
            vertical-align: middle;
        }

        .table-collapse th {
            background-color: #e9ecef;
        }

        .says-box {
            background-color: #e9ecef;
            border: 1px solid #ccc;
            padding: 8px 12px;
            font-weight: bold;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }
            .floating-controls {
                position: static;
                transform: none;
                flex-direction: row;
                width: 100%;
                justify-content: center;
                margin-top: 20px;
            }
            .company-name { font-size: 14px; }
            .doc-title-box h2 { font-size: 18px; }
            .table-collapse { font-size: 10px; }
        }

        @media print {
            .floating-controls {
                display: none !important;
            }
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }

            .page-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                border-radius: 0;
            }
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(220, 53, 69, 0.15);
            font-weight: bold;
            z-index: 9999;
            pointer-events: none;
            white-space: nowrap;
            user-select: none;
        }

        .table-collapse th, .table-collapse td {
            border: 1px solid #000 !important;
            padding: 5px 8px;
        }
        
        .table-collapse th {
            background-color: #e9ecef;
        }

        .says-box {
            background-color: #e9ecef;
            border: 1px solid #ccc;
            padding: 8px 12px;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>
<body>

    @if($record->trashed())
        <div class="watermark">DELETED</div>
    @endif

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 page-container">
                
                <!-- Company Header -->
                <div class="header-section">
                    <div class="logo-box">
                        <img src="{{ asset('img/light.png') }}" alt="LOGO">
                    </div>
                    <div class="company-info">
                        <div class="company-name">PT. SANTI WIJAYA MEAT</div>
                        <div class="company-address">
                            PERUM ASABRI RT 001/RW 005, Desa Sukasirna, Kec. Jonggol,<br>
                            Kab. Bogor, Jawa Barat, 16830 | Phone: 021-89935103
                        </div>
                    </div>
                    <div class="doc-title-box">
                        <h2>INVOICE</h2>
                        <div class="doc-meta">
                            {{ $record->invoice_number }}
                        </div>
                    </div>
                </div>

                <!-- Invoice Details -->
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td style="width: 120px;" class="text-muted">Do Number</td>
                                <td style="width: 15px;" class="text-muted">:</td>
                                <th>{{ $record->delivery_order_number }}</th>
                            </tr>
                            <tr>
                                <td class="text-muted">Delivery Date</td>
                                <td class="text-muted">:</td>
                                <th>{{ $record->invoice_date ? $record->invoice_date->format('d-M-Y') : '-' }}</th>
                            </tr>
                            <tr>
                                <td class="text-muted">Terms</td>
                                <td class="text-muted">:</td>
                                <th>{{ $record->term_of_payment }} Days</th>
                            </tr>
                            <tr>
                                <td class="text-muted">Duedate</td>
                                <td class="text-muted">:</td>
                                <th>
                                    @php
                                        $tukarfaktur = $record->customer->invoice_exchange ?? false;
                                        $tgltf = $record->invoice_exchange_date ?? null;
                                    @endphp
                                    @if ($tukarfaktur && empty($tgltf) && $record->status === 'Belum TF')
                                        <span class="text-danger font-weight-bold">BTF (Belum Tukar Faktur)</span>
                                    @else
                                        {{ $record->due_date ? $record->due_date->format('d-M-Y') : '-' }}
                                    @endif
                                </th>
                            </tr>
                            <tr>
                                <td class="text-muted">Sales Ref</td>
                                <td class="text-muted">:</td>
                                <th>Muryani</th>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted">Cust PO</td>
                                <td class="text-muted">:</td>
                                <th>{{ $record->po_number ?? '-' }}</th>
                            </tr>
                            <tr>
                                <td style="width: 120px;" class="text-muted">Invoice Date</td>
                                <td style="width: 15px;" class="text-muted">:</td>
                                <th>{{ $record->invoice_date ? $record->invoice_date->format('d-M-Y') : '-' }}</th>
                            </tr>
                            <tr>
                                <td class="text-muted">Bill To</td>
                                <td class="text-muted">:</td>
                                <th>{{ $record->customer->name ?? '-' }}</th>
                            </tr>
                            <tr>
                                <td valign="top" class="text-muted">Address</td>
                                <td valign="top" class="text-muted">:</td>
                                <td valign="top" align="justify" style="font-weight: bold;">{{ $record->customer->address ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-collapse mt-3">
                        <thead class="text-center">
                        <tr>
                            <th style="width: 5%">#</th>
                            <th style="width: 33%">Prod Descriptions</th>
                            <th style="width: 10%">Weight</th>
                            <th style="width: 12%">Price</th>
                            <th style="width: 7%">Disc %</th>
                            <th style="width: 11%">Disc Rp</th>
                            <th style="width: 22%">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($record->items as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ $item->product->name ?? '-' }}</td>
                            <td class="text-right">{{ number_format($item->weight, 2, ',', '.') }} Kg</td>
                            <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                            <td class="text-center">{{ $item->discount_percent }}%</td>
                            <td class="text-right">Rp {{ number_format($item->discount_rp, 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($item->amount, 2, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        
                        @if($record->additionalCharges && count($record->additionalCharges) > 0)
                            @foreach($record->additionalCharges as $charge)
                            <tr>
                                <td></td>
                                <td>{{ $charge['name'] ?? '-' }}</td>
                                <td class="text-right">{{ $charge['qty'] ?? 1 }}</td>
                                <td class="text-right">Rp {{ number_format($charge['price'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $charge['discount_percent'] ?? 0 }}%</td>
                                <td class="text-right">Rp {{ number_format($charge['discount_rp'] ?? 0, 0, ',', '.') }}</td>
                                <td class="text-right">Rp {{ number_format($charge['amount'] ?? 0, 2, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">{{ number_format($record->total_weight, 2, ',', '.') }} Kg</th>
                            <td colspan="3" class="text-right font-weight-bold">Grand Total :</td>
                            <th class="text-right">Rp {{ number_format($record->subtotal, 2, ',', '.') }}</th>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-right border-0 font-weight-bold">Down Payment :</td>
                            <th class="text-right">Rp {{ number_format($record->down_payment, 2, ',', '.') }}</th>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-right border-0 font-weight-bold">Balance :</td>
                            <th class="text-right">Rp {{ number_format($record->balance, 2, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
                </div>

                <!-- Says Box -->
                <div class="says-box mb-3">
                    Says : <i>{{ \App\Helpers\Terbilang::convertDecimal($record->balance) }}</i>
                </div>

                @if($record->note)
                <div class="mb-4">
                    <strong>Note :</strong><br>
                    <span class="text-muted"><i>{{ $record->note }}</i></span>
                </div>
                @endif

                <!-- Payment Methods & Finance -->
                <div class="mt-4">
                    <h5 class="font-weight-bold" style="font-size: 14px; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Payment Methods</h5>
                    <div class="row">
                        <div class="col-8">
                            @if(strpos(strtoupper($record->customer->name ?? ''), "LION") !== false)
                                <div class="font-weight-bold">BNI (BANK NEGARA INDONESIA) KCP BEKASI CITRA GRAND</div>
                                <table class="table table-sm table-borderless mt-1">
                                    <tr>
                                        <td style="width: 100px; padding: 2px 0;">ACC Name</td>
                                        <td style="width: 15px; padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong>SANTI WIJAYA L</strong></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 2px 0;">ACC. NUMBER</td>
                                        <td style="padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong>0335163001</strong></td>
                                    </tr>
                                </table>
                            @else
                                <div class="font-weight-bold">BCA (BANK CENTRAL ASIA) KCP BEKASI CITRA GRAND</div>
                                <table class="table table-sm table-borderless mt-1 mb-2">
                                    <tr>
                                        <td style="width: 100px; padding: 2px 0;">ACC Name</td>
                                        <td style="width: 15px; padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong>PT. SANTI WIJAYA MEAT</strong></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 2px 0;">ACC. NUMBER</td>
                                        <td style="padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong>7115534882</strong></td>
                                    </tr>
                                </table>
                                
                                <div class="font-weight-bold">BNI (BANK NEGARA INDONESIA) KCP BEKASI CITRA GRAND</div>
                                <table class="table table-sm table-borderless mt-1">
                                    <tr>
                                        <td style="width: 100px; padding: 2px 0;">ACC Name</td>
                                        <td style="width: 15px; padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong>PT. SANTI WIJAYA MEAT</strong></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 2px 0;">ACC. NUMBER</td>
                                        <td style="padding: 2px 0;">:</td>
                                        <td style="padding: 2px 0;"><strong>8585889991</strong></td>
                                    </tr>
                                </table>
                            @endif
                        </div>
                        <div class="col-4 text-center">
                            <p class="mb-5">F I N A N C E</p>
                            <div style="height: 50px;"></div>
                            <p class="mb-0">..................................................</p>
                        </div>
                    </div>
                </div>

                <div class="floating-controls no-print">
                    <button type="button" onclick="window.close(); window.history.back();" class="btn btn-secondary font-weight-bold">
                        <i class="fas fa-undo"></i> Kembali
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-print"></i> Print Invoice
                    </button>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
