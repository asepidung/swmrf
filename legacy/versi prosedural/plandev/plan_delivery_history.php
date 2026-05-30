<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

/* Default filter tanggal */
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate   = $_GET['end']   ?? date('Y-m-d');
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0">Plan Delivery History</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- FILTER TANGGAL -->
            <div class="card mb-2">
                <div class="card-body p-2">
                    <form method="GET" class="form-inline">
                        <label class="mr-2">Periode</label>
                        <input type="date" name="start" class="form-control form-control-sm mr-2"
                            value="<?= $startDate; ?>">
                        <span class="mr-2">s/d</span>
                        <input type="date" name="end" class="form-control form-control-sm mr-2"
                            value="<?= $endDate; ?>">
                        <button type="submit" class="btn btn-sm btn-primary">
                            Tampilkan
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body table-responsive">

                    <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                            <tr>
                                <th>#</th>
                                <th>Tgl Kirim</th>
                                <th>Customer</th>
                                <th>PO</th>
                                <th>Qty (Kg)</th>
                                <th>Driver</th>
                                <th>Armada</th>
                                <th>Jam</th>
                                <th>Note</th>
                                <!-- <th>Status</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;

                            $query = "
                            SELECT
                                dpd.idcustomer,
                                c.nama_customer,
                                dpd.deliverydate,
                                COUNT(DISTINCT dpd.idso) AS total_po,
                                SUM(sod.weight) AS total_qty,
                                MAX(dpd.driver) AS driver,
                                MAX(dpd.armada) AS armada,
                                MAX(dpd.loadtime) AS loadtime,
                                GROUP_CONCAT(DISTINCT dpd.note SEPARATOR ' | ') AS notes,
                                MAX(so.progress) AS progress
                            FROM delivery_plan_detail dpd
                            JOIN salesorder so 
                                ON dpd.idso = so.idso
                            JOIN salesorderdetail sod 
                                ON so.idso = sod.idso
                            JOIN customers c 
                                ON dpd.idcustomer = c.idcustomer
                            WHERE
                                dpd.deliverydate BETWEEN '$startDate' AND '$endDate'
                                AND so.is_deleted = 0
                                AND (c.idgroup IS NULL OR c.idgroup != 21)
                            GROUP BY
                                dpd.idcustomer,
                                dpd.deliverydate
                            ORDER BY
                                dpd.deliverydate DESC,
                                c.nama_customer ASC
                        ";

                            $result = mysqli_query($conn, $query);

                            while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>

                                    <td class="text-center">
                                        <?= date("d-m-Y", strtotime($row['deliverydate'])); ?>
                                    </td>

                                    <td><?= $row['nama_customer']; ?></td>

                                    <td class="text-center"><?= $row['total_po']; ?></td>

                                    <td class="text-right"><?= number_format($row['total_qty']); ?></td>

                                    <td><?= $row['driver']; ?></td>

                                    <td><?= $row['armada']; ?></td>

                                    <td class="text-center">
                                        <?= $row['loadtime'] ? date("H:i", strtotime($row['loadtime'])) : ''; ?>
                                    </td>

                                    <td><?= $row['notes']; ?></td>
                                    <!-- 
                                    <td class="text-center">
                                        <?= $row['progress']; ?>
                                    </td> -->
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
    document.title = "Plan Delivery History";
</script>

<?php include "../footer.php"; ?>