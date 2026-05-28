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
               <a href="newst.php" class="btn btn-outline-primary">
                  <i class="fab fa-firstdraft"></i> Buat Baru
               </a>
            </div>
         </div>
      </div>
   </div>

   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12">
               <div class="card">
                  <div class="card-body">
                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>Taking Date</th>
                              <th>Number</th>
                              <th>Catatan</th>
                              <th>Stock Miss</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           $stmt = $conn->prepare("SELECT * FROM stocktake ORDER BY nost DESC");
                           $stmt->execute();
                           $result = $stmt->get_result();

                           if ($result->num_rows > 0) {
                              while ($row = $result->fetch_assoc()) { ?>
                                 <tr class="text-right">
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td class="text-center"><?= date("d-M-y", strtotime($row['tglst'])); ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['nost']); ?></td>
                                    <td class="text-left"><?= htmlspecialchars($row['note']); ?></td>
                                    <td class="text-center">
                                       <a href="<?= ($row['stocked'] == 0) ? 'waiting.php' : 'stockmiss.php'; ?>?id=<?= $row['idst']; ?>"
                                          class="<?= ($row['stocked'] == 0) ? 'text-success' : 'text-danger'; ?>">
                                          <?= ($row['stocked'] == 0) ? 'Waiting Stock' : 'Missing Stock'; ?>
                                       </a>
                                    </td>
                                    <td class="text-center">
                                       <div class="btn-group">
                                          <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown">
                                             Pilih Proses
                                          </button>
                                          <div class="dropdown-menu">
                                             <?php if ($row['stocked'] == 0): ?>
                                                <a class="dropdown-item" href="starttaking.php?id=<?= $row['idst']; ?>&stat=ready">
                                                   <i class="fas fa-barcode"></i> Mulai Stock Take
                                                </a>
                                             <?php endif; ?>

                                             <a class="dropdown-item" href="lihatst.php?id=<?= $row['idst']; ?>">
                                                <i class="fas fa-eye"></i> Lihat
                                             </a>
                                             <a class="dropdown-item" href="printst.php?id=<?= $row['idst']; ?>">
                                                <i class="fas fa-print"></i> Cetak
                                             </a>
                                             <?php if ($_SESSION['idusers'] == 1 || $_SESSION['idusers'] == 2): ?>
                                                <?php if ($row['stocked'] == 0): ?>
                                                   <a class="dropdown-item" href="#" onclick="confirmStockIn(<?= $row['idst']; ?>)">
                                                      <i class="fas fa-upload"></i> Konfirmasi Stock In
                                                   </a>
                                                <?php endif; ?>
                                             <?php endif; ?>
                                             <a class="dropdown-item" href="editst.php?id=<?= $row['idst']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                             </a>
                                             <?php if ($row['stocked'] == 0): ?>
                                                <a class="dropdown-item text-danger" href="deletest.php?id=<?= $row['idst']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                   <i class="fas fa-trash"></i> Hapus
                                                </a>
                                             <?php endif; ?>
                                          </div>
                                       </div>
                                    </td>

                                 </tr>
                              <?php }
                           } else { ?>
                              <tr>
                                 <td colspan="6" class="text-center text-muted">Tidak ada data Stock Take yang tersedia.</td>
                              </tr>
                           <?php }
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

<!-- Modal Konfirmasi Stock In -->
<div class="modal fade" id="confirmStockModal" tabindex="-1" aria-labelledby="modalTitle" aria-hidden="true">
   <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Konfirmasi Stock In</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <span aria-hidden="true">&times;</span>
            </button>
         </div>
         <div class="modal-body">
            <p id="modalText"></p>
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="lihatStockBtn">Lihat</button>
            <button type="button" class="btn btn-primary" id="lanjutStockBtn">Tetap Lanjutkan</button>
         </div>
      </div>
   </div>
</div>

<script>
   function confirmStockIn(idst) {
      // Periksa jumlah barang yang belum terscan
      fetch("check_stockmiss.php?id=" + idst)
         .then(response => response.json())
         .then(data => {
            if (data.total_missing > 0) {
               // Tampilkan modal jika masih ada barang yang belum terscan
               document.getElementById("modalText").innerHTML =
                  `Masih ada <b>${data.total_missing}</b> barang yang belum terscan. Apakah Anda ingin melihat laporan missing stock terlebih dahulu?`;

               document.getElementById("lihatStockBtn").onclick = function() {
                  window.location.href = "waiting.php?id=" + idst;
               };

               document.getElementById("lanjutStockBtn").onclick = function() {
                  window.location.href = "stockin.php?id=" + idst;
               };

               $('#confirmStockModal').modal('show');
            } else {
               // Jika semua sudah terscan, lanjut ke stockin.php tanpa modal
               var confirmFinal = confirm("Apakah kamu sudah yakin dengan data stock opname yang ada? (Ingat: Data tidak bisa dikembalikan)");
               if (confirmFinal) {
                  window.location.href = "stockin.php?id=" + idst;
               }
            }
         })
         .catch(error => {
            alert("Terjadi kesalahan saat memeriksa missing stock.");
            console.error(error);
         });
   }

   document.title = "STOCK TAKE";
</script>

<?php include "../footer.php"; ?>