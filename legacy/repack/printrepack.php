<?php
require "../verifications/auth.php";
require "../konak/conn.php";
// include "../header.php";

$idrepack = $_GET['id'];

$query = "SELECT * FROM repack
WHERE idrepack = $idrepack";

$result = mysqli_query($conn, $query);
$tampil = mysqli_fetch_assoc($result);
include "hitungtotal.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>Print Repack</title>
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
            <h4 class="mb-n1">Laporan Hasil Repack</h4>
            <span><strong>No : <?= $tampil['norepack']; ?></strong></span>
         </div>
         <hr>
         <div class="row mt-2">
            <div class="col-md">
               <table class="table table-responsive table-borderless table-sm">
                  <tr>
                     <td>Tanggal Repack</td>
                     <td>:</td>
                     <th><?= $tampil['tglrepack']; ?></th>
                  </tr>
                  <tr>
                     <td>Keterangan</td>
                     <td>:</td>
                     <th><?= $tampil['note']; ?></th>
                  </tr>
               </table>
            </div>
         </div>
         <div class="row">
            <div class="col-4">
               <table class="table table-sm table-striped table-bordered">
                  <thead class="text-center">
                     <tr>
                        <th colspan="2">BAHAN</th>
                     </tr>
                     <tr>
                        <td>Item</td>
                        <td>Berat</td>
                     </tr>
                  </thead>
                  <tbody>
                     <?php
                     $query = "SELECT detailbahan.idbarang, barang.nmbarang, SUM(detailbahan.qty) AS total_qty
                              FROM detailbahan
                              INNER JOIN barang ON detailbahan.idbarang = barang.idbarang
                              WHERE detailbahan.idrepack = $idrepack
                              GROUP BY detailbahan.idbarang, barang.nmbarang";
                     $result = mysqli_query($conn, $query);
                     while ($tampil = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                           <td><?= $tampil['nmbarang'] ?></td>
                           <td class="text-right"><?= number_format($tampil['total_qty'], 2) ?></td>
                        </tr>
                     <?php } ?>
                  </tbody>
               </table>
            </div>
            <div class="col-4">
               <table class="table table-sm table-striped table-bordered">
                  <thead class="text-center">
                     <tr>
                        <th colspan="2">HASIL</th>
                     </tr>
                     <tr>
                        <td>Item</td>
                        <td>Berat</td>
                     </tr>
                  </thead>
                  <tbody>
                     <?php
                     $query = "SELECT detailhasil.idbarang, barang.nmbarang, SUM(detailhasil.qty) AS total_hasil
                     FROM detailhasil
                     INNER JOIN barang ON detailhasil.idbarang = barang.idbarang
                     WHERE detailhasil.idrepack = $idrepack
                     GROUP BY detailhasil.idbarang, barang.nmbarang";
                     $result = mysqli_query($conn, $query);
                     while ($tampil = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                           <td><?= $tampil['nmbarang'] ?></td>
                           <td class="text-right"><?= number_format($tampil['total_hasil'], 2) ?></td>
                        </tr>
                     <?php } ?>
                  </tbody>
               </table>
            </div>
            <div class="col-4">
               <table class="table table-sm table-striped table-bordered">
                  <thead class="text-center">
                     <tr>
                        <th colspan="2">SUSUT</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr>
                        <td class="text-right"><?= $lost; ?></td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <div class="row">
            </div>
         </div>

   </body>
   <script>
      document.title = "LAPORAN REPACK"
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