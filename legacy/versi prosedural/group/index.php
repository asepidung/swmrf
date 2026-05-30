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
               <a href="newgroup.php"><button type="button" class="btn btn-info"> Grup Baru</button></a>
            </div>
         </div>
      </div>
   </div>

   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col">
               <div class="card">
                  <div class="card-body">
                     <div class="col">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>#</th>
                                 <th>Nama Grup</th>
                                 <th>Actions</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $ambildata = mysqli_query($conn, "SELECT * FROM groupcs ORDER BY nmgroup ASC");
                              while ($tampil = mysqli_fetch_array($ambildata)) {
                                 $idgroup = $tampil['idgroup'];
                              ?>
                                 <tr class="text-right">
                                    <td class="text-center"><?= $no; ?></td>
                                    <td class="text-left"><?= $tampil['nmgroup']; ?></td>
                                    <td class="text-center">
                                       <a href="editgroup.php?idgroup=<?= $tampil['idgroup']; ?>" class="text-succes mx-auto p-2"><i class="fas fa-pencil-alt"></i></a>
                                       <a href="deletegroup.php?idgroup=<?= $tampil['idgroup']; ?>" class="text-danger mx-auto p-2" onclick="return confirm('apakah anda yakin ingin menghapus group ini?')"><i class="fas fa-minus-square"></i></a>
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
   document.title = "GROUP CUSTOMERS";
</script>
<?php
include "../footer.php";
include "../footnote.php";
?>