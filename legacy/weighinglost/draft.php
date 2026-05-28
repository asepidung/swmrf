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
// Ambil daftar WEIGHING yang:
//  - w.is_deleted = 0
//  - BELUM punya dokumen cattle_loss_receive
// heads = COUNT(weight_cattle_detail per idweigh)
// =====================================
$sql = "
SELECT
    w.idweigh,
    w.weigh_date,
    r.receipt_date,
    s.nmsupplier,
    COUNT(wd.idweighdetail) AS heads
FROM weight_cattle w
JOIN cattle_receive r
      ON r.idreceive = w.idreceive
     AND r.is_deleted = 0
JOIN pocattle p
      ON p.idpo = r.idpo
     AND p.is_deleted = 0
JOIN supplier s
      ON s.idsupplier = p.idsupplier
LEFT JOIN weight_cattle_detail wd
      ON wd.idweigh = w.idweigh
LEFT JOIN cattle_loss_receive l
      ON l.idweigh  = w.idweigh
     AND l.is_deleted = 0
WHERE w.is_deleted = 0
  AND l.idloss IS NULL
GROUP BY
    w.idweigh,
    w.weigh_date,
    r.receipt_date,
    s.nmsupplier
ORDER BY
    w.weigh_date DESC,
    w.idweigh DESC
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
                    <h1 class="m-0">Draft Cattle Weight Loss (Receiving)</h1>
                    <small class="text-muted">
                        Data weighing yang belum dihitung loss-nya
                    </small>
                </div>
                <div class="col-12 col-md-6 text-md-right mt-2 mt-md-0">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-list"></i> Kembali ke Loss List
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
                                        <th>Receive Date</th>
                                        <th>Supplier</th>
                                        <th>Jumlah Sapi (Ekor)</th>
                                        <th style="width:18%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    if ($res && $res->num_rows) {
                                        while ($r = $res->fetch_assoc()) {
                                            $idweigh   = (int)$r['idweigh'];
                                            $heads     = (int)$r['heads'];
                                    ?>
                                            <tr class="text-center">
                                                <td><?= $no++; ?></td>
                                                <td><?= e(tgl($r['receipt_date'])); ?></td>
                                                <td class="text-left"><?= e($r['nmsupplier']); ?></td>
                                                <td class="text-right"><?= number_format($heads, 0, ',', '.'); ?></td>
                                                <td>
                                                    <a href="create.php?idweigh=<?= $idweigh ?>"
                                                        class="btn btn-success btn-sm"
                                                        title="Process loss untuk weighing ini">
                                                        <i class="fas fa-calculator"></i> Process
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="5" class="text-center text-muted">Tidak ada weighing yang menunggu perhitungan loss.</td></tr>';
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
    document.title = "Draft Cattle Weight Loss (Receiving)";
</script>

<?php include "../footer.php"; ?>