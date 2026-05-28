<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

/* ================= FILTER TANGGAL ================= */
$awal  = $_GET['awal']  ?? date('Y-m-01');
$akhir = $_GET['akhir'] ?? date('Y-m-d');

$whereTanggal = " AND r.returdate BETWEEN '$awal' AND '$akhir' ";
?>
<div class="content-wrapper">

    <!-- HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">

                <div class="col-sm-3 col-12 mb-2">
                    <a href="create.php" class="btn btn-outline-primary btn-block btn-sm">
                        <i class="fas fa-plus"></i> Baru
                    </a>
                </div>

                <div class="col-sm-9 col-12">
                    <form method="GET" class="form-inline justify-content-sm-end flex-wrap">
                        <label class="mr-2 font-weight-bold">Periode</label>

                        <input type="date" name="awal"
                            value="<?= htmlspecialchars($awal) ?>"
                            class="form-control form-control-sm mr-2 mb-1">

                        <span class="mr-2 mb-1">s/d</span>

                        <input type="date" name="akhir"
                            value="<?= htmlspecialchars($akhir) ?>"
                            class="form-control form-control-sm mr-2 mb-1">

                        <button type="submit" class="btn btn-sm btn-primary mb-1">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-body table-responsive">

                    <table id="example1" class="table table-bordered table-striped table-sm mb-0">
                        <thead class="text-center">
                            <tr>
                                <th>#</th>
                                <th>Return Number</th>
                                <th>Customer</th>
                                <th>DO Number</th>
                                <th>Total Qty</th>
                                <th>Status</th>
                                <th>Made By</th>
                                <th style="width:260px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;

                            $sql = "
                                SELECT 
                                    r.*,
                                    c.nama_customer,
                                    u.fullname,
                                    IFNULL(SUM(d.qty),0) AS total_qty
                                FROM returjual r
                                JOIN customers c ON r.idcustomer = c.idcustomer
                                LEFT JOIN users u ON r.idusers = u.idusers
                                LEFT JOIN returjualdetail d 
                                       ON d.idreturjual = r.idreturjual 
                                       AND d.is_deleted = 0
                                WHERE r.is_deleted = 0
                                $whereTanggal
                                GROUP BY r.idreturjual
                                ORDER BY r.idreturjual DESC
                            ";

                            $q = mysqli_query($conn, $sql);

                            if (mysqli_num_rows($q) == 0) {
                                echo '<tr><td colspan="8" class="text-center text-muted">Data tidak ditemukan</td></tr>';
                            }

                            while ($r = mysqli_fetch_assoc($q)) {
                                $status = $r['status'];
                            ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>

                                    <td class="text-center font-weight-bold">
                                        <?= htmlspecialchars($r['returnnumber']) ?>
                                    </td>

                                    <td><?= htmlspecialchars($r['nama_customer']) ?></td>

                                    <td class="text-center">
                                        <?= htmlspecialchars($r['donumber'] ?? '-') ?>
                                    </td>

                                    <td class="text-right font-weight-bold">
                                        <?= number_format($r['total_qty'], 2) ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($status === 'POSTED'): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-lock"></i> POSTED
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-edit"></i> DRAFT
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?= htmlspecialchars($r['fullname'] ?? '-') ?>
                                    </td>

                                    <td class="text-center">

                                        <?php if ($status === 'DRAFT'): ?>

                                            <!-- SCAN -->
                                            <a href="scan_retur.php?idreturjual=<?= $r['idreturjual'] ?>"
                                                class="btn btn-sm btn-warning mb-1"
                                                title="Scan Barcode">
                                                <i class="fas fa-barcode"></i>
                                            </a>

                                            <!-- LABEL -->
                                            <a href="label_retur.php?idreturjual=<?= $r['idreturjual'] ?>"
                                                class="btn btn-sm btn-info mb-1"
                                                title="Cetak Label">
                                                <i class="fas fa-tag"></i>
                                            </a>

                                            <!-- POST -->
                                            <a href="post_retur.php?idreturjual=<?= $r['idreturjual'] ?>"
                                                class="btn btn-sm btn-primary mb-1"
                                                onclick="return confirm('Proses retur ke stock? Setelah POSTED data tidak bisa diubah.')"
                                                title="Proses ke Stock">
                                                <i class="fas fa-check"></i>
                                            </a>

                                            <!-- DELETE -->
                                            <a href="delete.php?idreturjual=<?= $r['idreturjual'] ?>"
                                                class="btn btn-sm btn-danger mb-1"
                                                onclick="return confirm('Yakin hapus retur ini?')"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>

                                        <?php else: ?>

                                            <!-- SCAN LOCK -->
                                            <button class="btn btn-sm btn-secondary mb-1" disabled
                                                title="Data sudah POSTED">
                                                <i class="fas fa-barcode"></i>
                                            </button>

                                            <!-- LABEL LOCK -->
                                            <button class="btn btn-sm btn-secondary mb-1" disabled
                                                title="Data sudah POSTED">
                                                <i class="fas fa-tag"></i>
                                            </button>

                                        <?php endif; ?>

                                        <!-- VIEW (SELALU AKTIF) -->
                                        <a href="view.php?idreturjual=<?= $r['idreturjual'] ?>"
                                            class="btn btn-sm btn-success mb-1"
                                            title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                </div>
            </div>

        </div>
    </section>
</div>

<script>
    document.title = "Sales Return";
</script>

<?php include "../footer.php"; ?>