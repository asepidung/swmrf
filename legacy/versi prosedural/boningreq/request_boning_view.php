<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Tangkap idboning
$idboning = isset($_GET['idboning']) ? (int)$_GET['idboning'] : 0;

// Tambahkan b.kunci di Select-nya untuk mengecek status lock
$query = "SELECT pr.*, b.batchboning, b.tglboning, b.kunci, u.fullname 
          FROM production_requests pr
          INNER JOIN boning b ON pr.idboning = b.idboning
          INNER JOIN users u ON pr.idusers = u.idusers
          WHERE pr.idboning = $idboning AND pr.is_deleted = 0 
          LIMIT 1";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo "<script>alert('Data Request tidak ditemukan!'); window.location='../boning/databoning.php';</script>";
    exit;
}

$idrequest = $row['idrequest'];
?>

<style>
    /* Memastikan elemen UI yang tidak diperlukan hilang saat di-print */
    @media print {
        body * {
            visibility: hidden;
        }

        #print-area,
        #print-area * {
            visibility: visible;
        }

        #print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .main-footer,
        .main-header,
        .main-sidebar {
            display: none !important;
        }
    }
</style>

<div class="content-wrapper" id="print-area">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <div class="text-center">
                    <h4 class="mb-n1">REQUEST MARKETING BONING</h4>
                    <span><strong><?= htmlspecialchars($row['batchboning']); ?></strong></span>
                </div>
                <hr>

                <div class="row mt-2">
                    <div class="col-md-6 mb-2">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td width="35%">Batch Boning</td>
                                <td width="5%">:</td>
                                <th><?= htmlspecialchars($row['batchboning']); ?></th>
                            </tr>
                            <tr>
                                <td>Tgl Boning</td>
                                <td>:</td>
                                <th><?= date('d-M-Y', strtotime($row['tglboning'])); ?></th>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 mb-2">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td width="35%">Requested By</td>
                                <td width="5%">:</td>
                                <th><?= htmlspecialchars($row['fullname']); ?></th>
                            </tr>
                            <tr>
                                <td>Request Date</td>
                                <td>:</td>
                                <th><?= date('d-M-Y H:i', strtotime($row['creatime'])); ?></th>
                            </tr>
                        </table>
                    </div>
                </div>

                <table class="table table-sm table-striped table-bordered">
                    <thead class="thead-dark">
                        <tr class="text-center">
                            <th>#</th>
                            <th>Product Name</th>
                            <th>Qty</th>
                            <th>Satuan</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $query_items = "SELECT pri.*, b.nmbarang 
                                  FROM production_request_items pri
                                  INNER JOIN barang b ON pri.idbarang = b.idbarang
                                  WHERE pri.idrequest = $idrequest";
                        $result_items = mysqli_query($conn, $query_items);

                        while ($item = mysqli_fetch_assoc($result_items)) { ?>
                            <tr>
                                <td class="text-center"><?= $no; ?></td>
                                <td><?= htmlspecialchars($item['nmbarang']); ?></td>
                                <td class="text-right"><?= number_format($item['qty'], 2); ?></td>
                                <td class="text-center"><?= htmlspecialchars($item['satuan']); ?></td>
                                <td><?= htmlspecialchars($item['notes'] ?? '-'); ?></td>
                            </tr>
                        <?php $no++;
                        } ?>
                    </tbody>
                </table>

                <p class="mb-n1">
                    <?php if (trim($row['spesifications']) !== "") { ?>
                        <strong>Spesifikasi / Instruksi Umum :</strong>
                    <?php } else {
                        echo "<strong>Spesifikasi :</strong> -";
                    } ?>
                </p>
                <p><i><?= nl2br(htmlspecialchars($row['spesifications'])); ?></i></p>

                <div class="row mt-4 justify-content-center d-print-none">
                    <div class="col-4 col-md-2 mb-2">
                        <a href="../boning/databoning.php">
                            <button type="button" class="btn btn-block btn-success" title="Kembali"><i class="fas fa-undo"></i></button>
                        </a>
                    </div>

                    <div class="col-4 col-md-2 mb-2">
                        <?php if ($row['kunci'] == 0) { ?>
                            <a href="request_boning_edit.php?idboning=<?= $idboning ?>">
                                <button type="button" class="btn btn-block btn-info" title="Edit Request"><i class="fas fa-edit"></i></button>
                            </a>
                        <?php } else { ?>
                            <button type="button" class="btn btn-block btn-secondary" disabled title="Batch Boning Sudah Dikunci, Request Tidak Bisa Diedit">
                                <i class="fas fa-lock"></i>
                            </button>
                        <?php } ?>
                    </div>

                    <div class="col-4 col-md-2 mb-2">
                        <button type="button" class="btn btn-block btn-warning" title="Print Request" onclick="window.print()">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    document.title = "View Request - <?= htmlspecialchars($row['batchboning']); ?>"
</script>

<?php include "../footer.php"; ?>