<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// --- helpers ---
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function tgl($d)
{
    return $d ? date('d/M/Y', strtotime($d)) : '-';
}

// --- query data (agregasi dari detail) ---
// tambahkan p.note di SELECT dan GROUP BY
$sql = "
SELECT
  p.idpo,
  p.nopo,
  p.podate,
  p.arrival_date,
  s.nmsupplier,
  p.note,
  COALESCE(SUM(d.qty), 0) AS total_qty,
  GROUP_CONCAT(CONCAT(d.class, ' (', d.qty, ')') ORDER BY d.class SEPARATOR ', ') AS jenis_ringkas,
  EXISTS(
    SELECT 1 FROM cattle_receive cr
    WHERE cr.idpo = p.idpo AND cr.is_deleted = 0
  ) AS isProcessed
FROM pocattle p
LEFT JOIN supplier s       ON s.idsupplier = p.idsupplier
LEFT JOIN pocattledetail d ON d.idpo = p.idpo AND d.is_deleted = 0
WHERE p.is_deleted = 0
GROUP BY p.idpo, p.nopo, p.podate, p.arrival_date, s.nmsupplier, p.note
ORDER BY p.idpo DESC
";
$res = $conn->query($sql);
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12 col-md-2 mb-2">
                    <a href="newpocattle.php" class="btn btn-sm btn-outline-primary btn-block">
                        <i class="fas fa-plus"></i> Baru
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
                                        <th>PO Date</th>
                                        <th>Arrival Date</th>
                                        <th>No PO</th>
                                        <th>Suppliers</th>
                                        <th>Qty Head</th>
                                        <th>Jenis Sapi</th>
                                        <th>Catatan</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    if ($res && $res->num_rows) {
                                        while ($r = $res->fetch_assoc()) {
                                            $idpo       = (int)$r['idpo'];
                                            $canDelete  = empty($r['isProcessed']);  // 1 = sudah diproses, 0/null = belum
                                            $canEdit    = $canDelete;
                                    ?>
                                            <tr class="text-center">
                                                <td><?= $no++; ?></td>
                                                <td><?= tgl($r['podate']); ?></td>
                                                <td><?= tgl($r['arrival_date']); ?></td>
                                                <td><?= e($r['nopo']); ?></td>
                                                <td class="text-left"><?= e($r['nmsupplier'] ?? '-'); ?></td>
                                                <td><?= number_format((int)$r['total_qty'], 0, ',', '.'); ?></td>
                                                <td class="text-left"><?= e($r['jenis_ringkas'] ?: '-'); ?></td>
                                                <td class="text-left"><?= nl2br(e($r['note'] ?? '')); ?></td>

                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="view.php?id=<?= $idpo ?>" class="btn btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>

                                                        <a href="<?= $canEdit ? 'edit.php?id=' . $idpo : 'javascript:void(0);' ?>"
                                                            class="btn btn-warning <?= $canEdit ? '' : 'disabled' ?>"
                                                            title="<?= $canEdit ? 'Edit' : 'Data Sudah di Proses' ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </a>

                                                        <a href="delete.php?id=<?= $idpo ?>"
                                                            class="btn btn-danger <?= $canDelete ? '' : 'disabled' ?>"
                                                            onclick="return <?= $canDelete ? 'confirm(\'Hapus PO ini?\')' : 'false' ?>;"
                                                            title="<?= $canDelete ? 'Delete' : 'Data Sudah di Proses' ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="9" class="text-center text-muted">Belum ada data PO Cattle.</td></tr>';
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
    document.title = "PO Cattle";
</script>

<?php include "../footer.php"; ?>