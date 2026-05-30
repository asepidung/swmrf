<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

// Pastikan ID Stock Take tersedia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Parameter ID tidak valid.");
}

$idst = intval($_GET['id']);

// Ambil data dari tabel missing_stock
$query_missing = "
    SELECT m.kdbarcode, m.idbarang, b.nmbarang, m.idgrade, g.nmgrade, m.qty, m.pcs, m.pod, m.origin 
    FROM missing_stock m
    INNER JOIN barang b ON m.idbarang = b.idbarang
    LEFT JOIN grade g ON m.idgrade = g.idgrade
    WHERE m.idst = ?
    ORDER BY b.nmbarang ASC
";
$stmt_missing = $conn->prepare($query_missing);
$stmt_missing->bind_param("i", $idst);
$stmt_missing->execute();
$result_missing = $stmt_missing->get_result();
$total_missing = $result_missing->num_rows;

// Ambil data dari tabel manualstock
$query_manual = "
    SELECT m.kdbarcode, m.idbarang, b.nmbarang, m.idgrade, g.nmgrade, m.qty, m.pcs, m.pod, m.origin 
    FROM manualstock m
    INNER JOIN barang b ON m.idbarang = b.idbarang
    LEFT JOIN grade g ON m.idgrade = g.idgrade
    WHERE m.idst = ?
    ORDER BY b.nmbarang ASC
";
$stmt_manual = $conn->prepare($query_manual);
$stmt_manual->bind_param("i", $idst);
$stmt_manual->execute();
$result_manual = $stmt_manual->get_result();
$total_manual = $result_manual->num_rows;
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
                    <!-- Tabel 1: Data Missing Stock -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">ADA DI SYSTEM TIDAK ADA DI FISIK</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($total_missing > 0) { ?>
                                    <table id="example1" class="table table-bordered table-striped table-sm">
                                        <thead class="text-center">
                                            <tr>
                                                <th>#</th>
                                                <th>Barcode</th>
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
                                            while ($row = $result_missing->fetch_assoc()) {
                                            ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++; ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($row['kdbarcode']); ?></td>
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
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                <?php } else { ?>
                                    <div class="alert alert-success text-center">
                                        <strong>Tidak ada barang yang hilang dalam stock opname!</strong>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel 2: Data Manual Stock -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">ADA DI FISIK TIDAK ADA DI SYSTEM</h3>
                            </div>
                            <div class="card-body">
                                <?php if ($total_manual > 0) { ?>
                                    <table id="example2" class="table table-bordered table-striped table-sm">
                                        <thead class="text-center">
                                            <tr>
                                                <th>#</th>
                                                <th>Barcode</th>
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
                                            while ($row = $result_manual->fetch_assoc()) {
                                            ?>
                                                <tr>
                                                    <td class="text-center"><?= $no++; ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($row['kdbarcode']); ?></td>
                                                    <td><?= htmlspecialchars($row['nmbarang']); ?></td>
                                                    <td class="text-center"><?= htmlspecialchars($row['nmgrade'] ?? '-'); ?></td>
                                                    <td class="text-right"><?= $row['qty']; ?></td>
                                                    <td class="text-center"><?= $row['pcs']; ?></td>
                                                    <td class="text-center"><?= date("d-M-Y", strtotime($row['pod'])); ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        echo $origin_types[$row['origin']] ?? "UNKNOWN";
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                <?php } else { ?>
                                    <div class="alert alert-success text-center">
                                        <strong>Tidak ada data manual stock ketika opname!</strong>
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
    document.title = "Missing & Founding";
</script>
<?php include "../footer.php"; ?>