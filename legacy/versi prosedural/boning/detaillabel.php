<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Ambil ID label boning dari GET
if (!isset($_GET['idlabelboning']) || !is_numeric($_GET['idlabelboning'])) {
   die("ID Label Boning tidak valid.");
}
$idlabelboning = intval($_GET['idlabelboning']);

// Ambil data label boning untuk memastikan ID valid
$query = "SELECT * FROM labelboning WHERE idlabelboning = $idlabelboning";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) === 0) {
   die("Data Label Boning tidak ditemukan.");
}
$labelData = mysqli_fetch_assoc($result);
?>


<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Detail Label</title>
   <link rel="stylesheet" href="../dist/css/adminlte.min.css">
</head>

<body>
   <div class="container mt-5">
      <h3>Pengisian Detail Label</h3>
      <p>ID Boning: <strong><?= $idboning; ?></strong></p>

      <!-- Form Input Detail Label -->
      <form method="POST" action="proses_detaillabel.php">
         <input type="hidden" name="idboning" value="<?= $idboning; ?>">

         <div id="pcs-container">
            <!-- Input Pertama -->
            <div class="form-group row">
               <label class="col-sm-2 col-form-label" for="pcs_weight_1">Berat PCS 1 (Kg):</label>
               <div class="col-sm-6">
                  <input type="number" step="0.01" class="form-control" name="pcs_weight[]" id="pcs_weight_1" placeholder="Masukkan Berat" required>
               </div>
            </div>
         </div>

         <!-- Tombol Tambah PCS -->
         <button type="button" class="btn btn-primary mb-3" onclick="addPcs()">Tambah PCS</button>

         <!-- Submit Form -->
         <button type="submit" class="btn btn-success">Simpan</button>
      </form>
   </div>

   <script>
      let pcsCounter = 1;

      // Fungsi untuk menambahkan input baru
      function addPcs() {
         pcsCounter++;
         const container = document.getElementById('pcs-container');
         const newInput = `
                <div class="form-group row">
                    <label class="col-sm-2 col-form-label" for="pcs_weight_${pcsCounter}">Berat PCS ${pcsCounter} (Kg):</label>
                    <div class="col-sm-6">
                        <input type="number" step="0.01" class="form-control" name="pcs_weight[]" id="pcs_weight_${pcsCounter}" placeholder="Masukkan Berat" required>
                    </div>
                </div>`;
         container.insertAdjacentHTML('beforeend', newInput);
      }
   </script>
</body>

</html>