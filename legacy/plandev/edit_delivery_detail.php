<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idcustomer   = $_GET['idcustomer'] ?? '';
$deliverydate = $_GET['deliverydate'] ?? '';

if (empty($idcustomer) || empty($deliverydate)) {
    header("Location: index.php");
    exit;
}

/*
   QUERY EDIT
   - WAJIB pakai aggregate
   - TIDAK pakai filter progress
*/
$query = "
    SELECT
        dpd.idso,
        so.sonumber,
        MAX(dpd.driver)   AS driver,
        MAX(dpd.armada)  AS armada,
        MAX(dpd.loadtime) AS loadtime,
        dpd.note,
        SUM(sod.weight) AS total_qty,
        c.nama_customer
    FROM delivery_plan_detail dpd
    JOIN salesorder so ON dpd.idso = so.idso
    JOIN salesorderdetail sod ON so.idso = sod.idso
    JOIN customers c ON dpd.idcustomer = c.idcustomer
    WHERE
        dpd.idcustomer = '$idcustomer'
        AND dpd.deliverydate = '$deliverydate'
    GROUP BY
        dpd.idso,
        dpd.note,
        so.sonumber,
        c.nama_customer
    ORDER BY so.sonumber ASC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("SQL ERROR: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) == 0) {
?>
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="alert alert-warning">
                    Data pengiriman tidak ditemukan.
                </div>
                <a href="index.php" class="btn btn-secondary btn-sm">Kembali</a>
            </div>
        </section>
    </div>
<?php
    include "../footer.php";
    exit;
}

$data = mysqli_fetch_assoc($result);

/* Header value (sama untuk semua SO) */
$driver   = $data['driver'];
$armada  = $data['armada'];
$loadtime = $data['loadtime'];

mysqli_data_seek($result, 0);
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header">
                    <strong>Tanggal Kirim:</strong> <?= date("d-m-Y", strtotime($deliverydate)); ?><br>
                    <strong>Customer:</strong> <?= $data['nama_customer']; ?>
                </div>

                <div class="card-body">
                    <form method="POST" action="update_delivery_detail.php">

                        <input type="hidden" name="idcustomer" value="<?= $idcustomer; ?>">
                        <input type="hidden" name="deliverydate" value="<?= $deliverydate; ?>">

                        <!-- HEADER -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label>Driver</label>
                                <input type="text" name="driver" class="form-control form-control-sm" value="<?= $driver; ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label>Armada</label>
                                <input type="text" name="armada" class="form-control form-control-sm" value="<?= $armada; ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label>Jam Loading</label>
                                <input type="time" name="loadtime" class="form-control form-control-sm" value="<?= $loadtime; ?>" required>
                            </div>
                        </div>

                        <!-- DETAIL -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>SO Number</th>
                                        <th>Qty (Kg)</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($row = mysqli_fetch_assoc($result)) { ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td>
                                                <?= $row['sonumber']; ?>
                                                <input type="hidden" name="idso[]" value="<?= $row['idso']; ?>">
                                            </td>
                                            <td class="text-right"><?= number_format($row['total_qty']); ?></td>
                                            <td>
                                                <input type="text" name="note[]" class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($row['note']); ?>">
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-warning btn-sm">
                            Update Pengiriman
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-sm">Kembali</a>

                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include "../footer.php"; ?>