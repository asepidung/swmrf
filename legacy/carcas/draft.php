<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// ====================
// Helper sederhana
// ====================
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('tgl')) {
    function tgl($d)
    {
        return $d ? date('d/M/Y', strtotime($d)) : '-';
    }
}

// ====================
// Ambil draft CARCAS
// ====================
// Logika:
//  - Ambil semua sapi di weight_cattle_detail (d)
//  - LEFT JOIN ke carcasedetail (cd) dan carcase (c)
//  - Yang dihitung hanya kalau TIDAK punya carcase aktif (c.idcarcase IS NULL)
//  - Group per batch timbang (w.idweigh)
//  - Hanya tampil kalau masih ada sisa sapi (heads > 0)
$sql = "
SELECT
    w.idweigh,
    w.weigh_no,
    w.weigh_date,
    r.receipt_date,                -- <-- tambah receipt_date
    s.nmsupplier,
    COUNT(d.idweighdetail) AS heads
FROM weight_cattle w
JOIN cattle_receive r
      ON r.idreceive = w.idreceive
     AND r.is_deleted = 0
JOIN pocattle p
      ON p.idpo = r.idpo
     AND p.is_deleted = 0
JOIN supplier s
      ON s.idsupplier = p.idsupplier
JOIN weight_cattle_detail d
      ON d.idweigh = w.idweigh
LEFT JOIN carcasedetail cd
      ON cd.idweightdetail = d.idweighdetail
LEFT JOIN carcase c
      ON c.idcarcase = cd.idcarcase
     AND c.is_deleted = 0
WHERE w.is_deleted = 0
  AND c.idcarcase IS NULL       -- sapi yang belum punya carcase aktif
GROUP BY
    w.idweigh,
    w.weigh_no,
    w.weigh_date,
    r.receipt_date,              -- <-- tambah ke GROUP BY
    s.nmsupplier
HAVING
    heads > 0                   -- hanya batch yang masih punya sapi sisa
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
                    <h1 class="m-0">Draft Carcas</h1>
                    <small class="text-muted">
                        Batch timbang sapi yang masih memiliki sapi belum dipotong (belum punya carcase)
                    </small>
                </div>
                <div class="col-12 col-md-6 text-md-right mt-2 mt-md-0">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-list"></i> Kembali ke Data Carcas
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
                                        <th>No Timbang</th>
                                        <th>Tanggal Penerimaan</th>
                                        <th>Tanggal Timbang</th>
                                        <th>Supplier</th>
                                        <th class="text-right">Sapi Belum Dipotong (Ekor)</th>
                                        <th style="width:15%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    if ($res->num_rows > 0) {
                                        while ($r = $res->fetch_assoc()) {
                                            $idweigh = (int)$r['idweigh'];
                                            $heads   = (int)$r['heads'];
                                    ?>
                                            <tr>
                                                <td class="text-center"><?= $no++; ?></td>
                                                <td class="text-center"><?= e($r['weigh_no']); ?></td>
                                                <td class="text-center"><?= e(tgl($r['receipt_date'])); ?></td>
                                                <td class="text-center"><?= e(tgl($r['weigh_date'])); ?></td>
                                                <td class="text-left"><?= e($r['nmsupplier']); ?></td>
                                                <td class="text-right"><?= number_format($heads, 0, ',', '.'); ?></td>
                                                <td class="text-center">
                                                    <a href="create.php?idweight=<?= $idweigh ?>"
                                                        class="btn btn-success btn-sm"
                                                        title="Proses sapi yang belum dipotong">
                                                        <i class="fas fa-drumstick-bite"></i> Process
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="7" class="text-center text-muted">Tidak ada batch timbang yang masih memiliki sapi belum dipotong.</td></tr>';
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
    document.title = "Draft Carcas";
</script>

<?php include "../footer.php"; ?>