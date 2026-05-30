<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idcustomer   = $_GET['idcustomer'] ?? '';
$deliverydate = $_GET['deliverydate'] ?? '';

$query = "
   SELECT 
      so.idso,
      so.sonumber,
      SUM(sod.weight) AS total_qty,
      c.nama_customer
   FROM salesorder so
   JOIN salesorderdetail sod ON so.idso = sod.idso
   JOIN customers c ON so.idcustomer = c.idcustomer
   WHERE 
      so.idcustomer = '$idcustomer'
      AND so.deliverydate = '$deliverydate'
      AND so.progress != 'Delivered'
      AND so.is_deleted = 0
   GROUP BY so.idso
   ORDER BY so.sonumber ASC
";

$result = mysqli_query($conn, $query);
$header = mysqli_fetch_assoc($result);
mysqli_data_seek($result, 0);
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-header">
                    <strong>Tanggal Kirim:</strong> <?= date("d-m-Y", strtotime($deliverydate)); ?><br>
                    <strong>Customer:</strong> <?= $header['nama_customer']; ?>
                </div>

                <div class="card-body">
                    <form method="POST" action="save_delivery_detail.php">

                        <!-- INPUT SEKALI -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label>Driver</label>
                                <input type="text" name="driver" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label>Armada</label>
                                <input type="text" name="armada" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label>Jam Loading</label>
                                <input type="time" name="loadtime" class="form-control form-control-sm" required>
                            </div>
                        </div>

                        <!-- DETAIL SO -->
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
                                            <input type="text" name="note[]" class="form-control form-control-sm">
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>

                        <button type="submit" class="btn btn-primary btn-sm">
                            Simpan
                        </button>

                    </form>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include "../footer.php"; ?>