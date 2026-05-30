<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Cek apakah idcustomer tersedia dalam parameter URL
if (!isset($_GET['idcustomer']) || empty($_GET['idcustomer'])) {
    echo "<script>alert('ID Customer tidak ditemukan!'); window.location='invoice.php';</script>";
    exit;
}

$idcustomer = mysqli_real_escape_string($conn, $_GET['idcustomer']);

// Ambil data customer
$queryCustomer = "SELECT nama_customer FROM customers WHERE idcustomer = '$idcustomer'";
$resultCustomer = mysqli_query($conn, $queryCustomer);
$dataCustomer = mysqli_fetch_assoc($resultCustomer);

if (!$dataCustomer) {
    echo "<script>alert('Customer tidak ditemukan!'); window.location='invoice.php';</script>";
    exit;
}

$customerName = htmlspecialchars($dataCustomer['nama_customer']);
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <h3 class="mb-3">Detail Faktur: <?= $customerName; ?></h3>
                    <a href="tf.php" class="btn btn-sm btn-success"><i class="fas fa-undo-alt"></i> Kembali</a>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <!-- /.card-header -->
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>No Invoice</th>
                                        <th>No DO</th>
                                        <th>Tgl Invoice</th>
                                        <th>PO</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $query = "SELECT i.*, c.nama_customer, do.iddo
                                              FROM invoice i
                                              INNER JOIN customers c ON i.idcustomer = c.idcustomer
                                              LEFT JOIN do ON i.donumber = do.donumber
                                              WHERE i.status = 'Belum TF' AND i.is_deleted = 0 AND i.idcustomer = '$idcustomer'
                                              ORDER BY i.invoice_date ASC";
                                    $result = mysqli_query($conn, $query);

                                    while ($row = mysqli_fetch_array($result)) {
                                        $iddo = $row['iddo'];
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no; ?></td>
                                            <td class="text-center"><?= htmlspecialchars($row['noinvoice']); ?></td>
                                            <td class="text-center">
                                                <a href="../do/lihatdo.php?iddo=<?= $iddo ?>">
                                                    <?= substr($row['donumber'], -4); ?>
                                                </a>
                                            </td>
                                            <td class="text-center"><?= date("d-M-y", strtotime($row['invoice_date'])); ?></td>
                                            <td><?= htmlspecialchars($row['pocustomer']); ?></td>
                                            <td class="text-right"><?= number_format($row['balance'], 2); ?></td>
                                            <td class="text-center">
                                                <?php if ($row['status'] == '-') {
                                                    echo "-";
                                                } else if ($row['status'] == 'Belum TF') { ?>
                                                    <a href="tukarfaktur.php?idinvoice=<?= $row['idinvoice'] ?>">
                                                        <span class="text-success" data-toggle="tooltip" data-placement="bottom" title="Klik Untuk Tukar Faktur">Belum TF</span>
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="text-primary" data-toggle="tooltip" data-placement="left" title="<?= date("d-M-y", strtotime($row['tgltf'])) . " " . htmlspecialchars($row['note']); ?>"><?= $row['status']; ?></span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="lihatinvoice.php?idinvoice=<?= $row['idinvoice']; ?>">
                                                    <button type="button" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                                </a>
                                                <a href="pib.php?idinvoice=<?= $row['idinvoice']; ?>">
                                                    <button type="button" class="btn btn-sm btn-success"><i class="fas fa-print"></i></button>
                                                </a>
                                                <a href="editinvoice.php?idinvoice=<?= $row['idinvoice']; ?>">
                                                    <button type="button" class="btn btn-sm btn-warning"><i class="fas fa-pencil-alt"></i></button>
                                                </a>
                                                <a href="deleteinvoice.php?idinvoice=<?= $row['idinvoice']; ?>&iddo=<?= $row['iddo']; ?>" onclick="return confirm('Anda yakin ingin Membatalkan invoice ini?');">
                                                    <button type="button" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></button>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php $no++;
                                    } ?>
                                </tbody>
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
    document.title = "Detail Faktur: <?= $customerName; ?>";
</script>
<?php
include "../footer.php";
?>