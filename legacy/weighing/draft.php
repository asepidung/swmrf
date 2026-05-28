<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Helpers
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function tgl($d)
{
    return $d ? date('d/M/Y', strtotime($d)) : '-';
}

// =====================================
// Ambil daftar RECEIVE yang BELUM ditimbang
// heads = COUNT(cattle_receive_detail per idreceive)
// =====================================
$sql = "
SELECT
    r.idreceive,
    r.receipt_date,
    s.nmsupplier,
    COUNT(d.idreceivedetail) AS heads
FROM cattle_receive r
JOIN pocattle p
      ON p.idpo = r.idpo
     AND p.is_deleted = 0
JOIN supplier s
      ON s.idsupplier = p.idsupplier
LEFT JOIN cattle_receive_detail d
      ON d.idreceive = r.idreceive
LEFT JOIN weight_cattle w
      ON w.idreceive = r.idreceive
     AND w.is_deleted = 0
WHERE r.is_deleted = 0
  AND w.idweigh IS NULL          -- <== ini yang tadinya salah pakai idweight
GROUP BY
    r.idreceive,
    r.receipt_date,
    s.nmsupplier
ORDER BY
    r.receipt_date DESC,
    r.idreceive DESC
";

$res = $conn->query($sql);
if (!$res) {
    die("Query error: " . e($conn->error));
}
?>

<div class="content-wrapper">

    <!-- Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12 col-md-6">
                    <h1 class="m-0">Draft Weighing</h1>
                    <small class="text-muted">Penerimaan sapi yang belum diproses penimbangan</small>
                </div>
                <div class="col-12 col-md-6 text-md-right mt-2 mt-md-0">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-list"></i> Kembali ke Weighing List
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main -->
    <section class="content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">

                    <div class="card">
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th style="width:5%;">#</th>
                                        <th>Tanggal Penerimaan</th>
                                        <th>Supplier</th>
                                        <th>Jumlah Sapi (Ekor)</th>
                                        <th style="width:15%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    if ($res && $res->num_rows) {
                                        while ($r = $res->fetch_assoc()) {
                                            $idreceive = (int)$r['idreceive'];
                                            $heads     = (int)$r['heads'];
                                    ?>
                                            <tr class="text-center">
                                                <td><?= $no++; ?></td>
                                                <td><?= e(tgl($r['receipt_date'])); ?></td>
                                                <td class="text-left"><?= e($r['nmsupplier']); ?></td>
                                                <td class="text-right"><?= number_format($heads, 0, ',', '.'); ?></td>
                                                <td>
                                                    <a href="create.php?idreceive=<?= $idreceive ?>"
                                                        class="btn btn-success btn-sm" title="Process">
                                                        <i class="fas fa-balance-scale"></i> Process
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center text-muted">Tidak ada penerimaan yang menunggu proses timbang.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div><!-- /.card-body -->
                    </div><!-- /.card -->

                </div>
            </div>

        </div>
    </section>
</div>


<script>
    document.title = "Draft Timbang";
</script>
<?php include "../footer.php"; ?>