<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>
<div class="content-wrapper">
   <div class="content-header">
      <div class="container-fluid">
         <div class="row">
            <div class="col">
               <!-- <h1 class="m-0">DATA BONING</h1> -->
               <a href="newmt.php"><button type="button" class="btn btn-outline-primary"><i class="fab fa-firstdraft"></i> Baru</button></a>
            </div><!-- /.col -->
         </div><!-- /.row -->
      </div><!-- /.container-fluid -->
   </div>
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
                              <th>Mutasi Date</th>
                              <th>Number</th>
                              <th>Tujuan</th>
                              <th>Driver</th>
                              <th>Catatan</th>
                              <th>User</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           $ambildata = mysqli_query($conn, "SELECT mutasi.*, users.fullname
                                  FROM mutasi
                                  INNER JOIN users ON mutasi.idusers = users.idusers
                                  WHERE mutasi.is_deleted = 0
                                  ORDER BY mutasi.idmutasi DESC");
                           while ($tampil = mysqli_fetch_array($ambildata)) {
                           ?>
                              <tr class="text-right">
                                 <td class="text-center"><?= $no ?></td>
                                 <td class="text-center"><?= date("d-M-y", strtotime($tampil['tglmutasi'])) ?></td>
                                 <td class="text-center"><?= $tampil['nomutasi'] ?></td>
                                 <td class="text-center"><?= $tampil['gudang'] ?></td>
                                 <td class="text-center"><?= $tampil['driver'] ?></td>
                                 <td class="text-left"><?= $tampil['note'] ?></td>
                                 <td class="text-left"><?= $tampil['fullname'] ?></td>
                                 <td class="text-center">
                                    <a href="mutasidetail.php?id=<?= $tampil['idmutasi']; ?>&stat=ready" class="btn btn-xs btn-warning">
                                       <i class="fas fa-barcode"></i>
                                    </a>
                                    <a href="lihatmutasi.php?id=<?= $tampil['idmutasi']; ?>" class="btn btn-xs btn-success">
                                       <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="printmutasi.php?id=<?= $tampil['idmutasi']; ?>" class="btn btn-xs btn-secondary">
                                       <i class="fas fa-print"></i>
                                    </a>
                                    <a href="deletemutasi.php?id=<?= $tampil['idmutasi']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Apakah kamu yakin akan menghapus data ini?');">
                                       <i class="fas fa-trash"></i>
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
   document.title = "Mutasi";
</script>
<?php
// require "../footnote.php";
include "../footer.php" ?>