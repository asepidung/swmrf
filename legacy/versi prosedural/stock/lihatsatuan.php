<?php
require "../verifications/auth.php";
require "../konak/conn.php";
$pod = $_GET['pod'];
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
      <section class="content">
         <div class="container-fluid">
            <div class="row">
               <div class="col-8 mt-3">
                  <a href="aging.php" class="btn btn-warning mb-2"><i class="fas fa-undo-alt"></i></a>
                  <a href="index.php" class="btn btn-primary mb-2">Summary</a>
                  <div class="card">
                     <div class="card-body">
                        <div class="col">
                           <table id="example1" class="table table-bordered table-striped table-sm">
                              <thead class="text-center">
                                 <tr>
                                    <th>#</th>
                                    <th>Barcode</th>
                                    <th>Grade</th>
                                    <th>Item Description</th>
                                    <th>Qty</th>
                                    <th>Pack On Date</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <?php
                                 // Query untuk mengambil data dari tabel stock, melakukan JOIN, dan mengelompokkannya
                                 $query = "SELECT a.kdbarcode, a.qty, a.pod, b.nmgrade, c.nmbarang
                                 FROM stock a
                                 JOIN grade b ON a.idgrade = b.idgrade
                                 JOIN barang c ON a.idbarang = c.idbarang
                                 WHERE a.pod = ?
                                 ORDER BY c.nmbarang";  // Menggunakan alias tabel untuk menjaga keterbacaan

                                 $stmt = $conn->prepare($query);
                                 $stmt->bind_param("s", $pod);
                                 $stmt->execute();
                                 $result = $stmt->get_result();

                                 if ($result->num_rows > 0) {
                                    $count = 1;
                                    while ($row = $result->fetch_assoc()) {
                                       echo "<tr class='text-center'>";
                                       echo "<td>$count</td>";
                                       echo "<td>" . $row['kdbarcode'] . "</td>";
                                       echo "<td>" . $row['nmgrade'] . "</td>";
                                       echo "<td class='text-left'>" . $row['nmbarang'] . "</td>";
                                       echo "<td class='text-right'>" . number_format($row['qty'], 2) . "</td>";
                                       echo "<td>" . date('d-M-Y', strtotime($row['pod'], 2)) . "</td>";
                                       echo "</tr>";
                                       $count++;
                                    }
                                 } else {
                                    echo "<tr><td colspan='5'>No data available</td></tr>";
                                 }

                                 $stmt->close();
                                 ?>

                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </section>
      <script>
         document.title = "Print By POD";

         // window.onload = function() {
         //    window.print();
         // };

         // window.onafterprint = function() {
         //    window.location.href = 'index.php';
         // };
      </script>


      <?php
      include "../footer.php" ?>