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
               <a href="newrawcategory.php"><button type="button" class="btn btn-info"> Kategori Baru</button></a>
            </div>
         </div>
      </div>
   </div>

   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-md">
               <div class="card">
                  <div class="card-body">
                     <div class="col">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>#</th>
                                 <th>Nama Kategery</th>
                                 <th>Actions</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $ambildata = mysqli_query($conn, "SELECT * FROM rawcategory ORDER BY nmcategory");
                              while ($tampil = mysqli_fetch_array($ambildata)) {
                                 $idrawcategory = $tampil['idrawcategory'];
                              ?>
                                 <tr class="text-right">
                                    <td class="text-center"><?= $no; ?></td>
                                    <td class="text-left"><?= $tampil['nmcategory']; ?></td>
                                    <td class="text-center">
                                       <a href="editrawcategory.php?idrawcategory=<?= $tampil['idrawcategory']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-pencil-alt"></i></a>
                                       <a href="deleterawcategory.php?idrawcategory=<?= $tampil['idrawcategory']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('apakah anda yakin ingin menghapus rawcategory ini?')"><i class="fas fa-minus-square"></i></a>
                                    </td>
                                 </tr>
                              <?php $no++;
                              } ?>
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
<script>
   document.title = "RAW KATEGORI";
</script>
<?php
include "../footer.php";
include "../footnote.php";
?>