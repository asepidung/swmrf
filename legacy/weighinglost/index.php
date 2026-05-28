<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// (opsional, bantu debug)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helpers
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function tgl($d)
{
    return $d ? date('d/M/Y', strtotime($d)) : '-';
}
function n2($n)
{
    return number_format((float)$n, 2, ',', '.');
}
function rupiah($n)
{
    return number_format((float)$n, 0, ',', '.');
}

/**
 * List dokumen cattle_loss_receive aktif
 * Ringkasan diambil dari tabel cattle_loss_receive_detail.
 */
$sql = "
SELECT
  l.idloss,
  l.loss_no,
  l.loss_date,

  w.weigh_no,
  w.weigh_date,

  r.receipt_date,
  r.doc_no,
  p.nopo,
  s.nmsupplier,

  COALESCE(COUNT(d.idlossdetail), 0) AS heads,
  COALESCE(SUM(d.receive_weight), 0) AS total_receive_weight,
  COALESCE(SUM(d.actual_weight), 0)  AS total_actual_weight,
  COALESCE(SUM(d.loss_weight), 0)    AS total_loss_weight,
  COALESCE(
    SUM(
      CASE WHEN d.price_perkg IS NOT NULL THEN d.loss_cost ELSE 0 END
    ), 0
  ) AS total_loss_cost

FROM cattle_loss_receive l
JOIN weight_cattle w
  ON w.idweigh = l.idweigh
  AND w.is_deleted = 0
JOIN cattle_receive r
  ON r.idreceive = w.idreceive
  AND r.is_deleted = 0
JOIN pocattle p
  ON p.idpo = r.idpo
  AND p.is_deleted = 0
JOIN supplier s
  ON s.idsupplier = p.idsupplier
LEFT JOIN cattle_loss_receive_detail d
  ON d.idloss = l.idloss

WHERE l.is_deleted = 0

GROUP BY
  l.idloss,
  l.loss_no,
  l.loss_date,
  w.weigh_no,
  w.weigh_date,
  r.receipt_date,
  r.doc_no,
  p.nopo,
  s.nmsupplier

ORDER BY
  l.loss_date DESC,
  l.idloss DESC
";

$res = $conn->query($sql);
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12 col-md-3 mb-2">
                    <!-- tombol ke halaman draft -->
                    <a href="draft.php" class="btn btn-sm btn-outline-primary btn-block">
                        <i class="fas fa-file-alt"></i> Draft Loss <span class="badge badge-danger ml-1"><?= $draftloss; ?></span>
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
                                        <th>Supplier</th>
                                        <th>Weighing Date</th>
                                        <th>Receive Date</th>
                                        <th>No PO</th>
                                        <th>Qty (Head)</th>
                                        <th>Loss Wt (Kg)</th>
                                        <th>Loss Cost (Rp)</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    if ($res && $res->num_rows) {
                                        while ($r = $res->fetch_assoc()) {
                                            $idloss = (int)$r['idloss'];

                                            // sementara semua dokumen boleh edit/delete
                                            $editCls   = '';
                                            $delCls    = '';
                                            $editTitle = 'Edit';
                                            $delTitle  = 'Delete';
                                            $editOnClk = '';
                                            $delOnClk  = "return confirm('Hapus dokumen loss ini?')";
                                    ?>
                                            <tr class="text-center">
                                                <td><?= $no++; ?></td>
                                                <td class="text-left"><?= e($r['nmsupplier']); ?></td>
                                                <td><?= tgl($r['weigh_date']); ?></td>
                                                <td><?= tgl($r['receipt_date']); ?></td>
                                                <td><?= e($r['nopo']); ?></td>
                                                <td><?= number_format((int)$r['heads'], 0, ',', '.'); ?></td>
                                                <td class="text-right"><?= n2($r['total_loss_weight']); ?></td>
                                                <td class="text-right"><?= rupiah($r['total_loss_cost']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="view.php?id=<?= $idloss ?>" class="btn btn-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="edit.php?id=<?= $idloss ?>"
                                                            class="btn btn-warning <?= $editCls ?>"
                                                            title="<?= e($editTitle) ?>"
                                                            onclick="<?= $editOnClk ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="delete.php?id=<?= $idloss ?>"
                                                            class="btn btn-danger <?= $delCls ?>"
                                                            title="<?= e($delTitle) ?>"
                                                            onclick="<?= $delOnClk ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="14" class="text-center text-muted">Belum ada dokumen Cattle Weight Loss (Receiving).</td></tr>';
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
    document.title = "Cattle Weight Loss (Receiving)";
</script>

<?php include "../footer.php"; ?>