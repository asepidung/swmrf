<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idstockout = (int)($_GET['id'] ?? 0);
if ($idstockout <= 0) {
    die("ID dokumen tidak valid.");
}

/* ================================
   Ambil HEADER
================================ */
$stmtH = $conn->prepare("
    SELECT 
        nostockout,
        tgl,
        kegiatan,
        ref_no,
        kegiatan_note
    FROM raw_stock_out
    WHERE idstockout = ? AND is_deleted = 0
    LIMIT 1
");
$stmtH->bind_param("i", $idstockout);
$stmtH->execute();
$h = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$h) {
    die("Dokumen tidak ditemukan atau sudah dihapus.");
}

$nostockout    = $h['nostockout'];
$tgl           = $h['tgl'];
$kegiatan      = $h['kegiatan'];
$ref_no        = $h['ref_no'];
$kegiatan_note = $h['kegiatan_note'];

/* ================================
   Ambil DETAIL
================================ */
$stmtD = $conn->prepare("
    SELECT 
        d.idrawmate,
        COALESCE(rm.nmrawmate, CONCAT('ID#', d.idrawmate)) AS nmrawmate,
        d.qty,
        d.note
    FROM raw_stock_out_detail d
    LEFT JOIN rawmate rm ON rm.idrawmate = d.idrawmate
    WHERE d.idstockout = ?
    ORDER BY nmrawmate ASC
");
$stmtD->bind_param("i", $idstockout);
$stmtD->execute();
$res = $stmtD->get_result();

$rows  = [];
$grand = 0;
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $grand += (float)$r['qty'];
}
$stmtD->close();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h4>
                        <i class="fas fa-clipboard-list"></i>
                        Pengeluaran Material — <?= htmlspecialchars($nostockout) ?>
                    </h4>
                    <div class="small text-muted">
                        Tanggal: <?= htmlspecialchars(date('d-M-Y', strtotime($tgl))) ?>
                        · Kegiatan:
                        <b><?= htmlspecialchars($kegiatan) ?></b>
                        <?php if ($kegiatan === 'LAINNYA'): ?>
                            · <?= htmlspecialchars($kegiatan_note) ?>
                        <?php else: ?>
                            · Ref: <?= htmlspecialchars($ref_no) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-dark shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Detail Pemakaian Material</h3>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="stockOutView" class="table table-bordered table-striped table-sm">
                            <thead class="text-center">
                                <tr>
                                    <th width="60">#</th>
                                    <th>Material</th>
                                    <th width="160">Qty Terpakai</th>
                                    <th width="200">Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1;
                                foreach ($rows as $r): ?>
                                    <tr>
                                        <td class="text-center"><?= $no++ ?></td>
                                        <td><?= htmlspecialchars($r['nmrawmate']) ?></td>
                                        <td class="text-right">
                                            <?= number_format((float)$r['qty'], 2) ?>
                                        </td>
                                        <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2" class="text-right">TOTAL PEMAKAIAN</th>
                                    <th class="text-right"><?= number_format($grand, 2) ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>

<script>
    document.title = "Pengeluaran Material <?= htmlspecialchars($nostockout) ?>";

    $(function() {
        $("#stockOutView").DataTable({
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            ordering: false,
            paging: false,
            searching: true,
            info: false,
            buttons: ["copy", "excel", "pdf", "print"]
        }).buttons().container().appendTo('#stockOutView_wrapper .col-md-6:eq(0)');
    });
</script>

<?php include "../footnote.php"; ?>
<?php include "../footer.php"; ?>