<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');

$queryMaxDate = "SELECT MAX(deliverydate) AS max_date FROM salesorder";
$resultMaxDate = mysqli_query($conn, $queryMaxDate);
$rowMaxDate = mysqli_fetch_assoc($resultMaxDate);
$maxDate = $rowMaxDate['max_date'];

$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : $maxDate;
?>

<div class="content-wrapper">
   <div class="content-header">
      <div class="container-fluid">
         <div class="row mb-2 align-items-center">

            <!-- LEFT : BARU + DETAIL (MOBILE STACK) -->
            <div class="col-sm-3 col-12 mb-2">

               <!-- BARU -->
               <a href="newso.php" class="btn btn-sm btn-outline-primary btn-block mb-2">
                  <i class="fas fa-plus"></i> Baru
               </a>

               <!-- DETAIL (MOBILE ONLY) -->
               <a href="salesorderdetail.php"
                  class="btn btn-sm btn-outline-success btn-block d-sm-none">
                  <i class="fas fa-eye"></i> Detail
               </a>

            </div>

            <!-- RIGHT : DETAIL DESKTOP + FILTER -->
            <div class="col-sm-9 col-12">
               <div class="d-flex flex-wrap justify-content-sm-end align-items-center">

                  <!-- DETAIL (DESKTOP ONLY) -->
                  <a href="salesorderdetail.php"
                     class="btn btn-sm btn-outline-success mr-sm-3 mb-2 d-none d-sm-inline">
                     <i class="fas fa-eye"></i> Detail
                  </a>

                  <!-- FILTER -->
                  <form method="GET" class="form-inline flex-wrap mb-2">
                     <label class="mr-2 font-weight-bold">Periode</label>

                     <input type="date" name="awal"
                        value="<?= htmlspecialchars($awal) ?>"
                        class="form-control form-control-sm mr-2 mb-1">

                     <span class="mr-2 mb-1">s/d</span>

                     <input type="date" name="akhir"
                        value="<?= htmlspecialchars($akhir) ?>"
                        class="form-control form-control-sm mr-2 mb-1">

                     <button type="submit" class="btn btn-sm btn-primary mb-1">
                        <i class="fas fa-filter"></i> Filter
                     </button>
                  </form>

               </div>
            </div>

         </div>
      </div>
   </div>

   <section class="content">
      <div class="container-fluid">
         <div class="card">
            <div class="card-body">
               <div class="table-responsive">
                  <table id="example1" class="table table-bordered table-striped table-sm">
                     <thead class="text-center">
                        <tr>
                           <th>#</th>
                           <th>SO Number</th>
                           <th>Customer</th>
                           <th>Tgl Kirim</th>
                           <th>PO</th>
                           <th>Note</th>
                           <th>Progress</th>
                           <th>Made By</th>
                           <th>Action</th>
                        </tr>
                     </thead>

                     <tbody>
                        <?php
                        $no = 1;
                        $ambildata = mysqli_query($conn, "
                           SELECT salesorder.*, customers.nama_customer, users.fullname
                           FROM salesorder
                           INNER JOIN customers ON salesorder.idcustomer = customers.idcustomer
                           INNER JOIN users ON salesorder.idusers = users.idusers
                           WHERE salesorder.deliverydate BETWEEN '$awal' AND '$akhir'
                           AND salesorder.is_deleted = 0
                           ORDER BY salesorder.idso DESC
                        ");

                        while ($tampil = mysqli_fetch_array($ambildata)) {
                           $progress = $tampil['progress'];
                        ?>
                           <tr>
                              <td class="text-center"><?= $no; ?></td>
                              <td class="text-center"><?= $tampil['sonumber']; ?></td>
                              <td><?= $tampil['nama_customer']; ?></td>
                              <td class="text-center"><?= date("d-M-y", strtotime($tampil['deliverydate'])); ?></td>
                              <td><?= $tampil['po']; ?></td>
                              <td><?= $tampil['note']; ?></td>

                              <!-- PROGRESS -->
                              <td class="text-center">
                                 <?php if ($progress == "Cancel") { ?>
                                    <span class="badge badge-danger">
                                       <i class="fas fa-ban"></i> Cancel
                                    </span>
                                 <?php } elseif ($progress == "Delivered") { ?>
                                    <span class="badge badge-success">
                                       <i class="fas fa-check-circle"></i> Delivered
                                    </span>
                                 <?php } elseif ($progress == "On Process") { ?>
                                    <span class="badge badge-info">
                                       <i class="fas fa-spinner fa-pulse"></i> On Process
                                    </span>
                                 <?php } elseif ($progress == "On Delivery") { ?>
                                    <span class="badge badge-purple">
                                       <i class="fas fa-truck"></i> On Delivery
                                    </span>
                                 <?php } elseif ($progress == "DRAFT") { ?>
                                    <span class="badge badge-secondary">
                                       <i class="fas fa-pencil-alt"></i> Draft
                                    </span>
                                 <?php } elseif ($progress == "Rejected") { ?>
                                    <span class="badge badge-danger">
                                       <i class="fas fa-eject"></i> Rejected
                                    </span>
                                 <?php } else { ?>
                                    <span class="badge badge-light">
                                       <i class="fas fa-clock"></i> Waiting
                                    </span>
                                 <?php } ?>
                              </td>

                              <td><?= $tampil['fullname']; ?></td>

                              <!-- ACTION -->
                              <td class="text-center">
                                 <?php if ($progress == "Cancel") { ?>
                                    <a href="lihatso.php?idso=<?= $tampil['idso']; ?>"
                                       class="btn btn-sm btn-outline-secondary">
                                       <i class="far fa-eye"></i>
                                    </a>

                                 <?php } elseif ($progress == "Waiting") { ?>
                                    <a href="hideprice.php?idso=<?= $tampil['idso']; ?>" class="btn btn-sm btn-warning">
                                       <i class="fas fa-eye-slash"></i>
                                    </a>
                                    <a href="lihatso.php?idso=<?= $tampil['idso']; ?>" class="btn btn-sm btn-primary">
                                       <i class="far fa-eye"></i>
                                    </a>
                                    <a href="editso.php?idso=<?= $tampil['idso']; ?>" class="btn btn-sm btn-success">
                                       <i class="far fa-edit"></i>
                                    </a>
                                    <a href="deleteso.php?idso=<?= $tampil['idso']; ?>"
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                       <i class="far fa-trash-alt"></i>
                                    </a>

                                 <?php } else { ?>
                                    <a href="hideprice.php?idso=<?= $tampil['idso']; ?>" class="btn btn-sm btn-warning">
                                       <i class="fas fa-eye-slash"></i>
                                    </a>
                                    <a href="lihatso.php?idso=<?= $tampil['idso']; ?>" class="btn btn-sm btn-primary">
                                       <i class="far fa-eye"></i>
                                    </a>
                                 <?php } ?>
                              </td>
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
   </section>
</div>

<script>
   document.title = "Sales Order List";
</script>

<?php include "../footer.php"; ?>