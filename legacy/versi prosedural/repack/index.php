<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

/* ==========================
   DEFAULT PERIODE
========================== */
$awal  = $_GET['awal']  ?? date('Y-m-01'); // tanggal 1 bulan ini
$akhir = $_GET['akhir'] ?? date('Y-m-d');  // hari ini
?>
<div class="content-wrapper">

    <!-- HEADER -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-3">
                    <a href="newrepack.php" class="btn btn-info">
                        <i class="fas fa-plus-circle"></i> Baru
                    </a>
                </div>

                <!-- FILTER TANGGAL -->
                <div class="col-sm-9">
                    <form method="GET" class="form-inline float-right">
                        <label class="mr-2">Periode</label>
                        <input type="date" name="awal" value="<?= htmlspecialchars($awal) ?>" class="form-control form-control-sm mr-2">
                        <span class="mr-2">s/d</span>
                        <input type="date" name="akhir" value="<?= htmlspecialchars($akhir) ?>" class="form-control form-control-sm mr-2">
                        <button type="submit" class="btn btn-sm btn-primary">
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
            <div class="row">
                <div class="col">
                    <div class="card">
                        <div class="card-body">

                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>No Proses</th>
                                        <th>Tgl Proses</th>
                                        <th>Bahan</th>
                                        <th>Hasil</th>
                                        <th>Balance</th>
                                        <th>Catatan</th>
                                        <th>Status</th>
                                        <th>AKSI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;

                                    $sql = "
                                        SELECT repack.*, users.fullname
                                        FROM repack
                                        INNER JOIN users ON repack.idusers = users.idusers
                                        WHERE repack.is_deleted = 0
                                        AND repack.tglrepack BETWEEN '$awal' AND '$akhir'
                                        ORDER BY repack.idrepack DESC
                                    ";
                                    $ambildata = mysqli_query($conn, $sql);

                                    while ($tampil = mysqli_fetch_assoc($ambildata)):
                                        $idrepack = (int)$tampil['idrepack'];

                                        // TOTAL BAHAN
                                        $qBahan = mysqli_query($conn, "SELECT SUM(qty) total FROM detailbahan WHERE idrepack=$idrepack");
                                        $totalBahan = mysqli_fetch_assoc($qBahan)['total'] ?? 0;

                                        // TOTAL HASIL
                                        $qHasil = mysqli_query($conn, "SELECT SUM(qty) total FROM detailhasil WHERE idrepack=$idrepack AND is_deleted=0");
                                        $totalHasil = mysqli_fetch_assoc($qHasil)['total'] ?? 0;

                                        $lost = $totalHasil - $totalBahan;

                                        // CEK BAHAN
                                        $qDetail = mysqli_query($conn, "SELECT COUNT(*) total FROM detailbahan WHERE idrepack=$idrepack");
                                        $adaBahan = mysqli_fetch_assoc($qDetail)['total'] ?? 0;

                                        $isLockedOrApproved = ($tampil['kunci'] >= 1);

                                        $disabledBahan = $isLockedOrApproved
                                            ? 'disabled style="pointer-events:none;opacity:0.5;"'
                                            : '';

                                        $disabledHasil = ($isLockedOrApproved || $adaBahan == 0)
                                            ? 'disabled style="pointer-events:none;opacity:0.5;"'
                                            : '';
                                    ?>
                                        <tr class="text-center align-middle">
                                            <td><?= $no++; ?></td>

                                            <td>
                                                <a href="laporan_rawusage_repack.php?id=<?= $idrepack ?>" class="font-weight-bold text-primary">
                                                    <?= htmlspecialchars($tampil['norepack']); ?>
                                                    <i class="fas fa-link small"></i>
                                                </a>
                                            </td>

                                            <td><?= date("d-M-y", strtotime($tampil['tglrepack'])); ?></td>
                                            <td class="text-right"><?= number_format($totalBahan, 2); ?></td>
                                            <td class="text-right"><?= number_format($totalHasil, 2); ?></td>
                                            <td class="text-right">
                                                <?= ($lost < 0)
                                                    ? "<span class='text-danger'>" . number_format($lost, 2) . "</span>"
                                                    : number_format($lost, 2); ?>
                                            </td>
                                            <td class="text-left"><?= htmlspecialchars($tampil['note']); ?></td>

                                            <!-- STATUS -->
                                            <td>
                                                <?php
                                                $idusers = $_SESSION['idusers'] ?? 0;
                                                if ($tampil['kunci'] == 0) {
                                                    echo '<span class="badge badge-secondary">On Process</span>';
                                                } elseif ($tampil['kunci'] == 1) {
                                                    echo ($idusers == 1 || $idusers == 2)
                                                        ? '<a href="lockrepack.php?id=' . $idrepack . '" class="badge badge-warning text-dark">LOCK</a>'
                                                        : '<span class="badge badge-success">Approved</span>';
                                                } else {
                                                    echo ($idusers == 1 || $idusers == 2)
                                                        ? '<a href="unlockrepack.php?id=' . $idrepack . '" class="badge badge-danger">LOCKED</a>'
                                                        : '<span class="badge badge-dark">Locked</span>';
                                                }
                                                ?>
                                            </td>

                                            <!-- AKSI -->
                                            <td>
                                                <div class="btn-group btn-group-sm">

                                                    <?php if ($tampil['kunci'] == 0): ?>
                                                        <a href="approverepack.php?id=<?= $idrepack ?>" class="btn btn-outline-primary">
                                                            <i class="far fa-calendar-check"></i>
                                                        </a>
                                                    <?php elseif ($tampil['kunci'] == 1): ?>
                                                        <a href="unapproverepack.php?id=<?= $idrepack ?>" class="btn btn-outline-danger">
                                                            <i class="fas fa-calendar-times"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled>
                                                            <i class="far fa-calendar-check"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <a href="detailbahan.php?id=<?= $idrepack ?>&stat=ready"
                                                        class="btn btn-outline-warning" <?= $disabledBahan; ?>>
                                                        <i class="fas fa-box-open"></i>
                                                    </a>

                                                    <a href="detailhasil.php?id=<?= $idrepack ?>"
                                                        class="btn btn-outline-success" <?= $disabledHasil; ?>>
                                                        <i class="fas fa-tags"></i>
                                                    </a>

                                                    <a href="lihatrepack.php?id=<?= $idrepack ?>" class="btn btn-outline-secondary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <a href="editrepack.php?id=<?= $idrepack ?>"
                                                        class="btn btn-outline-dark"
                                                        <?= ($tampil['kunci'] == 2) ? 'disabled style="opacity:0.5;"' : ''; ?>>
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.title = "Data Repack";
</script>
<?php include "../footer.php"; ?>