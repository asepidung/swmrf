<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";


$idrepack = $_GET['id'];

// Query untuk mengambil data repack berdasarkan idrepack
$queryRepack = "SELECT * FROM repack WHERE idrepack = $idrepack";
$resultRepack = mysqli_query($conn, $queryRepack);

// Periksa jika query berhasil dijalankan
if ($resultRepack) {
   $repackData = mysqli_fetch_assoc($resultRepack);
} else {
   // Handle kesalahan query
   die("Query Error: " . mysqli_error($conn));
}
?>

<div class="content-wrapper">
   <section class="content">
      <div class="container">
         <div class="row">
            <div class="col-6 mt-3">
               <form method="POST" action="updaterepack.php">
                  <input type="hidden" name="idrepack" value="<?= $repackData['idrepack']; ?>">
                  <div class="card">
                     <div class="card-body">
                        <div class="col">
                           <div class="form-group">
                              <label for="tglrepack">Tanggal Repack</label>
                              <!-- Isi nilai input dengan data dari tabel repack -->
                              <input type="date" class="form-control" name="tglrepack" value="<?= $repackData['tglrepack']; ?>" required>
                              <input type="hidden" name="norepack" value="<?= $repackData['norepack']; ?>">
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="note">Keterangan</label>
                              <!-- Isi nilai input dengan data dari tabel repack -->
                              <input type="text" class="form-control" name="note" value="<?= $repackData['note']; ?>">
                           </div>
                        </div>
                        <div class="col">
                           <button type="submit" name="submit" class="btn bg-gradient-success">Update</button>
                        </div>
                     </div>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </section>
</div>
<script>
   document.title = "Edit Repack";
</script>

<?php
// require "../footnotes.php";
include "../footer.php";
?>