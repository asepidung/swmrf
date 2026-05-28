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
         <div class="row mb-2">
            <div class="col-sm-6">
               <a href="newbarang.php" class="btn bg-gradient-success btn-md shadow-sm">
                  <i class="fas fa-plus-circle"></i> Product Baru
               </a>
            </div>
         </div>
      </div>
   </div>

   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col">
               <div class="card">
                  <div class="card-body">
                     <div class="col">
                        <!-- Notifikasi sukses -->
                        <?php if (isset($_GET['msg'])): ?>
                           <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                              <?= html_entity_decode(urldecode($_GET['msg'])); ?>
                              <button type="button" class="close" data-dismiss="alert">&times;</button>
                           </div>
                        <?php endif; ?>

                        <table id="example1" class="table table-bordered table-striped table-hover table-sm">
                           <thead class="text-center bg-light">
                              <tr>
                                 <th>#</th>
                                 <th>Kode</th>
                                 <th>Nama Product</th>
                                 <th>Kategori</th>
                                 <th>B.O.M</th>
                                 <th>Actions</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              // ✅ Query untuk cek apakah barang punya BOM aktif
                              $ambildata = mysqli_query($conn, "
                                 SELECT b.*, c.nmcut,
                                        CASE 
                                           WHEN EXISTS (
                                              SELECT 1 FROM bom_rawmate br 
                                              WHERE br.idbarang = b.idbarang 
                                              AND br.is_active = 1
                                           ) THEN 1 
                                           ELSE 0 
                                        END AS has_bom
                                 FROM barang b
                                 LEFT JOIN cuts c ON b.idcut = c.idcut
                                 ORDER BY b.nmbarang ASC
                              ");
                              while ($tampil = mysqli_fetch_array($ambildata)) {
                              ?>
                                 <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td class="text-center"><?= htmlspecialchars($tampil['kdbarang']); ?></td>
                                    <td class="text-left"><?= htmlspecialchars($tampil['nmbarang']); ?></td>
                                    <td class="text-center"><?= htmlspecialchars($tampil['nmcut']); ?></td>

                                    <!-- Kolom Status B.O.M -->
                                    <td class="text-center">
                                       <?php if ($tampil['has_bom'] == 1): ?>
                                          <span class="badge badge-success px-2 py-1 shadow-sm">
                                             <i class="fas fa-check-circle"></i> Aktif
                                          </span>
                                       <?php else: ?>
                                          <span class="badge badge-secondary px-2 py-1 shadow-sm">
                                             <i class="fas fa-minus-circle"></i> Belum
                                          </span>
                                       <?php endif; ?>
                                    </td>

                                    <!-- Kolom Actions -->
                                    <td class="text-center">
                                       <a href="managebom.php?id=<?= $tampil['idbarang']; ?>"
                                          class="btn btn-sm btn-info"
                                          title="Kelola BOM">
                                          <i class="fas fa-bomb"></i>
                                       </a>
                                       <a href="editbarang.php?idbarang=<?= $tampil['idbarang']; ?>"
                                          class="btn btn-sm btn-warning mx-1" title="Edit">
                                          <i class="fas fa-edit"></i>
                                       </a>
                                       <a href="deletebarang.php?idbarang=<?= $tampil['idbarang']; ?>"
                                          class="btn btn-sm btn-danger mx-1"
                                          title="Hapus"
                                          onclick="return confirm('Apakah Anda yakin ingin menghapus barang ini?')">
                                          <i class="fas fa-trash-alt"></i>
                                       </a>
                                    </td>
                                 </tr>
                              <?php } ?>
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
   document.title = "DATA BARANG";

   $(function() {
      $("#example1").DataTable({
         responsive: true,
         lengthChange: true,
         autoWidth: false,
         buttons: ["copy", "excel", "pdf", "print"]
      }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
   });
</script>

<?php
include "../footer.php";
include "../footnote.php";
?>