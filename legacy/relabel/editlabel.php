<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

// Cek apakah form sudah disubmit
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
   // Periksa apakah parameter kdbarcode telah diterima
   if (isset($_GET['kdbarcode'])) {
      $kdbarcode = $_GET['kdbarcode'];

      $checkStockQuery = "SELECT stock.*, barang.nmbarang, grade.nmgrade
      FROM stock 
      INNER JOIN barang ON stock.idbarang = barang.idbarang
      INNER JOIN grade ON stock.idgrade = grade.idgrade
      WHERE stock.kdbarcode = '$kdbarcode'";

      $checkStockResult = mysqli_query($conn, $checkStockQuery);

      if (!$checkStockResult) {
         die("Query Error: " . mysqli_error($conn));
      }

      // Periksa jumlah baris hasil query
      $rowCount = mysqli_num_rows($checkStockResult);

      if ($rowCount == 0) {
         // Data barang tidak ditemukan
         echo '<script>alert("Data Barang Tidak Ditemukan");</script>';
         echo '<script>window.location.href = "index.php";</script>'; // Redirect ke halaman index.php menggunakan JavaScript
         exit(); // Hentikan eksekusi script setelah redirect
      } else {
         // Data barang ditemukan, lanjutkan dengan menampilkan data dari tabel stock
         $row = mysqli_fetch_assoc($checkStockResult);
?>
         <div class="content-wrapper">
            <div class="content">
               <div class="container-fluid">
                  <div class="row">
                     <div class="col-lg-4 mt-2">
                        <div class="card">
                           <div class="card-body">
                              <form method="POST" action="cetakrelabel.php" onsubmit="submitForm(event)">
                                 <!-- ... -->
                                 <div class="form-group">
                                    <!-- <label>Product <span class="text-danger">*</span></label> -->
                                    <div class="input-group">
                                       <input type="hidden" name="idbarang" value="<?= $row['idbarang']; ?>">
                                       <input type=" text" class="form-control" readonly value="<?= $row['nmbarang']; ?>">
                                    </div>
                                 </div>
                                 <div class="form-group">
                                    <!-- <label>Grade <span class="text-danger">*</span></label> -->
                                    <div class="input-group">
                                       <input type="hidden" name="idgrade" value="<?= $row['idgrade']; ?>">
                                       <input type="text" class="form-control" readonly value="<?= $row['nmgrade']; ?>">
                                    </div>
                                 </div>
                                 <div class="form-group">
                                    <!-- <label>Packed Date<span class="text-danger">*</span></label> -->
                                    <div class="input-group">
                                       <input type="hidden" name="xpackdate" id="xpackdate" value="<?= $row['pod']; ?>">
                                       <input type="date" class="form-control" name="packdate" id="packdate" required value="<?= $row['pod']; ?>">
                                    </div>
                                 </div>
                                 <div class="form-group">
                                    <!-- <label>Expired Date</label> -->
                                    <div class="input-group">
                                       <input type="date" class="form-control" name="exp" id="exp" readonly>
                                    </div>
                                 </div>
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tenderstreach" id="tenderstreach">
                                    <label class="form-check-label">Aktifkan Tenderstreatch</label>
                                 </div>
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="pembulatan" id="pembulatan">
                                    <label class="form-check-label">1 Digit Koma</label>
                                 </div>
                                 <!-- ... -->
                                 <input type="hidden" name="idusers" id="idusers" value="<?= $idusers ?>">
                                 <!-- <input type="hidden" name="product" id="product"> -->
                                 <input type="hidden" name="kdbarcode" id="kdbarcode" value="<?= $kdbarcode ?>">
                                 <div class="row">
                                    <div class="col-8">
                                       <div class="form-group mt-1">
                                          <input type="text" class="form-control" name="qty" id="qty" readonly value="<?= $row['qty'] ?>">
                                       </div>
                                    </div>
                                    <div class="col">
                                       <div class="form-group mt-1">
                                          <input type="hidden" name="xpcs" id="xpcs" value="<?= $row['pcs'] ?>">
                                          <input type="number" class="form-control" name="pcs" id="pcs" value="<?= $row['pcs'] ?>">
                                       </div>
                                    </div>
                                 </div>
                                 <button type="submit" class="btn btn-block bg-gradient-primary" name="submit">Print</button>
                              </form>
                           </div>
                        </div>
                        <!-- /.card -->
                     </div>
                  </div>
               </div>
            </div>
         </div>
<?php
      }
   } else {
      echo "Parameter kdbarcode tidak ditemukan.";
   }
} else {
   echo "Form tidak dikirimkan.";
}

require "../footer.php";
?>