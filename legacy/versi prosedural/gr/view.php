<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$idgr = isset($_GET['idgr']) ? intval($_GET['idgr']) : 0;
$idusers = isset($_SESSION['idusers']) ? intval($_SESSION['idusers']) : 0;

// Query untuk mengambil data dari tabel grraw
$query = "
SELECT grraw.*, supplier.nmsupplier 
FROM grraw 
JOIN supplier ON grraw.idsupplier = supplier.idsupplier 
WHERE grraw.idgr = ? AND grraw.is_deleted = 0";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idgr);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    die("<div class='alert alert-danger'>Data tidak ditemukan.</div>");
}
$row = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../dist/img/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <style>
        body {
            font-family: Cambria, sans-serif;
            font-size: 18px;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <div class="container">
            <div class="row mb-2">
                <img src="../dist/img/headerquo.png" alt="Logo-grraw" class="img-fluid">
            </div>
            <h4 class="text-center">BUKTI TERIMA BARANG</h4>
            <h5 class="text-center">No :<b><i> <?= htmlspecialchars($row['grnumber'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></i></b></h5>
            <table class="table table-sm table-borderless mt-3">
                <tr>
                    <td width="23%">Supplier</td>
                    <td width="1%">:</td>
                    <td><?= htmlspecialchars($row['nmsupplier'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                </tr>
                <tr>
                    <td width="23%">Receiving Date</td>
                    <td width="1%">:</td>
                    <td><?= htmlspecialchars(!empty($row['receivedate']) ? date('d-M-Y', strtotime($row['receivedate'])) : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                </tr>
            </table>

            <table class="table table-sm table-bordered">
                <thead>
                    <tr class="text-center">
                        <th width="2%">NO</th>
                        <th>Product Descriptions</th>
                        <th>Units</th>
                        <th>Ordered Quantity</th>
                        <th>Received Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Query untuk mengambil detail dari tabel grrawdetail (termasuk satuan dari rawmate)
                    $querydetail = "
                    SELECT 
                        rm.nmrawmate, 
                        rm.unit,
                        grd.orderqty AS ordered_qty, 
                        grd.qty AS received_qty
                    FROM grrawdetail grd
                    JOIN rawmate rm ON grd.idrawmate = rm.idrawmate
                    WHERE grd.idgr = ? AND grd.is_deleted = 0";
                    $stmtdetail = $conn->prepare($querydetail);
                    $stmtdetail->bind_param("i", $idgr);
                    $stmtdetail->execute();
                    $resultdetail = $stmtdetail->get_result();

                    $counter = 1;

                    while ($rowdetail = $resultdetail->fetch_assoc()) {
                        $nm = htmlspecialchars($rowdetail['nmrawmate'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $unit = htmlspecialchars($rowdetail['unit'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                        $ordered = isset($rowdetail['ordered_qty']) ? (float)$rowdetail['ordered_qty'] : 0.0;
                        $received = isset($rowdetail['received_qty']) ? (float)$rowdetail['received_qty'] : 0.0;
                    ?>
                        <tr>
                            <td class="text-center"><?= $counter++; ?></td>
                            <td><?= $nm; ?></td>
                            <td class="text-center"><?= $unit; ?></td>
                            <td class="text-right"><?= number_format($ordered, 2); ?></td>
                            <td class="text-right"><?= number_format($received, 2); ?></td>
                        </tr>
                    <?php
                    }
                    $stmtdetail->close();
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-center">TOTAL</th>
                        <th></th>
                        <th class="text-right">
                            <?php
                            // Hitung total ordered_qty yang belum di-soft delete
                            $query_total_order = "SELECT COALESCE(SUM(orderqty),0) AS total_order FROM grrawdetail WHERE idgr = ? AND is_deleted = 0";
                            $stmt_total_order = $conn->prepare($query_total_order);
                            $stmt_total_order->bind_param("i", $idgr);
                            $stmt_total_order->execute();
                            $result_total_order = $stmt_total_order->get_result();
                            $total_order_row = $result_total_order->fetch_assoc();
                            $total_order = isset($total_order_row['total_order']) ? (float)$total_order_row['total_order'] : 0.0;
                            echo htmlspecialchars(number_format($total_order, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $stmt_total_order->close();
                            ?>
                        </th>
                        <th class="text-right">
                            <?php
                            // Hitung total received_qty yang belum di-soft delete
                            $query_total_received = "SELECT COALESCE(SUM(qty),0) AS total_received FROM grrawdetail WHERE idgr = ? AND is_deleted = 0";
                            $stmt_total_received = $conn->prepare($query_total_received);
                            $stmt_total_received->bind_param("i", $idgr);
                            $stmt_total_received->execute();
                            $result_total_received = $stmt_total_received->get_result();
                            $total_received_row = $result_total_received->fetch_assoc();
                            $total_received = isset($total_received_row['total_received']) ? (float)$total_received_row['total_received'] : 0.0;
                            echo htmlspecialchars(number_format($total_received, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            $stmt_total_received->close();
                            ?>
                        </th>
                    </tr>
                </tfoot>
            </table>

            <div class="row mt-3">
                <div class="col-6 float-right text-justify">
                    <?php if (!empty($row['note'])) { ?>
                        Catatan : <strong><?= htmlspecialchars($row['note'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                    <?php } ?>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-8"></div>
                <div class="col-4 text-center">
                    RECEIVING STAFF
                    <br><br><br><br><br>
                    <?= htmlspecialchars($_SESSION['fullname'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            </div>
            <div class="col">
                <p><strong>Penting !</strong></p>
                <li>Dokumen ini diperuntukkan sebagai bukti penerimaan Barang oleh PT. SANTI WIJAYA MEAT</li>
                <li>Dokumen Ini Wajib Dibawa Ketika Akan Melakukan Tukar Faktur</li>
            </div>
        </div>
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
    <script>
        // Mengubah judul halaman web
        document.title = "<?= htmlspecialchars($row['grnumber'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>";
    </script>
    <script src="../plugins/jquery/jquery.min.js"></script>
    <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/js/adminlte.min.js"></script>
</body>

</html>