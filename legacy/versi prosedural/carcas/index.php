<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function tgl($d)
{
    return $d ? date('d-M-Y', strtotime($d)) : '-';
}

// Inisialisasi Tanggal Awal dan Akhir
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');

// ====================
// Query utama CARCASE
// ====================
$sql = "
SELECT
    c.idcarcase,
    c.killdate,
    s.nmsupplier,
    u.fullname,
    COALESCE(SUM(cd.berat), 0)                        AS total_berat,
    COUNT(cd.iddetail)                                AS total_eartag,
    COALESCE(SUM(cd.carcase1 + cd.carcase2), 0)       AS total_carcase,
    COALESCE(SUM(cd.hides + cd.tail), 0)              AS total_carcase_tail,
    COALESCE(SUM(cd.hides), 0)                        AS total_hides,
    COALESCE(SUM(cd.tail), 0)                         AS total_tails
FROM carcase c
LEFT JOIN supplier s
       ON s.idsupplier = c.idsupplier
LEFT JOIN users u
       ON u.idusers = c.idusers
LEFT JOIN carcasedetail cd
       ON cd.idcarcase = c.idcarcase
WHERE c.is_deleted = 0 AND c.killdate BETWEEN '$awal' AND '$akhir'
GROUP BY
    c.idcarcase,
    c.killdate,
    s.nmsupplier,
    u.fullname
ORDER BY
    c.killdate DESC,
    c.idcarcase DESC
";

$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . e($conn->error));
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">

                <!-- FILTER PERIODE -->
                <div class="col-sm-9 col-12">
                    <form method="GET" class="form-inline flex-wrap">
                        <label class="mr-2 font-weight-bold d-none d-sm-inline">Periode</label>

                        <input type="date"
                            name="awal"
                            value="<?= e($awal); ?>"
                            class="form-control form-control-sm mr-sm-2 mb-1">

                        <span class="mr-sm-2 mb-1">s/d</span>

                        <input type="date"
                            name="akhir"
                            value="<?= e($akhir); ?>"
                            class="form-control form-control-sm mr-sm-2 mb-1">

                        <button type="submit"
                            class="btn btn-sm btn-primary mb-1"
                            name="search">
                            <i class="fas fa-search"></i> Cari
                        </button>
                    </form>
                </div>

                <!-- Tombol Draft dari Weighing -->
                <div class="col-sm-3 col-12 text-sm-right mt-2 mt-sm-0">
                    <a href="draft.php" class="btn btn-sm btn-outline-primary btn-block btn-sm-inline">
                        <i class="fas fa-file-alt"></i> Draft from Weighing
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

                            <table id="example1" class="table table-bordered table-striped table-sm text-right">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Killing Date</th>
                                        <th>Supplier</th>
                                        <th>Berat &Sigma;</th>
                                        <th>Head &Sigma;</th>
                                        <th>Carcase &Sigma;</th>
                                        <th>Offal</th>
                                        <th>Hides &Sigma;</th>
                                        <th>Tails &Sigma;</th>
                                        <th>Carcase %</th>
                                        <th>User</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Variabel penampung grand total
                                    $sum_berat = 0;
                                    $sum_head = 0;
                                    $sum_carcase = 0;
                                    $sum_offal = 0;
                                    $sum_hides = 0;
                                    $sum_tails = 0;

                                    if ($result->num_rows > 0) {
                                        $no = 1;
                                        while ($row = $result->fetch_assoc()) {
                                            $total_berat = (float)$row['total_berat'];
                                            $total_carcase = (float)$row['total_carcase'];
                                            $total_tails = (float)$row['total_tails'];

                                            // Offal = total carcase + total tails (tanpa hides)
                                            $offal = $total_carcase + $total_tails;

                                            $carcase_percentage = 0;
                                            if ($total_berat > 0) {
                                                $carcase_percentage = ($total_carcase / $total_berat) * 100;
                                            }

                                            // Akumulasi total
                                            $sum_berat += $total_berat;
                                            $sum_head += (int)$row['total_eartag'];
                                            $sum_carcase += $total_carcase;
                                            $sum_offal += $offal;
                                            $sum_hides += (float)$row['total_hides'];
                                            $sum_tails += $total_tails;
                                    ?>
                                            <tr>
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td class="text-center"><?= e(tgl($row['killdate'])) ?></td>
                                                <td class="text-left"><?= e($row['nmsupplier'] ?? '-') ?></td>
                                                <td><?= number_format($total_berat, 2) ?></td>
                                                <td class="text-center"><?= (int)$row['total_eartag'] ?></td>
                                                <td><?= number_format($total_carcase, 2) ?></td>
                                                <td><?= number_format($offal, 2) ?></td>
                                                <td><?= number_format((float)$row['total_hides'], 2) ?></td>
                                                <td><?= number_format($total_tails, 2) ?></td>
                                                <td><?= number_format($carcase_percentage, 2) ?></td>
                                                <td class="text-left"><?= e($row['fullname'] ?? '-') ?></td>
                                                <td class="text-center">
                                                    <a href="view.php?id=<?= (int)$row['idcarcase'] ?>" class="btn btn-info btn-sm" title="Lihat">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?= (int)$row['idcarcase'] ?>" class="btn btn-warning btn-sm" title="Edit">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </a>
                                                    <a href="delete.php?id=<?= (int)$row['idcarcase'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')" class="btn btn-danger btn-sm" title="Hapus">
                                                        <i class="fas fa-minus-square"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo "<tr><td colspan='12' class='text-center text-muted'>Tidak ada data ditemukan</td></tr>";
                                    }
                                    ?>
                                </tbody>

                                <!-- FOOTER GRAND TOTAL -->
                                <?php if ($result->num_rows > 0): ?>
                                    <tfoot class="text-right font-weight-bold bg-light">
                                        <tr>
                                            <td colspan="3" class="text-center">GRAND TOTAL</td>
                                            <td><?= number_format($sum_berat, 2) ?></td>
                                            <td class="text-center"><?= $sum_head ?></td>
                                            <td><?= number_format($sum_carcase, 2) ?></td>
                                            <td><?= number_format($sum_offal, 2) ?></td>
                                            <td><?= number_format($sum_hides, 2) ?></td>
                                            <td><?= number_format($sum_tails, 2) ?></td>
                                            <td>
                                                <?php
                                                // Persentase Carcase keseluruhan
                                                $total_percentage = ($sum_berat > 0) ? ($sum_carcase / $sum_berat) * 100 : 0;
                                                echo number_format($total_percentage, 2);
                                                ?>
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                <?php endif; ?>

                            </table>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.title = "Data Carcas";
</script>

<?php include "../footer.php"; ?>