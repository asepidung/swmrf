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
               <a href="newrawmate.php" class="btn bg-gradient-success btn-md shadow-sm">
                  <i class="fas fa-plus-circle"></i> Material Baru
               </a>
            </div>
         </div>
      </div>
   </div>

   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-10">
               <div class="card">
                  <div class="card-body">
                     <div class="col">
                        <table id="example1" class="table table-bordered table-striped table-hover table-sm">
                           <thead class="text-center bg-light">
                              <tr>
                                 <th>#</th>
                                 <th>Kode</th>
                                 <th>Nama Material</th>
                                 <th>Satuan</th>
                                 <th>Kategori</th>
                                 <th>View In Stock</th>
                                 <th>BMS</th>
                                 <th>Actions</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $ambildata = mysqli_query($conn, "
                                 SELECT rawmate.*, rawcategory.nmcategory 
                                 FROM rawmate 
                                 LEFT JOIN rawcategory ON rawmate.idrawcategory = rawcategory.idrawcategory 
                                 ORDER BY nmrawmate ASC
                              ");
                              while ($tampil = mysqli_fetch_array($ambildata)) {
                                 $viewInStock = ($tampil['stock'] == 1) ? 'YES' : ''; // logika utama
                              ?>
                                 <tr>
                                    <td class="text-center"><?= $no; ?></td>
                                    <td class="text-center"><?= htmlspecialchars($tampil['kdrawmate'] ?? ''); ?></td>
                                    <td class="text-left"><?= htmlspecialchars($tampil['nmrawmate'] ?? ''); ?></td>
                                    <td class="text-center"><?= htmlspecialchars($tampil['unit'] ?? ''); ?></td>
                                    <td class="text-left"><?= htmlspecialchars($tampil['nmcategory'] ?? ''); ?></td>
                                    <td class="text-center"><?= $viewInStock; ?></td>
                                    <td class="text-center"><?= htmlspecialchars($tampil['barmin'] ?? ''); ?></td>
                                    <td class="text-center">
                                       <a href="editrawmate.php?idrawmate=<?= $tampil['idrawmate']; ?>" class="btn btn-sm btn-warning mx-1" title="Edit">
                                          <i class="fas fa-edit"></i>
                                       </a>
                                       <a href="deleterawmate.php?idrawmate=<?= $tampil['idrawmate']; ?>" class="btn btn-sm btn-danger mx-1" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus material ini?')">
                                          <i class="fas fa-trash-alt"></i>
                                       </a>
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
<script>
   document.title = "RAW MATERIAL";
</script>
<?php
include "../footer.php";
?>