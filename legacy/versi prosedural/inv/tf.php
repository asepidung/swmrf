<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$queryApprovedCount = "SELECT COUNT(*) AS approved_count FROM do WHERE status = 'Approved'";
$resultApprovedCount = mysqli_query($conn, $queryApprovedCount);
$rowApprovedCount = mysqli_fetch_assoc($resultApprovedCount);
$approvedCount = $rowApprovedCount['approved_count'];
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <a href="invoice.php" class="btn btn-sm btn-success"><i class="fas fa-undo-alt"></i> Kembali</a>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-6 col-12"> <!-- Responsive Layout -->
                    <div class="card">
                        <!-- /.card-header -->
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Customer</th>
                                        <th>Count Inv</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $totalAmount = 0; // Variabel untuk menyimpan total keseluruhan
                                    $query = "SELECT c.idcustomer, c.nama_customer, 
                                                    COUNT(i.idinvoice) AS count_inv, 
                                                    SUM(i.balance) AS total_amount
                                              FROM invoice i
                                              INNER JOIN customers c ON i.idcustomer = c.idcustomer
                                              WHERE i.status = 'Belum TF' AND i.is_deleted = 0
                                              GROUP BY c.idcustomer, c.nama_customer
                                              ORDER BY total_amount DESC";
                                    $result = mysqli_query($conn, $query);

                                    while ($row = mysqli_fetch_array($result)) {
                                        $totalAmount += $row['total_amount']; // Menambahkan amount ke total keseluruhan
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no; ?></td>
                                            <td>
                                                <a href="detailtf.php?idcustomer=<?= $row['idcustomer']; ?>">
                                                    <?= htmlspecialchars($row['nama_customer']); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?= $row['count_inv']; ?></td>
                                            <td class="text-right"><?= number_format($row['total_amount'], 2); ?></td>
                                        </tr>
                                    <?php $no++;
                                    } ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th class="text-right" colspan="3">TOTAL</th>
                                        <th class="text-right"><?= number_format($totalAmount, 2); ?></th> <!-- Total semua amount -->
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<script>
    document.title = "Faktur List";
</script>
<?php
include "../footer.php";
?>