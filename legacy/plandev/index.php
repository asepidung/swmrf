<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0">Plan Delivery</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="plan_delivery_history.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-history"></i> History
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
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
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;

                            $query = "
                            SELECT
                                so.idcustomer,
                                c.nama_customer,
                                so.deliverydate,
                                COUNT(DISTINCT so.idso) AS total_po,
                                SUM(sod.weight) AS total_qty,
                                MAX(dpd.driver) AS driver,
                                MAX(dpd.armada) AS armada,
                                MAX(dpd.loadtime) AS loadtime,
                                GROUP_CONCAT(DISTINCT dpd.note SEPARATOR ' | ') AS notes
                            FROM salesorder so
                            JOIN salesorderdetail sod ON so.idso = sod.idso
                            JOIN customers c ON so.idcustomer = c.idcustomer
                            LEFT JOIN delivery_plan_detail dpd ON dpd.idso = so.idso
                            WHERE
                                so.deliverydate >= CURDATE()
                                AND so.progress IN ('Waiting', 'DRAFT')
                                AND so.is_deleted = 0
                                AND (c.idgroup IS NULL OR c.idgroup != 21)
                            GROUP BY
                                so.idcustomer,
                                so.deliverydate
                            ORDER BY
                                so.deliverydate ASC,
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

                                    <td><?= $row['driver'] ?? ''; ?></td>

                                    <td><?= $row['armada'] ?? ''; ?></td>

                                    <td class="text-center">
                                        <?= $row['loadtime'] ? date("H:i", strtotime($row['loadtime'])) : ''; ?>
                                    </td>

                                    <td><?= $row['notes'] ?? ''; ?></td>

                                    <td class="text-center">
                                        <?php if (empty($row['driver'])) { ?>
                                            <!-- BELUM ADA → CREATE -->
                                            <a
                                                href="delivery_detail.php?idcustomer=<?= $row['idcustomer']; ?>&deliverydate=<?= $row['deliverydate']; ?>"
                                                class="btn btn-xs btn-primary"
                                                title="Isi Pengiriman">
                                                <i class="fas fa-truck"></i>
                                            </a>
                                        <?php } else { ?>
                                            <!-- SUDAH ADA → EDIT -->
                                            <a
                                                href="edit_delivery_detail.php?idcustomer=<?= $row['idcustomer']; ?>&deliverydate=<?= $row['deliverydate']; ?>"
                                                class="btn btn-xs btn-warning"
                                                title="Edit Pengiriman">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php } ?>
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
    document.title = "Plan Delivery";
</script>

<?php include "../footer.php"; ?>