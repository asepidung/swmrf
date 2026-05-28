<?php
require "verifications/auth.php";
require "konak/conn.php";

$fullname = $_SESSION['fullname'];
$idusers = $_SESSION['idusers'];
$query = "SELECT * FROM role WHERE idusers = $idusers";
$result = mysqli_query($conn, $query);
$role = mysqli_fetch_assoc($result);
include "kebutuhanindex.php";
include "notifcount.php";
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
         <ul class="navbar-nav ml-auto">
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
                  <a href="verifications/edituser.php?id=<?= $idusers ?>" class="d-block"><?= $fullname; ?></a>
               </div>
            </div>
            <!-- Sidebar Menu -->
            <nav class="mt-2">
               <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                  <?php if ($role['cattle'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-dragon"></i>
                           <p>
                              CATTLE
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="cattlereceive" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Penerimaan Sapi</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="weighing" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Timbang Ulang</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="carcas" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Carcase</p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['produksi'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-people-carry"></i>
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
                                 <p>Repack
                                    <span class="badge badge-info right"><?= $repackCount ?></span>
                                 </p>
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

                  <?php if (isset($role['warehouse']) && $role['warehouse'] == 1) : ?>
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
                                 <p>Tally Sheet
                                    <span class="badge badge-info right"><?= isset($drafttally) ? $drafttally : '0' ?></span>
                                 </p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="#" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>
                                    Goods Receipt
                                    <i class="right fas fa-angle-left"></i>
                                 </p>
                              </a>
                              <ul class="nav nav-treeview">
                                 <li class="nav-item">
                                    <a href="grbeef" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>Daging</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="gr" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>Non Daging
                                          <?php if ($poBelumGRCount > 0): ?>
                                             <span class="badge badge-danger right"><?= $poBelumGRCount ?></span>
                                          <?php endif; ?>
                                       </p>
                                    </a>
                                 </li>
                              </ul>
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
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['qc'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-check-double"></i>
                           <p>
                              QC/QA Monitoring
                              <i class="right fas fa-angle-left"></i>
                              <?php if ($pendingQC > 0): ?>
                                 <span class="badge badge-danger"><?= $pendingQC ?></span>
                              <?php endif; ?>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="monitoring_produksi/" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>Monitoring
                                 </p>
                              </a>
                           </li>
                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if (isset($role['stock']) && $role['stock'] == 1) : ?>
                     <li class="nav-item has-treeview">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-boxes"></i>
                           <p>
                              STOCK
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>

                        <ul class="nav nav-treeview">

                           <!-- PERSEDIAAN -->
                           <li class="nav-item has-treeview">
                              <a href="#" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>
                                    Persediaan
                                    <i class="right fas fa-angle-left"></i>
                                 </p>
                              </a>
                              <ul class="nav nav-treeview">
                                 <li class="nav-item">
                                    <a href="stock/" class="nav-link">
                                       <i class="far fa-circle nav-icon"></i>
                                       <p>Data Stock (Daging)</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="stockraw/" class="nav-link">
                                       <i class="far fa-circle nav-icon"></i>
                                       <p>Stock Raw (Bahan Penolong)</p>
                                    </a>
                                 </li>
                              </ul>
                           </li>

                           <!-- OPERASIONAL -->
                           <li class="nav-item has-treeview">
                              <a href="#" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>
                                    Operasional
                                    <i class="right fas fa-angle-left"></i>
                                 </p>
                              </a>
                              <ul class="nav nav-treeview">
                                 <li class="nav-item">
                                    <a href="stockin/" class="nav-link">
                                       <i class="far fa-circle nav-icon"></i>
                                       <p>Stock In (Input Daging Baru)</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="material" class="nav-link">
                                       <i class="far fa-circle nav-icon"></i>
                                       <p>Stock Out (Bahan Material)</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="stocktake" class="nav-link">
                                       <i class="far fa-circle nav-icon"></i>
                                       <p>Stock Take (Opname)</p>
                                    </a>
                                 </li>
                              </ul>
                           </li>

                           <!-- MONITORING -->
                           <li class="nav-item has-treeview">
                              <a href="#" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>
                                    Monitoring
                                    <i class="right fas fa-angle-left"></i>
                                 </p>
                              </a>
                              <ul class="nav nav-treeview">
                                 <li class="nav-item">
                                    <a href="stock/tofroz.php" class="nav-link">
                                       <i class="far fa-circle nav-icon"></i>
                                       <p>&gt; 60 Days (Umur Stok)</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="track" class="nav-link">
                                       <i class="far fa-circle nav-icon"></i>
                                       <p>Track Product (Barcode)</p>
                                    </a>
                                 </li>
                              </ul>
                           </li>

                        </ul>
                     </li>
                  <?php endif; ?>

                  <?php if ($role['distributions'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
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
                                 <p>Delivery Order
                                    <span class="badge badge-info right"><?= $draftdo ?></span>
                                 </p>
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

                  <!-- requisition -->
                  <li class="nav-item">
                     <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-hand-holding-usd"></i>
                        <p>
                           REQUISITION
                           <?php if ($idusers == 13): ?>
                              <?php if ($TotalRequest > 0): ?>
                                 <span class="badge badge-warning right"><?= $TotalRequest ?></span>
                              <?php endif; ?>
                              <?php if ($TotalOrdering > 0): ?>
                                 <span class="badge badge-primary right"><?= $TotalOrdering ?></span>
                              <?php endif; ?>
                           <?php elseif ($idusers == 15): ?>
                              <?php if ($TotalWaiting > 0): ?>
                                 <span class="badge badge-warning right"><?= $TotalWaiting ?></span>
                              <?php endif; ?>
                           <?php endif; ?>
                           <i class="right fas fa-angle-left"></i>
                        </p>
                     </a>
                     <ul class="nav nav-treeview">
                        <li class="nav-item">
                           <a href="requisitionbeef/index.php" class="nav-link">
                              <i class="far fa-circle nav-icon"></i>
                              <p>Daging
                                 <?php if ($idusers == 13 && $CountRequest > 0): ?>
                                    <span class="badge badge-warning right"><?= $CountRequest ?></span>
                                 <?php elseif ($idusers == 15 && $CountWaiting > 0): ?>
                                    <span class="badge badge-warning right"><?= $CountWaiting ?></span>
                                 <?php elseif ($idusers == 13 && $CountOrdering > 0): ?>
                                    <span class="badge badge-primary right"><?= $CountOrdering ?></span>
                                 <?php endif; ?>
                              </p>
                           </a>
                        </li>
                        <li class="nav-item">
                           <a href="requisition/index.php" class="nav-link">
                              <i class="far fa-circle nav-icon"></i>
                              <p>Non Daging
                                 <?php if ($idusers == 13 && $CountRequestNonDaging > 0): ?>
                                    <span class="badge badge-warning right"><?= $CountRequestNonDaging ?></span>
                                 <?php elseif ($idusers == 15 && $CountWaitingNonDaging > 0): ?>
                                    <span class="badge badge-warning right"><?= $CountWaitingNonDaging ?></span>
                                 <?php elseif ($idusers == 13 && $CountOrderingNonDaging > 0): ?>
                                    <span class="badge badge-primary right"><?= $CountOrderingNonDaging ?></span>
                                 <?php endif; ?>
                              </p>
                           </a>
                        </li>
                     </ul>
                  </li>

                  <?php if ($role['purchase_module'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-shopping-cart"></i>
                           <p>
                              PURCHASE ORDER
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <li class="nav-item">
                              <a href="cattle/" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>Cattle</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="pobeef/" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>Daging</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="po/" class="nav-link">
                                 <i class="far fa-dot-circle nav-icon"></i>
                                 <p>Non Daging</p>
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
                        <a href="#" class="nav-link">
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
                                 <p>Invoice
                                    <span class="badge badge-info right"><?= $draftinvoice ?></span>
                                 </p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="piutang" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Piutang</p>
                              </a>
                           </li>

                           <!-- CATTLE FINANCE -->
                           <li class="nav-item">
                              <a href="#" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>
                                    Cattle
                                    <i class="right fas fa-angle-left"></i>
                                 </p>
                              </a>
                              <ul class="nav nav-treeview">
                                 <li class="nav-item">
                                    <a href="weighinglost" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>Weight Loss
                                          <span class="badge badge-info right"><?= $draftloss ?></span>
                                       </p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="killinglost" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>Killing Lost</p>
                                    </a>
                                 </li>
                              </ul>
                           </li>

                        </ul>
                     </li>
                  <?php endif; ?>


                  <?php if ($role['data_report'] == 1) : ?>
                     <li class="nav-item">
                        <a href="#" class="nav-link">
                           <i class="nav-icon fas fa-chart-line"></i>
                           <p>
                              DATA REPORT
                              <i class="right fas fa-angle-left"></i>
                           </p>
                        </a>
                        <ul class="nav nav-treeview">
                           <!-- Submenu baru: Produk Fast Moving -->
                           <li class="nav-item">
                              <a href="reports/fast_moving.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Produk Fast Moving</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="reports/sales.php" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Penjualan</p>
                              </a>
                           </li>
                           <li class="nav-item">
                              <a href="stock" class="nav-link">
                                 <i class="far fa-circle nav-icon"></i>
                                 <p>Stock</p>
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
                                       <p>Daging</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="rawmate/" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>Bahan Penolong</p>
                                    </a>
                                 </li>
                                 <li class="nav-item">
                                    <a href="rawcategory/" class="nav-link">
                                       <i class="far fa-dot-circle nav-icon"></i>
                                       <p>Raw Category</p>
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
         <section class="content">
            <div class="container-fluid">
               <div class="row mt-3">
                  <div class="col-lg col-6">
                     <div class="small-box bg-danger">
                        <div class="inner">
                           <h3>Stock</h3>
                           <p>Klik for Detail</p>
                        </div>
                        <div class="icon">
                           <i class="fas fa-cubes"></i>
                        </div>
                        <a href="stock/" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                     </div>
                  </div>
                  <div class="col-lg col-6">
                     <div class="small-box bg-info">
                        <div class="inner">
                           <h3><?= $futureDeliveryCount; ?></h3>
                           <p>Plan Delivery</p>
                        </div>
                        <div class="icon">
                           <i class="fas fa-truck"></i>
                        </div>
                        <a href="plandev/" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                     </div>
                  </div>
                  <div class="col-lg col-6">
                     <div class="small-box bg-success">
                        <div class="inner">
                           <h3><?= $deliverytoday; ?></h3>
                           <p>Delivery Today</p>
                        </div>
                        <div class="icon">
                           <i class="fas fa-truck-moving"></i>
                        </div>
                        <a href="do/dotoday.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                     </div>
                  </div>
                  <div class="col-lg col-6">
                     <div class="small-box bg-warning">
                        <div class="inner">
                           <h3><?= $pobeefCount ?></h3>
                           <p>Arrival Plans</p>
                        </div>
                        <div class="icon">
                           <i class="fas fa-truck-loading"></i>
                        </div>
                        <a href="grbeef/draft.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                     </div>
                  </div>
               </div>
               <?php include "quotes.php"; ?>
            </div>
         </section>
         <!-- /.content -->
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
            "searching": false,
            "ordering": true,
            "info": true,
            "autoWidth": true,
            "responsive": true,
         });
      });
   </script>
</body>

</html>