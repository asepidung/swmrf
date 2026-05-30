<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

/* Mengambil ID Monitoring dari URL */
$id = $_GET['id'];

/* Query data header monitoring */
$query = "SELECT mp.*, so.sonumber, so.po, so.deliverydate, c.nama_customer, c.alamat1 
          FROM monitoring_produksi mp
          JOIN salesorder so ON mp.idso = so.idso
          JOIN customers c ON mp.idcustomer = c.idcustomer
          WHERE mp.idmonitoring = '$id'";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

/* Menghitung total berat untuk footer tabel */
$totalWeight = 0;
?>

<style>
    /* CSS untuk mempercantik tampilan tombol dan menyembunyikannya saat print */
    @media print {
        .no-print {
            display: none !important;
        }

        .container {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
        }

        .card {
            border: none !important;
        }
    }

    .btn-custom {
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
</style>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center">
                <h4 class="mb-n1">MONITORING PRODUKSI</h4>
                <span><strong><?= $row['sonumber']; ?></strong></span>
            </div>
            <hr>

            <div class="row mt-2">
                <div class="col-md-6 mb-2">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td width="30%">Customer</td>
                            <td width="5%">:</td>
                            <th><?= $row['nama_customer']; ?></th>
                        </tr>
                        <tr>
                            <td>Alamat</td>
                            <td>:</td>
                            <td><?= $row['alamat1']; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6 mb-2">
                    <table class="table table-borderless table-sm">
                        <tr>
                            <td width="40%">PO Number</td>
                            <td width="5%">:</td>
                            <th><?= ($row['po'] != "") ? $row['po'] : "-"; ?></th>
                        </tr>
                        <tr>
                            <td>Tgl Kirim</td>
                            <td>:</td>
                            <th><?= date('d-M-Y', strtotime($row['deliverydate'])); ?></th>
                        </tr>
                        <tr>
                            <td>Status QC</td>
                            <td>:</td>
                            <th><?= $row['status_qc']; ?></th>
                        </tr>
                    </table>
                </div>
            </div>

            <table class="table table-sm table-striped table-bordered mt-3">
                <thead class="thead-dark text-center">
                    <tr>
                        <th width="5%">#</th>
                        <th width="45%">Nama Produk</th>
                        <th width="20%">Qty / Weight</th>
                        <th width="30%">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    /* Mengambil item barang dari detail monitoring */
                    $query_detail = "SELECT md.*, b.nmbarang 
                                    FROM monitoring_produksidetail md
                                    JOIN barang b ON md.idbarang = b.idbarang
                                    WHERE md.idmonitoring = '$id'";
                    $result_detail = mysqli_query($conn, $query_detail);

                    while ($item = mysqli_fetch_assoc($result_detail)) {
                        $totalWeight += $item['weight'];
                    ?>
                        <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td><?= $item['nmbarang']; ?></td>
                            <td class="text-right"><?= number_format($item['weight'], 2); ?></td>
                            <td><?= $item['notes']; ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold" style="background-color: #f8f9fa;">
                        <td colspan="2" class="text-right">Total Weight</td>
                        <td class="text-right"><?= number_format($totalWeight, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <div class="mt-3">
                <strong>Catatan QC / Produksi :</strong>
                <p class="border p-2" style="min-height: 80px; font-style: italic; background-color: #fff;">
                    <?= ($row['catatan_qc'] != "") ? $row['catatan_qc'] : "-"; ?>
                </p>
            </div>

            <div class="row mt-5 text-center">
                <div class="col-4">
                    <p>PIC QC</p>
                    <br><br><br><br>
                    ( ....................................... )
                </div>
                <div class="col-4">
                    <p>PIC Produksi</p>
                    <br><br><br><br>
                    ( ....................................... )
                </div>
                <div class="col-4">
                    <p>PIC Gudang</p>
                    <br><br><br><br>
                    ( ....................................... )
                </div>
            </div>

            <div class="row mt-5 justify-content-center no-print">
                <div class="col-md-3 col-6">
                    <a href="index.php" class="btn btn-block btn-warning btn-custom shadow-sm">
                        <i class="fas fa-undo"></i> Kembali
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <button type="button" class="btn btn-block btn-primary btn-custom shadow-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Cetak SPK
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    /* Mengubah judul tab browser */
    document.title = "SPK_<?= $row['nama_customer'] . "_" . $row['sonumber'] ?>";
</script>

<?php include "../footer.php"; ?>