<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $salesOrder->customer->name ?? 'Customer' }} - {{ $salesOrder->so_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* CSS khusus saat halaman di-print */
        @media print {
            .no-print {
                display: none !important;
                /* Menyembunyikan tombol saat dicetak */
            }

            body {
                background-color: white;
            }
        }

        body {
            background-color: #f4f6f9;
        }

        .page-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 page-container">
                <div class="text-center">
                    <h4 class="mb-n1">SALES ORDER</h4>
                    <span><strong>{{ $salesOrder->so_number }}</strong></span>
                </div>
                <hr>
                <div class="row mt-2">
                    <div class="col-md-6 mb-2">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td width="25%">Customer</td>
                                <td width="5%">:</td>
                                <th>{{ $salesOrder->customer->name ?? '-' }}</th>
                            </tr>
                            <tr>
                                <td>Ship To</td>
                                <td>:</td>
                                <th>{{ $salesOrder->shipping_address ?? '-' }}</th>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 mb-2">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td width="30%">PO Numb</td>
                                <td width="5%">:</td>
                                <th>{{ $salesOrder->po_number ?? '-' }}</th>
                            </tr>
                            <tr>
                                <td>Delivery Date</td>
                                <td>:</td>
                                <th>{{ \Carbon\Carbon::parse($salesOrder->delivery_date)->format('d-M-Y') }}</th>
                            </tr>
                        </table>
                    </div>
                </div>

                <table class="table table-sm table-striped table-bordered mt-3">
                    <thead class="thead-dark text-center">
                        <tr>
                            <th width="5%">#</th>
                            <th width="15%">Product Code</th>
                            <th width="25%">Product Name</th>
                            <th width="10%">Order Qty</th>
                            <th width="15%" class="price-col">Price</th>
                            <th width="10%" class="price-col">Discount</th>
                            <th width="20%">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salesOrder->items as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td class="text-center">{{ $item->product->code ?? '-' }}</td>
                            <td>{{ $item->product->name ?? '-' }}</td>
                            <td class="text-right">{{ number_format($item->weight, 2, '.', ',') }}</td>
                            <td class="text-right price-col">{{ number_format($item->price, 0, '', ',') }}</td>
                            <td class="text-center price-col">{{ $item->discount }} %</td>
                            <td>{{ $item->note }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">Weight Total</th>
                            <th class="text-right">{{ number_format($totalWeight, 2, '.', ',') }}</th>
                            <th colspan="3" class="price-col"></th>
                            <th class="non-price-col" style="display: none;"></th>
                        </tr>
                    </tfoot>
                </table>

                <p class="mb-1 mt-4">
                    <strong>Catatan :</strong> {{ $salesOrder->note ?? '-' }}
                </p>

                <div class="row mt-5 no-print border-top pt-3">
                    <div class="col-sm-3 mb-2">
                        <button type="button" id="toggleBtn" onclick="togglePrice()" class="btn btn-block btn-warning font-weight-bold">
                            <i class="fas fa-eye-slash"></i> Hide Price
                        </button>
                    </div>
                    <div class="col-sm-3 mb-2">
                        <button type="button" onclick="window.close()" class="btn btn-block btn-secondary">
                            <i class="fas fa-undo"></i> Close Tab
                        </button>
                    </div>
                    <div class="col-sm-3 mb-2">
                        <button type="button" onclick="window.print()" class="btn btn-block btn-primary font-weight-bold">
                            <i class="fas fa-print"></i> Print Document
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Fungsi cerdik untuk menyembunyikan/menampilkan kolom harga tanpa reload
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

            // Menyesuaikan colspan tfoot saat harga disembunyikan
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