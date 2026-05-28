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

// Ambil daftar PO yang BELUM punya penerimaan aktif
// heads = SUM(qty) dari pocattledetail (is_deleted=0)
$sql = "
SELECT 
  p.idpo,
  p.nopo,
  p.arrival_date AS receiving_date,
  s.nmsupplier,
  COALESCE(SUM(d.qty), 0) AS heads
FROM pocattle p
JOIN supplier s ON s.idsupplier = p.idsupplier
LEFT JOIN pocattledetail d 
       ON d.idpo = p.idpo AND d.is_deleted = 0
LEFT JOIN cattle_receive cr 
       ON cr.idpo = p.idpo AND cr.is_deleted = 0
WHERE p.is_deleted = 0
  AND cr.idreceive IS NULL
GROUP BY p.idpo, p.nopo, p.arrival_date, s.nmsupplier
ORDER BY p.arrival_date IS NULL, p.arrival_date ASC, p.idpo DESC
";
$res = $conn->query($sql);
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12 col-md-6">
                    <h1 class="m-0">Draft Receive</h1>
                    <small class="text-muted">PO yang belum diproses penerimaannya</small>
                </div>
                <div class="col-12 col-md-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-list"></i> Kembali ke Receive List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Receiving Date</th>
                                        <th>No PO</th>
                                        <th>Supplier</th>
                                        <th>Cattle Qty (Head)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    if ($res && $res->num_rows) {
                                        while ($r = $res->fetch_assoc()) {
                                            $idpo = (int)$r['idpo'];
                                    ?>
                                            <tr class="text-center">
                                                <td><?= $no++; ?></td>
                                                <td><?= tgl($r['receiving_date']); ?></td>
                                                <td class="text-left"><?= e($r['nopo']); ?></td>
                                                <td class="text-left"><?= e($r['nmsupplier']); ?></td>
                                                <td><?= number_format((int)$r['heads'], 0, ',', '.'); ?></td>
                                                <td>
                                                    <a href="create.php?idpo=<?= $idpo ?>" class="btn btn-success btn-sm" title="Process">
                                                        <i class="fas fa-truck-loading"></i> Process
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center text-muted">Tidak ada PO yang menunggu proses receive.</td></tr>';
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
    document.title = "Draft Receive";
</script>

<?php include "../footer.php"; ?>