<?php
session_start();
if (!isset($_SESSION['login'])) {
   header("location: verifications/login.php");
}
require "konak/conn.php";

$fullname = $_SESSION['fullname'];
$idusers = $_SESSION['idusers'];
$query = "SELECT * FROM role WHERE idusers = $idusers";
$result = mysqli_query($conn, $query);
$role = mysqli_fetch_assoc($result);
include "kebutuhanindex.php";
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>SWM Welcome</title>
   <link rel="icon" href="dist/img/favicon.png" type="image/x-icon">
   <!-- Google Font: Source Sans Pro -->
   <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
   <!-- Font Awesome -->
   <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
   <!-- Ionicons -->
   <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
   <!-- Tempusdominus Bootstrap 4 -->
   <link rel="stylesheet" href="plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
   <!-- iCheck -->
   <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
   <!-- JQVMap -->
   <link rel="stylesheet" href="plugins/jqvmap/jqvmap.min.css">
   <!-- Theme style -->
   <link rel="stylesheet" href="dist/css/adminlte.min.css">
   <!-- overlayScrollbars -->
   <link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
   <!-- Daterange picker -->
   <link rel="stylesheet" href="plugins/daterangepicker/daterangepicker.css">
   <!-- summernote -->
   <link rel="stylesheet" href="plugins/summernote/summernote-bs4.min.css">
   <style>
      body {
         font-size: 13px;
         /* Sesuaikan dengan ukuran font yang diinginkan */
      }

      .fas {
         font-size: 0.85em;
      }
   </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
   <div class="wrapper">

      <!-- Preloader -->
      <div class="preloader flex-column justify-content-center align-items-center">
         <img class="animation__shake" src="dist/img/logoSWM.png" alt="AdminLTELogo" height="150" width="200">
      </div>

      <!-- Navbar -->
      <nav class="main-header navbar navbar-expand navbar-dark">
         <!-- Left navbar links -->
         <ul class="navbar-nav">
            <li class="nav-item">
               <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
         </ul>
         <!-- Right navbar links -->
         <ul class="navbar-nav ml-auto">
            <!-- Navbar Search -->
            <li class="nav-item">
               <!-- <a class="nav-link" data-widget="navbar-search" href="#" role="button">
                  <i class="fas fa-search"></i>
               </a> -->
               <div class="navbar-search-block">
                  <form class="form-inline">
                     <div class="input-group input-group-sm">
                        <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
                        <div class="input-group-append">
                           <button class="btn btn-navbar" type="submit">
                              <i class="fas fa-search"></i>
                           </button>
                           <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                              <i class="fas fa-times"></i>
                           </button>
                        </div>
                     </div>
                  </form>
               </div>
            </li>
            <!-- Messages Dropdown Menu -->
            <li class="nav-item dropdown">
               <!-- <a class="nav-link" data-toggle="dropdown" href="#">
                  <i class="far fa-comments"></i>
                  <span class="badge badge-danger navbar-badge">2</span>
               </a> -->
               <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                  <a href="#" class="dropdown-item">
                     <!-- Message Start -->
                     <div class="media">
                        <img src="dist/img/ipin.jpg" alt="User Avatar" class="img-size-50 mr-3 img-circle">
                        <div class="media-body">
                           <h3 class="dropdown-item-title">
                              Ipin Suripin
                              <span class="float-right text-sm text-danger"><i class="fas fa-star"></i></span>
                           </h3>
                           <p class="text-sm">Keur Naon mang...?</p>
                           <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                        </div>
                     </div>
                     <!-- Message End -->
                  </a>
                  <div class="dropdown-divider"></div>
                  <a href="#" class="dropdown-item">
                     <!-- Message Start -->
                     <div class="media">
                        <img src="dist/img/ble.jpg" alt="User Avatar" class="img-size-50 img-circle mr-3">
                        <div class="media-body">
                           <h3 class="dropdown-item-title">
                              Bapak Ble
                              <span class="float-right text-sm text-muted"><i class="fas fa-star"></i></span>
                           </h3>
                           <p class="text-sm">menta link na atuh...!</p>
                           <p class="text-sm text-muted"><i class="far fa-clock mr-1"></i> 4 Hours Ago</p>
                        </div>
                     </div>
                     <!-- Message End -->
                  </a>
                  <div class="dropdown-divider"></div>
                  <a href="#" class="dropdown-item dropdown-footer">See All Messages</a>
               </div>
            </li>
            <!-- Notifications Dropdown Menu -->
            <li class="nav-item dropdown">
               <!-- <a class="nav-link" data-toggle="dropdown" href="#">
                  <i class="far fa-bell"></i>
                  <span class="badge badge-warning navbar-badge">15</span>
               </a> -->
               <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                  <span class="dropdown-item dropdown-header">15 Notifications</span>
                  <div class="dropdown-divider"></div>
                  <a href="#" class="dropdown-item">
                     <i class="fas fa-envelope mr-2"></i> 4 New Sales Order
                     <span class="float-right text-muted text-sm">3 mins</span>
                  </a>
                  <div class="dropdown-divider"></div>
                  <a href="#" class="dropdown-item">
                     <i class="fas fa-truck-moving mr-2"></i> 8 New Delivery Order
                     <span class="float-right text-muted text-sm">12 hours</span>
                  </a>
                  <div class="dropdown-divider"></div>
                  <a href="#" class="dropdown-item">
                     <i class="fas fa-file-invoice mr-2"></i> 3 new Invoice
                     <span class="float-right text-muted text-sm">2 days</span>
                  </a>
                  <div class="dropdown-divider"></div>
                  <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
               </div>
            </li>
            <li class="nav-item">
               <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                  <i class="fas fa-expand-arrows-alt"></i>
               </a>
            </li>
            <li class="nav-item">
               <a class="nav-link" href="verifications/logout.php" role="button" data-toggle="tooltip" data-placement="bottom" title="LOGOUT">
                  <i class="fas fa-power-off text-danger"></i>
               </a>
            </li>
         </ul>
      </nav>
      <!-- /.navbar -->
      <!-- Main Sidebar Container -->
      <aside class="main-sidebar sidebar-dark-primary elevation-4">
         <!-- Brand Logo -->
         <a href="index.php" class="brand-link">
            <img src="dist/img/logoSWM.png" alt="SWM Logo" class="brand-image">
            <span class="brand-text font-weight-light">WIJAYA MEAT</span>
         </a>
         <!-- Sidebar -->
         <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
               <div class="image">
                  <img src="dist/img/avatar5.png" class="img-circle elevation-2" alt="User Image">
               </div>
               <div class="info">
                  <a href="#" class="d-block"><?= $fullname; ?></a>
               </div>
            </div>
            <!-- Sidebar Menu -->
            <nav class="mt-2">
               <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                  <?php if ($role['produksi'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-boxes"></i>
                           <p>
                              PRODUKSI
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="boning/databoning.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Boning</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="repack" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Repack</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="relabel/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Relabel</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['warehouse'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-warehouse"></i>
                           <p>
                              WAREHOUSE
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="tally/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Taly Sheet</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="gr/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Goods Receipt</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="returjual/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Sales Return</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="mutasi/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Mutasi</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="stock/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Stock</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="stock/tofroz.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>> 60 Days</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="stocktake" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Stock Take</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['distributions'] == 1) : ?>
                     <li class="nav-item">
                        <a href="404.php" class="nav-link">
                           <i class="nav-icon fas fa-truck"></i>
                           <p>
                              DISTRIBUTIONS
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="do/do.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Delivery Order</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="do/dodetail.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Do Detail</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="plandev/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Schedule</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['purchase_module'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-shopping-cart"></i>
                           <p>
                              PURCHASE MODULE
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="poproduct/" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>PO Product</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="pomaterial/" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>PO Material Support</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['sales'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-shopping-bag"></i>
                           <p>
                              SALES
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="pricelist/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Price List</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="salesorder" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Sales Order</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="404.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Approve Invoice</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['finance'] == 1) : ?>
                     <li class="nav-item">
                        <a href="404.php" class="nav-link">
                           <i class="nav-icon fas fa-cart-plus"></i>
                           <p>
                              FINANCE
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="inv/invoice.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Invoice</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="inv/invoicedetail.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Invoice Detail</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['data_report'] == 1) : ?>
                     <li class="nav-item">
                        <a href="404.php" class="nav-link">
                           <i class="nav-icon fas fa-table"></i>
                           <p>
                              DATA REPORT
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="sales/order" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Order</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="404.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Balance Sheet</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="404.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Profit / Loss</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="404.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Detail</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="log.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Log Activity</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['master_data'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-copy"></i>
                           <p>
                              MASTER DATA
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="#" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Barang</p>
                                 <i class="right fas fa-angle-left"></i>
                              </a>
                              <ul class="nav nav-treeview">
                                 <li class="nav-item">
                                    <a href="barang/barang.php" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>Product</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="rawmate/" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>NoN Product</p>
                                    </a>
                                 </li>
                              </ul>
                           </li>
                           <li class="nav-item">
                              <a href="supplier/supplier.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Supplier</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="customer/customer.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Customer</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="group/" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Group Customer</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="segment/segment.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Segment</p>
                              </a>
                           </li>
                           <?php if ($idusers == 1) { ?>
                              <li class="nav-item">
                                 <a href="user/user.php" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Users</p>
                                 </a>
                              </li>
                           <?php } ?>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <li class="nav-item">
                     <a href="verifications/logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>
                           LOGOUT
                        </p>
                     </a>
                  </li>
               </ul>
            </nav>
            <!-- /.sidebar-menu -->
         </div>
         <!-- /.sidebar -->
      </aside>
      <div class="content-wrapper">
         <div class="content-header">
            <div class="container-fluid">
               <div class="row g-2 align-items-center">
                  <div class="col-lg-2 col-md-3 col-6">
                     <form method="GET" action="">
                        <input type="date" class="form-control form-control-sm" name="awal" value="<?= $awal; ?>">
                  </div>
                  <div class="col-lg-2 col-md-3 col-6">
                     <input type="date" class="form-control form-control-sm" name="akhir" value="<?= $akhir; ?>">
                  </div>
                  <div class="col-lg-1 col-md-2 col-12 d-grid">
                     <button type="submit" class="btn btn-sm btn-primary" name="search">
                        <i class="fas fa-search"></i> Cari
                     </button>
                     </form>
                  </div>
               </div>
            </div>
         </div>
         <section class="content">
            <div class="container-fluid">
               <div class="row">
                  <div class="col-12">
                     <div class="card mt-3">
                        <div class="card-body">
                           <table id="example1" class="table table-bordered table-striped table-sm">
                              <thead>
                                 <tr class="text-center">
                                    <th>#</th>
                                    <th>Users</th>
                                    <th>Event</th>
                                    <th>Document Number</th>
                                    <th>Timestamp</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 <?php
                                 $no = 1;
                                 $query = "SELECT l.*, u.fullname 
                                     FROM logactivity l
                                     JOIN users u ON l.iduser = u.idusers
                                      WHERE l.waktu BETWEEN '$awal' AND '$akhir'
                                     ORDER BY l.waktu DESC";
                                 $result = mysqli_query($conn, $query);

                                 while ($row = mysqli_fetch_array($result)) {
                                 ?>
                                    <tr>
                                       <td class="text-center"><?= $no; ?></td>
                                       <td><?= $row['fullname']; ?></td>
                                       <td><?= $row['event']; ?></td>
                                       <td><?= $row['docnumb']; ?></td>
                                       <td class="text-center"><?= date("d-M-y H:i:s", strtotime($row['waktu'])); ?></td>
                                    </tr>
                                 <?php
                                    $no++;
                                 }
                                 ?>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </section>
      </div>
      <!-- Control Sidebar -->
      <aside class="control-sidebar control-sidebar-dark">
         <!-- Control sidebar content goes here -->
      </aside>
      <!-- /.control-sidebar -->
      <?php
      $year = date('Y');
      ?>

      <!-- Main Footer -->
      <footer class="main-footer">
         <strong>Copyright &copy; <?= $year ?> <a href="https://instagram.com/asep_idung">idung</a>.</strong>
         <!-- <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> 1.0.0 || Template By adminLTE
         </div> -->
      </footer>
   </div>
   <!-- ./wrapper -->
   <!-- REQUIRED SCRIPTS -->
   <!-- jQuery -->
   <script src="plugins/jquery/jquery.min.js"></script>
   <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
   <script src="plugins/select2/js/select2.full.min.js"></script>
   <script src="plugins/bootstrap4-duallistbox/jquery.bootstrap-duallistbox.min.js"></script>
   <script src="plugins/moment/moment.min.js"></script>
   <script src="plugins/inputmask/jquery.inputmask.min.js"></script>
   <script src="plugins/daterangepicker/daterangepicker.js"></script>
   <script src="plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js"></script>
   <script src="plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
   <script src="plugins/bs-stepper/js/bs-stepper.min.js"></script>
   <script src="plugins/dropzone/min/dropzone.min.js"></script>
   <script src="dist/js/adminlte.min.js"></script>
   <script src="plugins/datatables/jquery.dataTables.min.js"></script>
   <script src="plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
   <script src="plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
   <script src="plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
   <script src="plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
   <script src="plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
   <script src="plugins/jszip/jszip.min.js"></script>
   <script src="plugins/pdfmake/pdfmake.min.js"></script>
   <script src="plugins/pdfmake/vfs_fonts.js"></script>
   <script src="plugins/datatables-buttons/js/buttons.html5.min.js"></script>
   <script src="plugins/datatables-buttons/js/buttons.print.min.js"></script>

   <!-- Page specific script -->
   <script>
      $(function() {
         //Initialize Select2 Elements
         $('.select2').select2()
         //Initialize Select2 Elements
         $('.select2bs4').select2({
            theme: 'bootstrap4'
         })
         $("#example1").DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "buttons": ["copy", "excel", "pdf", "print", "colvis"]
         }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
         $('#example2').DataTable({
            "paging": true,
            "lengthChange": false,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": true,
            "responsive": true,
         });
      });
   </script>
</body>

</html>