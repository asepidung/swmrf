<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$idpricelist = $_GET['idpricelist'];

$query = "SELECT p.*, c.nmgroup
FROM pricelist p
INNER JOIN groupcs c ON p.idgroup = c.idgroup
WHERE p.idpricelist = ?"; // Gunakan placeholder (?)

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
  mysqli_stmt_bind_param($stmt, "i", $idpricelist);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
} else {
  die("Error in preparing the statement: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $idpricelist);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
  $row = mysqli_fetch_assoc($result);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SWM | Apps</title>
  <link rel="icon" href="../dist/img/favicon.png" type="image/x-icon">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="../plugins/daterangepicker/daterangepicker.css">
  <link rel="stylesheet" href="../plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="../plugins/bootstrap-colorpicker/css/bootstrap-colorpicker.min.css">
  <link rel="stylesheet" href="../plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <link rel="stylesheet" href="../plugins/bootstrap4-duallistbox/bootstrap-duallistbox.min.css">
  <link rel="stylesheet" href="../plugins/bs-stepper/css/bs-stepper.min.css">
  <link rel="stylesheet" href="../plugins/dropzone/min/dropzone.min.css">
  <link rel="stylesheet" href="../dist/css/adminlte.min.css">
  <link rel="stylesheet" href="../plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="../plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <link rel="stylesheet" href="../plugins/bootstrap4-duallistbox/bootstrap-duallistbox.min.css">
</head>
<div class="wrapper">

  <body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
    <div class="container">
      <div class="row mb-2">
        <img src="../dist/img/headerquo.png" alt="quotations" class="img-fluid">
      </div>
      <h3 class="text-center">PRICE LIST</h3>
      <table class="table table-responsive table-borderless table-sm">
        <tr>
          <td>Customer</td>
          <td>:</td>
          <th><?= $row['nmgroup']; ?></th>
        </tr>
        <tr>
          <td>CP</td>
          <td>:</td>
          <th><?= $row['up']; ?></th>
        </tr>
        <tr>
          <td>Price Update</td>
          <td>:</td>
          <th><?= date("d-M-y", strtotime($row['latestupdate'])); ?></th>
        </tr>
      </table>
      <table class="table table-sm table-striped table-bordered">
        <thead class="thead-dark">
          <tr class="text-center">
            <th>#</th>
            <th>Prod Desc</th>
            <th>Prod Kategory</th>
            <th>Brand</th>
            <th>Price</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $no = 1;
          $query_pricelistdetail = "SELECT pricelistdetail.*, barang.nmbarang, cuts.nmcut
           FROM pricelistdetail
           INNER JOIN barang ON pricelistdetail.idbarang = barang.idbarang
           LEFT JOIN cuts ON barang.idcut = cuts.idcut
           WHERE idpricelist = '$idpricelist'";
          $result_pricelistdetail = mysqli_query($conn, $query_pricelistdetail);
          while ($row_pricelistdetail = mysqli_fetch_assoc($result_pricelistdetail)) { ?>
            <tr>
              <td class="text-center"><?= $no; ?></td>
              <td><?= $row_pricelistdetail['nmbarang']; ?></td>
              <td><?= $row_pricelistdetail['nmcut']; ?></td>
              <td class="text-center">Wijaya Meat</td>
              <td class="text-right"><?= number_format($row_pricelistdetail['price']); ?></td>
              <td><?= $row_pricelistdetail['notes']; ?></td>
            </tr>
          <?php $no++;
          } ?>
        </tbody>
      </table>
      <div class="row">
        <div class="col-6">
          Informasi Lebih Lanjut Silahkan Hubungi <br> Muryani 0818 0898 5323<br> yani@wijayameat.co.id
        </div>
      </div>
    </div>
    <script>
      document.title = "<?= $row['nmgroup'] . " " . "Price List" ?>";

      // Trigger the print dialog when the page loads
      window.onload = function() {
        window.print();
      };

      // Close the window after printing (optional)
      window.onafterprint = function() {
        window.location.href = 'index.php';
      };
    </script>


    <?php
    include "../footer.php" ?>