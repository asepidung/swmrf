<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../inv/terbilang.php";

// Ambil ID Request dari parameter GET
$idrequest = $_GET['idrequest'];

// Tampilkan data dari tabel pobeef berdasarkan idrequest
$query_po = "SELECT pobeef.*, supplier.nmsupplier 
             FROM pobeef 
             INNER JOIN supplier ON pobeef.idsupplier = supplier.idsupplier 
             WHERE pobeef.idrequest = ?";
$stmt_po = $conn->prepare($query_po);
$stmt_po->bind_param("i", $idrequest);
$stmt_po->execute();
$result_po = $stmt_po->get_result();
$row_po = $result_po->fetch_assoc();

if (!$row_po) {
    die("PO not found for the given Request.");
}

// Tampilkan data dari tabel pobeefdetail berdasarkan idpo
$query_podetail = "SELECT pobeefdetail.*, barang.nmbarang, (pobeefdetail.qty * pobeefdetail.price) AS subtotal 
                   FROM pobeefdetail 
                   INNER JOIN barang ON pobeefdetail.idbarang = barang.idbarang 
                   WHERE pobeefdetail.idpo = ?";
$stmt_podetail = $conn->prepare($query_podetail);
$stmt_podetail->bind_param("i", $row_po['idpo']);
$stmt_podetail->execute();
$result_podetail = $stmt_podetail->get_result();

// Hitung Total Amount (Before Tax)
$totalAmount = 0;
while ($row_podetail = $result_podetail->fetch_assoc()) {
    $totalAmount += $row_podetail['subtotal']; // Jumlahkan semua subtotal dari pobeefdetail
}

// Reset hasil query podetail untuk iterasi ulang di tampilan
$result_podetail->data_seek(0);

// Hitung pajak
$taxPercent = floatval($row_po['tax']); // Ambil persentase pajak
$taxAmount = ($taxPercent > 0) ? ($totalAmount * $taxPercent / 100) : 0; // Hitung pajak berdasarkan persentase

// Hitung total setelah pajak
$totalAfterTax = $totalAmount + $taxAmount;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $row_po['nopo'] . " - " . $row_po['nmsupplier']; ?></title>
    <link rel="icon" href="../dist/img/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <style>
        body {
            font-size: 14px;
        }
    </style>
</head>

<body class="hold-transition layout-fixed">
    <div class="wrapper">
        <div class="container">
            <div class="row mb-2">
                <img src="../dist/img/headerpo.png" alt="Logo-po" class="img-fluid">
            </div>

            <table class="table table-borderless table-sm">
                <tr>
                    <td width="15%">PO Number</td>
                    <td width="1%">:</td>
                    <td width="30%"><?= $row_po['nopo']; ?></td>
                    <td></td>
                    <td width="15%">Delivery Date</td>
                    <td width="1%">:</td>
                    <td width="30%"><?= date('d-M-Y', strtotime($row_po['duedate'])); ?></td>
                </tr>
                <tr>
                    <td>PO Date</td>
                    <td>:</td>
                    <td><?= date('d-M-Y', strtotime($row_po['creatime'])); ?></td>
                    <td></td>
                    <td>Supplier</td>
                    <td>:</td>
                    <td><?= $row_po['nmsupplier']; ?></td>
                </tr>
                <tr>
                    <td>Delivery Address</td>
                    <td>:</td>
                    <td>RPH Jonggol Kp. Menan Rt 04.01 Ds. Sukamaju Kec. Jonggol Kab. Bogor</td>
                    <td></td>
                    <td>Tax</td>
                    <td>:</td>
                    <td><?= ($taxPercent > 0) ? $taxPercent . "%" : "No Tax"; ?></td>
                </tr>
            </table>

            <table class="table table-sm table-bordered">
                <thead class="thead-dark">
                    <tr class="text-center">
                        <th>#</th>
                        <th>Prod Descriptions</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    while ($row_podetail = $result_podetail->fetch_assoc()) {
                    ?>
                        <tr class="text-right">
                            <td class="text-center"><?= $no++; ?></td>
                            <td class="text-left"><?= $row_podetail['nmbarang']; ?></td>
                            <td><?= number_format($row_podetail['qty'], 2); ?></td>
                            <td><?= number_format($row_podetail['price'], 2); ?></td>
                            <td><?= number_format($row_podetail['subtotal'], 2); ?></td>
                            <td><?= $row_podetail['notes']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">Total Amount (Before Tax)</th>
                        <th class="text-right"><?= number_format($totalAmount, 2); ?></th>
                        <th></th>
                    </tr>
                    <tr>
                        <th colspan="4" class="text-right">Tax (<?= ($taxPercent > 0) ? $taxPercent . "%" : "No Tax"; ?>)</th>
                        <th class="text-right"><?= number_format($taxAmount, 2); ?></th>
                        <th></th>
                    </tr>
                    <tr>
                        <th colspan="4" class="text-right">Total After Tax</th>
                        <th class="text-right"><?= number_format($totalAfterTax, 2); ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
            <div class="col">
                NOTE :
                <p>
                    <?= $row_po['note']; ?>
                </p>
            </div>
            <div class="col text-right">
                PURCHASING
                <br><br><br><br>
                AYU SILVY
            </div>
            <div class="row mt-3 justify-content-center no-print">
                <div class="col-6 col-sm-4 col-md-3 mb-2">
                    <a href="javascript:history.back()">
                        <button type="button" class="btn btn-block btn-success"><i class="fas fa-undo"></i></button>
                    </a>
                </div>
                <div class="col-6 col-sm-4 col-md-3">
                    <button type="button" class="btn btn-block btn-warning" onclick="window.print()">
                        <i class="fas fa-print"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>