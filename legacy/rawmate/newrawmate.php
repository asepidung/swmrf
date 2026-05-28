<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "kdrawmateunik.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
   <section class="content">
      <div class="container-fluid">
         <div class="row justify-content-center">
            <!-- Lebar adaptif: HP full, desktop setengah -->
            <div class="col-12 col-lg-7">

               <div class="card card-dark mt-3">
                  <div class="card-header">
                     <h3 class="card-title">Input Item Baru</h3>
                  </div>

                  <form method="POST" action="prosesnewrawmate.php">
                     <div class="card-body">

                        <div class="row">
                           <!-- KODE -->
                           <div class="col-12 col-md-6">
                              <div class="form-group">
                                 <label for="kdrawmate">Kode</label>
                                 <input type="text" class="form-control" name="kdrawmate" id="kdrawmate"
                                    value="<?= htmlspecialchars($kodeauto); ?>" readonly>
                              </div>
                           </div>

                           <!-- UNIT -->
                           <div class="col-12 col-md-6">
                              <div class="form-group">
                                 <label for="unit">Unit / Satuan <span class="text-danger">*</span></label>
                                 <select class="form-control" name="unit" id="unit" required>
                                    <option value="">-- Pilih Satuan --</option>
                                    <?php
                                    $units = ["Box", "Ikat", "Kg", "Pack", "Pcs", "Set"];
                                    foreach ($units as $u) {
                                       echo "<option value=\"$u\">$u</option>";
                                    }
                                    ?>
                                 </select>
                              </div>
                           </div>
                        </div>

                        <!-- NAMA MATERIAL -->
                        <div class="form-group">
                           <label for="nmrawmate">Nama Material <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" autofocus
                              name="nmrawmate" id="nmrawmate"
                              placeholder="Gunakan Huruf BESAR !!!" required>
                        </div>

                        <!-- CATEGORY -->
                        <div class="form-group">
                           <label for="category">Category <span class="text-danger">*</span></label>
                           <div class="input-group">
                              <select name="idrawcategory" id="category" class="form-control" required>
                                 <option value="">-- Select Category --</option>
                                 <?php
                                 $query  = "SELECT idrawcategory, nmcategory FROM rawcategory ORDER BY nmcategory ASC";
                                 $result = mysqli_query($conn, $query);
                                 if ($result) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                       echo "<option value=\"" . htmlspecialchars($row['idrawcategory']) . "\">"
                                          . htmlspecialchars($row['nmcategory']) . "</option>";
                                    }
                                 }
                                 ?>
                              </select>
                              <div class="input-group-append">
                                 <a href="../rawcategory/newrawcategory.php" class="btn btn-dark" title="Tambah Category">
                                    <i class="fas fa-plus"></i>
                                 </a>
                              </div>
                           </div>
                        </div>

                        <div class="row">
                           <!-- BARMIN (OPSIONAL) -->
                           <div class="col-12 col-md-6">
                              <div class="form-group">
                                 <label for="barmin">Batas Minimal Stock</label>
                                 <input type="number" class="form-control"
                                    name="barmin" id="barmin"
                                    placeholder="Opsional, contoh: 10" min="0">
                                 <small class="text-muted">Kosongkan jika tidak ada batas minimal</small>
                              </div>
                           </div>

                           <!-- TAMPILKAN STOCK -->
                           <div class="col-12 col-md-6">
                              <div class="form-group">
                                 <label>Tampilkan di Stock</label>
                                 <div class="d-flex flex-wrap">
                                    <div class="form-check mr-4">
                                       <input class="form-check-input" type="radio"
                                          name="tampilkan_stock" id="stock_yes" value="1" checked>
                                       <label class="form-check-label" for="stock_yes">Ya</label>
                                    </div>
                                    <div class="form-check">
                                       <input class="form-check-input" type="radio"
                                          name="tampilkan_stock" id="stock_no" value="0">
                                       <label class="form-check-label" for="stock_no">Tidak</label>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>

                     </div>

                     <div class="card-footer text-right">
                        <button type="submit" class="btn bg-gradient-primary px-4">
                           <i class="fas fa-save mr-1"></i> Submit
                        </button>
                     </div>

                  </form>
               </div>

            </div>
         </div>
      </div>
   </section>
</div>

<?php
include "../footer.php";
include "../footnote.php";
?>