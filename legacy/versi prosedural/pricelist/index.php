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
         <div class="row">
            <div class="col">
               <!-- <h1 class="m-0">DATA BONING</h1> -->
               <a href="newpricelist.php"><button type="button" class="btn btn-outline-primary"><i class="fas fa-plus"></i> Baru</button></a>
            </div><!-- /.col -->
         </div><!-- /.row -->
      </div><!-- /.container-fluid -->
   </div>
   <!-- /.content-header -->

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
                              <th>Group</th>
                              <th>UP</th>
                              <th>T.O.P</th>
                              <th>Last Update</th>
                              <th>Note</th>
                              <th>Actions</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           $ambildata = mysqli_query($conn, "SELECT pricelist.*, groupcs.nmgroup, groupcs.terms 
                                   FROM pricelist 
                                   JOIN groupcs ON pricelist.idgroup = groupcs.idgroup");
                           while ($tampil = mysqli_fetch_array($ambildata)) {
                           ?>
                              <tr>
                                 <td class="text-center"><?= $no; ?></td>
                                 <td><?= $tampil['nmgroup']; ?></td>
                                 <td><?= $tampil['up']; ?></td>
                                 <td><?= $tampil['terms']; ?></td>
                                 <td class="text-center"><?= date("d-M-y", strtotime($tampil['latestupdate'])); ?></td>
                                 <td><?= $tampil['note']; ?></td>
                                 <!-- <td class="text-center"><?= $tampil['fullname']; ?></td> -->
                                 <td class="text-center">
                                    <a class="btn btn-success btn-sm" href="lihatpricelist.php?idpricelist=<?= $tampil['idpricelist'] ?>">
                                       <i class="fas fa-eye"></i>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="editpricelist.php?idpricelist=<?= $tampil['idpricelist'] ?>">
                                       <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <a class="btn btn-primary btn-sm" href="cetakpricelist.php?idpricelist=<?= $tampil['idpricelist'] ?>">
                                       <i class="fas fa-coffee"></i>
                                    </a>
                                    <a class="btn btn-danger btn-sm" href="deletepricelist.php?idpricelist=<?= $tampil['idpricelist'] ?>">
                                       <i class="fas fa-minus-square"></i>
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
   // Mengubah judul halaman web
   document.title = "Price List";
</script>
<?php
// require "../footnote.php";
include "../footer.php" ?>