<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
   <section class="content">
      <div class="container-fluid">
         <div class="row justify-content-center">
            <div class="col-md-8">
               <div class="card card-dark mt-3">
                  <div class="card-header">
                     <h3 class="card-title">Input Item Baru</h3>
                  </div>

                  <form method="POST" action="prosesnewbarang.php" id="formNewBarang">
                     <div class="card-body">

                        <!-- TIPE BARANG -->
                        <div class="form-group">
                           <label for="tipebarang">Tipe Barang <span class="text-danger">*</span></label>
                           <select class="form-control" name="tipebarang" id="tipebarang" required>
                              <option value="" selected disabled>Pilih Tipe Barang</option>
                              <option value="utama">Barang Utama</option>
                              <option value="turunan">Barang Turunan</option>
                           </select>
                        </div>

                        <!-- BARANG INDUK (UNTUK TURUNAN) -->
                        <div class="form-group d-none" id="parentContainer">
                           <label for="kodeinduk">Pilih Barang Induk (Barang Utama) <span class="text-danger">*</span></label>
                           <select class="form-control" name="kodeinduk" id="kodeinduk">
                              <option value="" selected disabled>Pilih Barang Induk</option>
                              <?php
                              $query = mysqli_query($conn, "SELECT kdbarang, nmbarang FROM barang WHERE kodeinduk IS NULL ORDER BY nmbarang ASC");
                              while ($row = mysqli_fetch_assoc($query)) {
                                 echo '<option value="' . htmlspecialchars($row['kdbarang']) . '">' . strtoupper(htmlspecialchars($row['nmbarang'])) . ' - ' . htmlspecialchars($row['kdbarang']) . '</option>';
                              }
                              ?>
                           </select>
                           <small class="form-text text-muted">Pilih barang utama sebagai induk untuk produk turunan.</small>
                        </div>

                        <!-- NAMA PRODUK -->
                        <div class="form-group">
                           <label for="nmbarang">Nama Product <span class="text-danger">*</span></label>
                           <input type="text" class="form-control text-uppercase" name="nmbarang" id="nmbarang" placeholder="Gunakan HURUF BESAR !!!" required>
                        </div>

                        <!-- KATEGORI -->
                        <div class="form-group">
                           <label for="cut">Kategori <span class="text-danger">*</span></label>
                           <select class="form-control" name="cut" id="cut" required>
                              <option value="" disabled selected>Pilih Kategori</option>
                              <?php
                              $query = mysqli_query($conn, "SELECT idcut, nmcut FROM cuts ORDER BY idcut ASC");
                              while ($row = mysqli_fetch_assoc($query)) {
                                 echo '<option value="' . $row['idcut'] . '">' . strtoupper($row['nmcut']) . '</option>';
                              }
                              ?>
                           </select>
                        </div>
                     </div>
                     <div class="card-footer text-right">
                        <button type="submit" class="btn bg-gradient-primary">Submit</button>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   document.addEventListener('DOMContentLoaded', function() {
      const tipeBarang = document.getElementById('tipebarang');
      const parentContainer = document.getElementById('parentContainer');
      const kodeIndukSelect = document.getElementById('kodeinduk');

      tipeBarang.addEventListener('change', function() {
         if (this.value === 'turunan') {
            parentContainer.classList.remove('d-none');
         } else {
            parentContainer.classList.add('d-none');
            kodeIndukSelect.value = '';
         }
      });
   });
</script>

<?php
include "../footer.php";
include "../footnote.php";
?>