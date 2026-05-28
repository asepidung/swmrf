<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

// Pastikan ID Stock Take tersedia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Parameter ID tidak valid.");
}

$idst = intval($_GET['id']);

// Ambil data barang yang ada di stock tetapi belum ada di stocktakedetail
$query = "
    SELECT s.kdbarcode, s.idbarang, b.nmbarang, s.idgrade, g.nmgrade, s.qty, s.pcs, s.pod, s.origin 
    FROM stock s
    INNER JOIN barang b ON s.idbarang = b.idbarang
    LEFT JOIN grade g ON s.idgrade = g.idgrade
    WHERE s.kdbarcode NOT IN (SELECT kdbarcode FROM stocktakedetail WHERE idst = ?)
    ORDER BY b.nmbarang ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idst);
$stmt->execute();
$result = $stmt->get_result();

// Hitung jumlah barang yang belum terscan
$total_missing = $result->num_rows;
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-alt-circle-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="row">
                    <div class="col-lg">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Data Yang Belum Berhasil Ter Stock</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($total_missing > 0) { ?>
                                    <table id="example1" class="table table-bordered table-striped table-sm">
                                        <thead class="text-center">
                                            <tr>
                                                <th>#</th>
                                                <!-- <th>Barcode</th> -->
                                                <th>Item</th>
                                                <th>Grade</th>
                                                <th>Weight</th>
                                                <th>Pcs</th>
                                                <th>POD</th>
                                                <th>Origin</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            while ($row = $result->fetch_assoc()) {
                                            ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++; ?></td>
                                                    <!-- <td class="text-center"><?= htmlspecialchars($row['kdbarcode']); ?></td> -->
                                                    <td><?= htmlspecialchars($row['nmbarang']); ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($row['nmgrade'] ?? '-'); ?></td>
                                                    <td class="text-right"><?= $row['qty']; ?></td>
                                                    <td class="text-center"><?= $row['pcs']; ?></td>
                                                    <td class="text-center"><?= date("d-M-Y", strtotime($row['pod'])); ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        $origin_types = [1 => "BONING", 2 => "TRADING", 3 => "REPACK", 4 => "RELABEL", 5 => "IMPORT"];
                                                        echo $origin_types[$row['origin']] ?? "UNKNOWN";
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                <?php } else { ?>
                                    <div class="alert alert-success text-center">
                                        <strong>Semua barang telah terscan! Tidak ada stock yang tertinggal.</strong>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    document.title = "Waiting Product";
</script>
<?php include "../footer.php"; ?>