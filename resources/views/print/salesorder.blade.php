<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Order - {{ $record->so_number }}</title>
    <link class="no-print" rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- For printing, we still load Bootstrap to render tables correctly -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" media="print">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Arial', sans-serif;
            font-size: 12px;
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

        /* Premium Header Styling */
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
            font-weight: 800;
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
            font-size: 22px;
            font-weight: 800;
            color: #007bff;
            letter-spacing: 1px;
        }

        .doc-meta {
            font-size: 11px;
            color: #333;
            margin-top: 3px;
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
            border-radius: 20px;
            padding: 10px 15px;
            font-size: 11px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .floating-controls .btn:hover {
            transform: scale(1.05);
        }

        @media print {
            .no-print {
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
                            Kab. Bogor, Jawa Barat, 16830 | Phone: 0813 6006 959
                        </div>
                    </div>
                    <div class="doc-title-box">
                        <h2>SALES ORDER</h2>
                        <div class="doc-meta">
                            <strong>SO Number:</strong> {{ $record->so_number }}
                        </div>
                    </div>
                </div>

                <!-- Order Meta Information -->
                <div class="row mt-2 mb-3">
                    <div class="col-md-6 mb-2">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td style="width: 100px;" class="text-muted">Customer</td>
                                <td style="width: 15px;" class="text-muted">:</td>
                                <th class="text-dark">{{ $record->customer->name ?? '-' }}</th>
                            </tr>
                            <tr>
                                <td class="text-muted">Ship To</td>
                                <td class="text-muted">:</td>
                                <td class="text-muted font-weight-bold">{{ $record->shipping_address ?? '-' }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 mb-2">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td style="width: 100px;" class="text-muted">PO Number</td>
                                <td style="width: 15px;" class="text-muted">:</td>
                                <th class="text-dark">{{ $record->po_number ?? '-' }}</th>
                            </tr>
                            <tr>
                                <td class="text-muted">Delivery Date</td>
                                <td class="text-muted">:</td>
                                <th class="text-dark">{{ \Carbon\Carbon::parse($record->delivery_date)->format('d-M-Y') }}</th>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Products Table -->
                <table class="table table-sm table-striped table-bordered mt-3">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th width="5%">#</th>
                            <th width="12%">Product Code</th>
                            <th width="20%">Product Name</th>
                            <th width="12%">Order Qty</th>
                            <th width="13%" class="price-col">Price</th>
                            <th width="8%" class="price-col">Discount</th>
                            <th width="30%">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalWeight = 0; @endphp
                        @foreach($record->items as $index => $item)
                        @php $totalWeight += $item->weight; @endphp
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td class="text-center">{{ $item->product->code ?? '-' }}</td>
                            <td>{{ $item->product->name ?? '-' }}</td>
                            <td class="text-right">{{ number_format($item->weight, 2, ',', '.') }}</td>
                            <td class="text-right price-col">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                            <td class="text-center price-col">{{ $item->discount }} %</td>
                            <td>{{ $item->note ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold">
                            <th colspan="3" class="text-right">Weight Total</th>
                            <th class="text-right">{{ number_format($totalWeight, 2, ',', '.') }}</th>
                            <th colspan="3" class="price-col"></th>
                            <th class="non-price-col" style="display: none;"></th>
                        </tr>
                    </tfoot>
                </table>

                <!-- Note Section -->
                @if($record->note)
                <div class="card bg-light mt-3">
                    <div class="card-body p-3">
                        <strong>Catatan:</strong>
                        <p class="mb-0 text-muted italic"><i>{{ $record->note }}</i></p>
                    </div>
                </div>
                @endif

                <!-- Signature Section -->
                <div class="row mt-5 text-center justify-content-end">
                    <div class="col-4">
                        <p>Dibuat Oleh,</p>
                        <div style="height: 80px;"></div>
                        <p class="font-weight-bold text-underline mb-0">{{ $record->creator->name ?? 'Admin' }}</p>
                        <small class="text-muted">Admin</small>
                    </div>
                </div>

                <!-- Footer Metas -->
                <div class="text-muted text-right mt-5" style="font-size: 10px;">
                    Dibuat pada {{ $record->created_at ? $record->created_at->format('d-m-Y H:i:s') : '-' }}
                </div>

                <!-- Controls Block (no-print) -->
                <div class="floating-controls no-print">
                    <button type="button" id="toggleBtn" onclick="togglePrice()" class="btn btn-warning font-weight-bold text-white">
                        <i class="fas fa-eye-slash"></i> Hide Price
                    </button>
                    <button type="button" onclick="window.close()" class="btn btn-secondary font-weight-bold">
                        <i class="fas fa-undo"></i> Close Tab
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-print"></i> Print Document
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script>
        function togglePrice() {
            const priceCols = document.querySelectorAll('.price-col');
            const nonPriceCol = document.querySelector('.non-price-col');
            const btn = document.getElementById('toggleBtn');
            let isHidden = false;

            priceCols.forEach(el => {
                if (el.style.display === 'none') {
                    el.style.display = '';
                } else {
                    el.style.display = 'none';
                    isHidden = true;
                }
            });

            if (isHidden) {
                nonPriceCol.style.display = '';
                btn.innerHTML = '<i class="fas fa-eye"></i> Show Price';
                btn.classList.replace('btn-warning', 'btn-success');
            } else {
                nonPriceCol.style.display = 'none';
                btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Price';
                btn.classList.replace('btn-success', 'btn-warning');
            }
        }
    </script>
</body>
</html>
