<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

/* ================================
   Ambil data stock out (aktif)
================================ */
$sql = "
    SELECT 
        so.idstockout,
        so.nostockout,
        so.tgl,
        so.kegiatan,
        so.ref_no,
        so.kegiatan_note,
        COUNT(DISTINCT sod.idrawmate) AS jml_material,
        COALESCE(SUM(sod.qty),0) AS total_qty
    FROM raw_stock_out so
    LEFT JOIN raw_stock_out_detail sod 
        ON sod.idstockout = so.idstockout
    WHERE so.is_deleted = 0
    GROUP BY 
        so.idstockout,
        so.nostockout,
        so.tgl,
        so.kegiatan,
        so.ref_no,
        so.kegiatan_note
    ORDER BY so.tgl DESC, so.idstockout DESC
";

$res = mysqli_query($conn, $sql);

$msg = $_GET['msg'] ?? '';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h4><i class="fas fa-box-open"></i> Pengeluaran Material Pendukung</h4>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="create.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus-circle"></i> Tambah Pengeluaran
                    </a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success py-2">
                    <?= htmlspecialchars($msg, ENT_QUOTES) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="card card-dark shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Daftar Pengeluaran Material</h3>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="stockOutTable" class="table table-bordered table-striped table-sm">
                            <thead class="text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Tanggal</th>
                                    <th>No Dokumen</th>
                                    <th>Kegiatan</th>
                                    <th>Referensi</th>
                                    <th>Jml Material</th>
                                    <th>Total Qty</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1;
                                while ($r = mysqli_fetch_assoc($res)): ?>
                                    <tr class="align-middle">
                                        <td class="text-center"><?= $no++ ?></td>

                                        <td class="text-center">
                                            <?= htmlspecialchars(date('d-M-y', strtotime($r['tgl']))) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($r['nostockout']) ?>
                                        </td>

                                        <td class="text-center">
                                            <?php if ($r['kegiatan'] === 'BONING'): ?>
                                                <span class="badge badge-info">BONING</span>
                                            <?php elseif ($r['kegiatan'] === 'REPACK'): ?>
                                                <span class="badge badge-success">REPACK</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">LAINNYA</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php
                                            if ($r['kegiatan'] === 'LAINNYA') {
                                                echo htmlspecialchars($r['kegiatan_note']);
                                            } else {
                                                echo htmlspecialchars($r['ref_no']);
                                            }
                                            ?>
                                        </td>

                                        <td class="text-center">
                                            <?= (int)$r['jml_material'] ?>
                                        </td>

                                        <td class="text-right">
                                            <?= number_format((float)$r['total_qty'], 2) ?>
                                        </td>

                                        <td class="text-center">
                                            <a href="view.php?id=<?= (int)$r['idstockout'] ?>"
                                                class="btn btn-outline-info btn-sm" title="Lihat">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= (int)$r['idstockout'] ?>"
                                                class="btn btn-outline-warning btn-sm" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?= (int)$r['idstockout'] ?>"
                                                class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Yakin ingin menghapus data ini?')"
                                                title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>

                            <tfoot>
                                <?php
                                $qGrand = mysqli_query($conn, "
                                    SELECT COALESCE(SUM(qty),0) AS gqty
                                    FROM raw_stock_out_detail d
                                    JOIN raw_stock_out h ON h.idstockout = d.idstockout
                                    WHERE h.is_deleted = 0
                                ");
                                $grand = mysqli_fetch_assoc($qGrand)['gqty'] ?? 0;
                                ?>
                                <tr>
                                    <th colspan="6" class="text-right">GRAND TOTAL QTY</th>
                                    <th class="text-right"><?= number_format((float)$grand, 2) ?></th>
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
    document.title = "Pengeluaran Material Pendukung";

    $(function() {
        $("#stockOutTable").DataTable({
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            ordering: false,
            paging: true,
            pageLength: 25,
            searching: true,
            info: true,
            buttons: ["copy", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#stockOutTable_wrapper .col-md-6:eq(0)');
    });
</script>

<?php include "../footnote.php"; ?>
<?php include "../footer.php"; ?>