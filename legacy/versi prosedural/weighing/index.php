<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// ====================
// Helper sederhana
// ====================
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function tgl($d)
{
    return $d ? date('d/M/Y', strtotime($d)) : '-';
}

// ====================
// Ambil data timbang
// ====================
// Tambahan: isProcessed = 1 jika ADA minimal 1 eartag dari weighing ini
// yang sudah masuk ke carcasedetail (carcase aktif, is_deleted=0).
$sql = "
SELECT
    w.idweigh,
    w.weigh_no,
    w.weigh_date,
    w.note,
    r.receipt_date,
    p.nopo,
    s.nmsupplier,
    u.fullname AS weigher_name,
    COUNT(d.idweighdetail)      AS heads,
    COALESCE(SUM(d.weight), 0)  AS total_actual_weight,
    COALESCE(SUM(crd.weight), 0) AS total_receive_weight,
    (COALESCE(SUM(d.weight), 0) - COALESCE(SUM(crd.weight), 0)) AS total_diff,
    EXISTS(
        SELECT 1
        FROM weight_cattle_detail d2
        JOIN carcasedetail cd
              ON cd.idweightdetail = d2.idweighdetail
        JOIN carcase c
              ON c.idcarcase = cd.idcarcase
             AND c.is_deleted = 0
        WHERE d2.idweigh = w.idweigh
    ) AS isProcessed
FROM weight_cattle w
JOIN cattle_receive r
      ON r.idreceive = w.idreceive
     AND r.is_deleted = 0
JOIN pocattle p
      ON p.idpo = r.idpo
     AND p.is_deleted = 0
JOIN supplier s
      ON s.idsupplier = p.idsupplier
LEFT JOIN users u
      ON u.idusers = w.idweigher
LEFT JOIN weight_cattle_detail d
      ON d.idweigh = w.idweigh
LEFT JOIN cattle_receive_detail crd
      ON crd.idreceivedetail = d.idreceivedetail
WHERE w.is_deleted = 0
GROUP BY
    w.idweigh,
    w.weigh_no,
    w.weigh_date,
    w.note,
    r.receipt_date,
    p.nopo,
    s.nmsupplier,
    u.fullname
ORDER BY w.weigh_date DESC, w.idweigh DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . e($conn->error));
}
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Cattle Weight</h1>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">

                    <div class="card">
                        <div class="card-header">
                            <div class="col-12 col-md-3 mb-2">
                                <a href="draft.php" class="btn btn-sm btn-outline-primary btn-block">
                                    <i class="fas fa-file-alt"></i> Draft
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="example1" class="table table-bordered table-striped table-sm">
                                    <thead>
                                        <tr class="text-center">
                                            <th style="width:5%;">No</th>
                                            <th>Tgl Timbang</th>
                                            <th>No Timbang</th>
                                            <th>No PO</th>
                                            <th>Supplier</th>
                                            <th>Ekor</th>
                                            <th>Selisih (Kg)</th>
                                            <th>Petugas</th>
                                            <th style="width:10%;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        while ($row = $result->fetch_assoc()) :
                                            $heads         = (int)$row['heads'];
                                            $total_actual  = (float)$row['total_actual_weight'];
                                            $total_receive = (float)$row['total_receive_weight'];
                                            $total_diff    = (float)$row['total_diff']; // actual - receive
                                            $isProcessed   = !empty($row['isProcessed']); // 1 jika sudah ada di carcas
                                            $canEdit       = !$isProcessed;
                                            $canDelete     = !$isProcessed;
                                        ?>
                                            <tr>
                                                <td class="text-center"><?= $no++; ?></td>
                                                <td class="text-center"><?= e(tgl($row['weigh_date'])); ?></td>
                                                <td class="text-center"><?= e($row['weigh_no']); ?></td>
                                                <td class="text-center"><?= e($row['nopo']); ?></td>
                                                <td><?= e($row['nmsupplier']); ?></td>
                                                <td class="text-center"><?= number_format($heads, 0, ',', '.'); ?></td>
                                                <td class="text-right">
                                                    <?= number_format($total_diff, 2, ',', '.'); ?>
                                                </td>
                                                <td class="text-center"><?= e($row['weigher_name'] ?? '-'); ?></td>
                                                <td class="text-center">
                                                    <a href="view.php?id=<?= (int)$row['idweigh']; ?>"
                                                        class="btn btn-sm btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <a href="edit.php?id=<?= (int)$row['idweigh']; ?>"
                                                        class="btn btn-sm btn-warning <?= $canEdit ? '' : 'disabled'; ?>"
                                                        title="<?= $canEdit ? 'Edit' : 'Sudah diproses ke Carcas'; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    <a href="delete.php?id=<?= (int)$row['idweigh']; ?>"
                                                        class="btn btn-sm btn-danger <?= $canDelete ? '' : 'disabled'; ?>"
                                                        title="<?= $canDelete ? 'Delete' : 'Sudah diproses ke Carcas'; ?>"
                                                        onclick="return <?= $canDelete ? "confirm('Yakin ingin menghapus data timbang ini?')" : 'false'; ?>;">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div> <!-- /.table-responsive -->
                        </div> <!-- /.card-body -->

                    </div> <!-- /.card -->

                </div>
            </div>

        </div><!-- /.container-fluid -->
    </section>
</div>
<!-- /.content-wrapper -->

<script>
    document.title = "Timbang Sapi";
</script>

<?php
include "../footer.php";
?>