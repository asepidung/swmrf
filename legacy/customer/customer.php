<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
   <!-- Content Header (Page header) -->
   <div class="content-header">
      <div class="container-fluid">
         <div class="row mb-2">
            <div class="col-sm-6">
               <a href="newcustomer.php"><button type="button" class="btn btn-warning btn-sm"><i class="fas fa-plus"></i> Baru</button></a>
            </div>
         </div>
      </div>
   </div>

   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12">
               <div class="card">
                  <div class="card-body">
                     <div class="table-responsive">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead>
                              <tr class="text-center">
                                 <th>#</th>
                                 <th>Nama Customer</th>
                                 <th>Alamat</th>
                                 <th>Bank</th>
                                 <th>Group</th>
                                 <th>T.O.P</th>
                                 <th>Pajak</th>
                                 <th>T.T.F</th>
                                 <th>Catatan</th>
                                 <th>Aksi</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $ambildata = mysqli_query($conn, "SELECT c.*, s.nmsegment, g.nmgroup
                              FROM customers c
                              JOIN segment s ON c.idsegment = s.idsegment
                              LEFT JOIN groupcs g ON c.idgroup = g.idgroup
                              ORDER BY c.nama_customer ASC
                              ");
                              while ($tampil = mysqli_fetch_array($ambildata)) {
                              ?>
                                 <tr>
                                    <td><?= $no; ?></td>
                                    <td><?= $tampil['nama_customer']; ?></td>
                                    <td class="text-truncate" style="max-width: 150px;" title="<?= $tampil['alamat1']; ?>">
                                       <?= $tampil['alamat1']; ?>
                                    </td>
                                    <td><?= $tampil['nmsegment']; ?></td>
                                    <td><?= $tampil['nmgroup']; ?></td>
                                    <td><?= $tampil['top'] . " Hari"; ?></td>
                                    <td class="text-center"><?= $tampil['pajak']; ?></td>
                                    <td class="text-center"><?= $tampil['tukarfaktur']; ?></td>
                                    <td><?= $tampil['catatan']; ?></td>
                                    <td class="text-center">
                                       <a href="editcust.php?id=<?= $tampil['idcustomer']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-pen"></i></a>
                                       <a href="deletecustomer.php?id=<?= $tampil['idcustomer']; ?>" class="btn btn-sm btn-danger"><i class="fas fa-trash-alt"></i></a>
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
         </div>
      </div>
   </section>
</div>

<?php include "../footer.php" ?>