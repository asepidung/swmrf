sekarang amati dengan seksama kode berikut

<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
$idtally = $_GET['id'];

$querytally = "SELECT tally.*, customers.nama_customer
            FROM tally
            INNER JOIN customers ON tally.idcustomer = customers.idcustomer
            WHERE tally.idtally = $idtally";
$resulttally = mysqli_query($conn, $querytally);
$rowtally = mysqli_fetch_assoc($resulttally);
$idso = $rowtally['idso'];

// Periksa apakah session 'limit' telah di-set
$defaultLimit = 14; // Nilai default untuk limit jika session belum di-set
if (!isset($_SESSION['limit'])) {
    $_SESSION['limit'] = $defaultLimit;
}
$limit = $_SESSION['limit'];
?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <a href="index.php"><button type="button" class="btn btn-outline-primary"><i class="fas fa-arrow-alt-circle-left"></i> Back To List</button></a>
                <a href="approvetally.php?id=<?= htmlspecialchars($rowtally['idtally']) ?>&idso=<?= htmlspecialchars($idso) ?>"><button type="button" class="btn btn-outline-success"><i class="fas fa-check-circle"></i> Approve</button></a>
                <span class="text-info float-right">
                    <?php if ($rowtally['nama_customer'] == "ASEP OFFAL") { ?>
                        <a href="istimewa.php?idso=<?= $idso ?>&idtally=<?= $idtally ?>">
                            <h4><?= $rowtally['nama_customer']; ?></h4>
                        </a>
                    <?php  } else { ?>
                        <h4><?= $rowtally['nama_customer']; ?></h4>
                    <?php } ?>
                </span>
            </div>
        </div>
    </div>
</div>
<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <form method="POST" action="inputtallydetail.php">
                    <div class="card">
                        <div class="card-body">
                            <div id="items-container">
                                <div class="row mb-n2">
                                    <div class="col-xs-2">
                                        <div class="form-group">
                                            <input type="number" placeholder="Scan Here" class="form-control text-center" name="barcode" id="barcode" autofocus>
                                        </div>
                                    </div>
                                    <input type="hidden" name="idtally" value="<?= $idtally ?>">
                                    <div class="col-1">
                                        <div class="form-group">
                                            <button type="submit" class="btn btn-primary">Submit</button>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <?php if ($_GET['stat'] == "success") { ?>
                                            <h3 class="headline text-success"><i class="fas fa-check-circle"></i> Success</h3>
                                        <?php } elseif ($_GET['stat'] == "ready") { ?>
                                            <h3 class="headline text-secondary"> Ready To Scan</h3>
                                        <?php } elseif ($_GET['stat'] == "updated") { ?>
                                            <h3 class="headline text-success"> Data Updated</h3>
                                        <?php } elseif ($_GET['stat'] == "manual") { ?>
                                            <h3 class="headline text-danger"> Silahkan Scan Ulang !</h3>
                                        <?php } elseif ($_GET['stat'] == "deleted") { ?>
                                            <h3 class="headline text-success"> Data berhasil dihapus</h3>
                                        <?php } elseif ($_GET['stat'] == "duplicate") { ?>
                                            <h3 class="headline text-warning"><i class="fas fa-exclamation-triangle"></i> Barang Sudah Terinput</h3>
                                        <?php } elseif ($_GET['stat'] == "unlisted") { ?>
                                            <h3 class="headline text-danger"><i class="fas fa-times-circle"></i> Barang Tidak ada di PO</h3>
                                        <?php } elseif ($_GET['stat'] == "unknown") { ?>
                                            <a href="tallymanual.php?id=<?= $idtally ?>">
                                                <span class="headline text-danger">BARANG TIDAK TERDAFTAR <br>
                                                    Stock In Manual <i class="fas fa-arrow-circle-right"></i>
                                                </span>
                                            </a>
                                        <?php } ?>
                                    </div>
                                    <div class="col-1">
                                        <input type="number" class="form-control" name="limit" value="<?= htmlspecialchars($limit) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="row">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <table id="example1" class="table table-bordered table-striped table-sm">
                                    <thead class="text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>Barcode</th>
                                            <th>Item</th>
                                            <th>Code</th>
                                            <th>Weight</th>
                                            <th>Pcs</th>
                                            <th>POD</th>
                                            <th>Origin</th>
                                            <th>Hapus</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1;
                                        $ambildata = mysqli_query($conn, "SELECT tallydetail.*, barang.nmbarang, grade.nmgrade
                              FROM tallydetail
                              INNER JOIN barang ON tallydetail.idbarang = barang.idbarang
                              INNER JOIN grade ON tallydetail.idgrade = grade.idgrade
                              WHERE idtally = $idtally ORDER BY idtallydetail DESC");
                                        while ($tampil = mysqli_fetch_array($ambildata)) {
                                            $origin = $tampil['origin'];
                                            $nmbarang = $tampil['nmbarang'];
                                            $nmgrade = $tampil['nmgrade'];
                                            $barcode = $tampil['barcode'];
                                            $pod = $tampil['pod'];
                                            $podDate = new DateTime($pod);
                                            $today = new DateTime();
                                            $interval = $today->diff($podDate);
                                            $daysDiff = $interval->days;
                                        ?>
                                            <tr class="text-center">
                                                <td><?= $no; ?></td>
                                                <td><?= $barcode; ?></td>
                                                <td class="text-left"><?= $nmbarang; ?></td>
                                                <td><?= $nmgrade; ?></td>
                                                <td><?= number_format($tampil['weight'], 2); ?></td>
                                                <?php
                                                if ($tampil['pcs'] < 1) {
                                                    $pcs = "";
                                                } else {
                                                    $pcs = $tampil['pcs'];
                                                }
                                                ?>
                                                <td><?= $pcs; ?></td>
                                                <?php
                                                if ($daysDiff >= $_SESSION['limit']) { ?>
                                                    <td>
                                                        <a href="editlabel.php?kdbarcode=<?= $barcode ?>&idtally=<?= $idtally ?>" class="text-danger">
                                                            <?= $daysDiff . " " . "Hari"; ?>
                                                        </a>
                                                    </td>
                                                <?php } else { ?>
                                                    <td>
                                                        <a href="editlabel.php?kdbarcode=<?= $barcode ?>&idtally=<?= $idtally ?>">
                                                            <?= $daysDiff . " " . "Hari"; ?>
                                                        </a>
                                                    </td>
                                                <?php  } ?>
                                                <td>
                                                    <?php
                                                    if ($origin == 1) {
                                                        echo "BONING";
                                                    } elseif ($origin == 2) {
                                                        echo "TRADING";
                                                    } elseif ($origin == 3) {
                                                        echo "REPACK";
                                                    } elseif ($origin == 4) {
                                                        echo "RELABEL";
                                                    } elseif ($origin == 5) {
                                                        echo "IMPORT";
                                                    } elseif ($origin == 6) {
                                                        echo "RTN";
                                                    } else {
                                                        echo "Unindentified";
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="deletetallydetail.php?iddetail=<?= $tampil['idtallydetail']; ?>&id=<?= $idtally; ?>" class="text-info" onclick="return confirm('Yakin Lu?')">
                                                        <i class="far fa-times-circle"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php
                                            $no++;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-bordered table-striped table-sm">
                                    <thead class="text-center">
                                        <tr>
                                            <th>Prod</th>
                                            <th>PO</th>
                                            <th>Qty</th>
                                            <th>Box</th>
                                            <th>Balance</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $idso_query = "SELECT idso FROM tally WHERE idtally = $idtally";
                                        $idso_result = mysqli_query($conn, $idso_query);

                                        if ($idso_result && $idso_row = mysqli_fetch_assoc($idso_result)) {
                                            $idso = $idso_row['idso'];
                                            $query = "SELECT sodetail.idbarang, barang.nmbarang, sodetail.weight, sodetail.notes
                                 FROM salesorderdetail AS sodetail
                                 INNER JOIN barang ON sodetail.idbarang = barang.idbarang
                                 WHERE sodetail.idso = $idso";
                                            $result = mysqli_query($conn, $query);
                                            while ($row = mysqli_fetch_assoc($result)) { ?>
                                                <tr>
                                                    <td class="ml-1"> <?= $row['nmbarang'] ?></td>
                                                    <td class="text-center"><?= number_format($row['weight'], 2) ?></td>
                                                    <td class="text-right">
                                                        <?php
                                                        $totalWeightQuery = "SELECT SUM(weight) AS total_weight
                                       FROM tallydetail
                                       WHERE idtally = $idtally AND idbarang = " . $row['idbarang'];
                                                        $totalWeightResult = mysqli_query($conn, $totalWeightQuery);
                                                        if ($totalWeightResult && $totalWeightRow = mysqli_fetch_assoc($totalWeightResult)) {
                                                            // Menggunakan null coalescing operator untuk memastikan nilai default jika null
                                                            $totalWeight = $totalWeightRow['total_weight'] ?? 0; // default ke 0 jika null
                                                            echo number_format($totalWeight, 2); // Format dengan dua desimal
                                                        } else {
                                                            echo "0"; // Jika tidak ada data, tampilkan 0
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php
                                                        $totalCountQuery = "SELECT COUNT(weight) AS total_weight
                                          FROM tallydetail
                                          WHERE idtally = $idtally AND idbarang = " . $row['idbarang'];
                                                        $totalCountResult = mysqli_query($conn, $totalCountQuery);
                                                        if ($totalCountResult && $totalCountRow = mysqli_fetch_assoc($totalCountResult)) {
                                                            echo $totalCountRow['total_weight'];
                                                        } else {
                                                            echo "0"; // Jika tidak ada data, tampilkan 0
                                                        }
                                                        ?>
                                                    </td>
                                                    <?php
                                                    $po = $row['weight'];
                                                    $scan = $totalWeightRow['total_weight'];
                                                    $sisa = $totalWeightRow['total_weight'] - $row['weight'];
                                                    ?>
                                                    <td class="text-right">
                                                        <?php if ($sisa > 0) { ?>
                                                            <span class="text-danger"><?= number_format($sisa, 2); ?></span>
                                                        <?php } else { ?>
                                                            <?= number_format($sisa, 2); ?>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <?= $row['notes']; ?>
                                                    </td>
                                                </tr>
                                        <?php }
                                        }
                                        ?>
                                    </tbody>
                                    <?php
                                    $totalPOQuery = "SELECT SUM(weight) AS total_weight FROM salesorderdetail WHERE idso = $idso";
                                    $totalPOResult = mysqli_query($conn, $totalPOQuery);
                                    if ($totalPOResult && $totalPORow = mysqli_fetch_assoc($totalPOResult)) {
                                        $totalPO = $totalPORow['total_weight'];
                                    } else {
                                        $totalPO = 0; // Atur ke 0 jika tidak ada hasil
                                    }

                                    $totalQtyQuery = "SELECT SUM(weight) AS total_qty FROM tallydetail WHERE idtally = $idtally";
                                    $totalQtyResult = mysqli_query($conn, $totalQtyQuery);
                                    if ($totalQtyResult && $totalQtyRow = mysqli_fetch_assoc($totalQtyResult)) {
                                        $totalQty = $totalQtyRow['total_qty'];
                                    } else {
                                        $totalQty = 0; // Atur ke 0 jika tidak ada hasil
                                    }

                                    $totalBoxQuery = "SELECT COUNT(weight) AS total_box FROM tallydetail WHERE idtally = $idtally";
                                    $totalBoxResult = mysqli_query($conn, $totalBoxQuery);
                                    if ($totalBoxResult && $totalBoxRow = mysqli_fetch_assoc($totalBoxResult)) {
                                        $totalBox = $totalBoxRow['total_box'];
                                    } else {
                                        $totalBox = 0; // Atur ke 0 jika tidak ada hasil
                                    }
                                    $totalBalance = $totalQty - $totalPO;
                                    ?>
                                    <tfoot>
                                        <tr class="text-right">
                                            <th>Total</th>
                                            <th class="text-center"><?= number_format($totalPO); ?></th>
                                            <th><?= number_format($totalQty ?? 0, 2); ?></th>
                                            <th class="text-center"><?= number_format($totalBox); ?></th>
                                            <th><?= number_format($totalBalance, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
    document.title = "<?= 'Taly' . ' ' . $rowtally['nama_customer']; ?> ";
</script>
<?php
include "../footer.php" ?>