<?php
require "../verifications/auth.php";
require "../konak/conn.php";
// include "../header.php";

$idso = $_GET['idso'];

$query = "SELECT salesorder.*, customers.nama_customer, customers.alamat1, customers.alamat2, customers.alamat3
FROM salesorder 
INNER JOIN customers ON salesorder.idcustomer = customers.idcustomer
WHERE idso = $idso";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
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
         <div class="col text-center">
            <h4 class="mb-n1">SALES ORDER</h4>
            <span><strong><?= $row['sonumber']; ?></strong></span>
         </div>
         <hr>
         <div class="row mt-2">
            <div class="col-md">
               <table class="table table-responsive table-borderless table-sm">
                  <tr>
                     <td>Customer</td>
                     <td>:</td>
                     <th><?= $row['nama_customer']; ?></th>
                  </tr>
                  <tr>
                     <td>PO Numb</td>
                     <td>:</td>
                     <th><?= $row['po']; ?></th>
                  </tr>
               </table>
            </div>
            <div class="col-xs">
               <table class="table table-responsive table-borderless table-sm">
                  <tr>
                     <td>Delivery Date</td>
                     <td>:</td>
                     <th><?= date('d-M-Y', strtotime($row['deliverydate'])); ?></th>
                  </tr>
                  <tr>
                     <td>Ship To</td>
                     <td>:</td>
                     <th><?= $row['alamat1']; ?></th>
                  </tr>
               </table>
            </div>
         </div>
         <table class="table table-sm table-striped table-bordered">
            <thead class="thead-dark">
               <tr class="text-center">
                  <th>#</th>
                  <th>Product Desc</th>
                  <th>PO Qty</th>
                  <th>Notes</th>
               </tr>
            </thead>
            <tbody>
               <?php
               $no = 1;
               $query_salesorderdetail = "SELECT salesorderdetail.*, barang.nmbarang
             FROM salesorderdetail
             INNER JOIN barang ON salesorderdetail.idbarang = barang.idbarang
             WHERE idso = '$idso'";
               $result_salesorderdetail = mysqli_query($conn, $query_salesorderdetail);
               while ($row_salesorderdetail = mysqli_fetch_assoc($result_salesorderdetail)) { ?>
                  <tr>
                     <td class="text-center"><?= $no; ?></td>
                     <td><?= $row_salesorderdetail['nmbarang']; ?></td>
                     <td class="text-right"><?= number_format($row_salesorderdetail['weight'], 2); ?></td>
                     <td><?= $row_salesorderdetail['notes']; ?></td>
                  </tr>
               <?php $no++;
               } ?>
            </tbody>
         </table>
         <p class="mb-n1">
            <?php
            if ($row['note'] !== "") { ?>
               <strong>Catatan :</strong>
            <?php } else {
            } ?>
         </p>
         <p>
            <i><?= $row['note']; ?></i>
         </p>
      </div>

   </body>
   <script>
      document.title = "<?= $row['nama_customer'] . " " . "Price List" ?>"
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

   include "../footer.php"
   ?>