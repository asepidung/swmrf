<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

$idlabelboning = isset($_GET['id']) ? intval($_GET['id']) : 0;
$idboning = isset($_GET['idboning']) ? intval($_GET['idboning']) : 0;

// Periksa apakah parameter valid
if ($idlabelboning <= 0 || $idboning <= 0) {
   die("Parameter tidak valid.");
}

// Ambil data labelboning berdasarkan id
$query = "SELECT * FROM labelboning WHERE idlabelboning = $idlabelboning";
$result = mysqli_query($conn, $query);
if (!$result || mysqli_num_rows($result) === 0) {
   die("Data tidak ditemukan.");
}
$data = mysqli_fetch_assoc($result);

// Ambil daftar barang
$queryBarang = "SELECT * FROM barang ORDER BY nmbarang ASC";
$resultBarang = mysqli_query($conn, $queryBarang);

// Ambil daftar grade
$queryGrade = "SELECT * FROM grade ORDER BY nmgrade ASC";
$resultGrade = mysqli_query($conn, $queryGrade);
?>
<div class="content-wrapper">
   <!-- Content Header -->
   <section class="content-header">
      <div class="container-fluid">
         <div class="row mb-2">
            <div class="col-sm-6">
               <h1>Edit Label</h1>
            </div>
         </div>
      </div><!-- /.container-fluid -->
   </section>
   <!-- Main Content -->
   <div class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-lg-4">
               <!-- Form Card -->
               <div class="card">
                  <div class="card-body">
                     <form method="POST" action="updatelabelboning.php" onsubmit="submitForm(event)">
                        <!-- Dropdown Barang -->
                        <div class="form-group">
                           <div class="input-group">
                              <select class="form-control" name="idbarang" id="idbarang" required>
                                 <option value="" disabled>--Pilih Item--</option>
                                 <?php while ($row = mysqli_fetch_assoc($resultBarang)) : ?>
                                    <option value="<?= $row['idbarang']; ?>" <?= $row['idbarang'] == $data['idbarang'] ? 'selected' : ''; ?>>
                                       <?= $row['nmbarang']; ?>
                                    </option>
                                 <?php endwhile; ?>
                              </select>
                              <div class="input-group-append">
                                 <a href="../barang/newbarang.php" class="btn btn-primary"><i class="fas fa-plus"></i></a>
                              </div>
                           </div>
                        </div>

                        <!-- Dropdown Grade -->
                        <div class="form-group">
                           <div class="input-group">
                              <select class="form-control" name="idgrade" id="idgrade" required>
                                 <option value="" disabled>--Pilih Grade--</option>
                                 <?php while ($row = mysqli_fetch_assoc($resultGrade)) : ?>
                                    <option value="<?= $row['idgrade']; ?>" <?= $row['idgrade'] == $data['idgrade'] ? 'selected' : ''; ?>>
                                       <?= $row['nmgrade']; ?>
                                    </option>
                                 <?php endwhile; ?>
                              </select>
                           </div>
                        </div>

                        <!-- Packed Date -->
                        <div class="form-group">
                           <div class="input-group">
                              <input type="date" class="form-control" name="packdate" id="packdate" required value="<?= $data['packdate']; ?>">
                           </div>
                        </div>

                        <!-- Expired Date -->
                        <div class="form-group">
                           <div class="input-group">
                              <input type="date" readonly class="form-control" name="exp" id="exp" value="<?= $data['exp'] ?? ''; ?>">
                           </div>
                        </div>
                        <!-- Hidden Inputs -->
                        <input type="hidden" name="idusers" id="idusers" value="<?= $idusers ?>">
                        <input type="hidden" name="idboningWithPrefix" id="idboningWithPrefix" value="<?= $idboningWithPrefix; ?>">
                        <input type="hidden" name="idboning" id="idboning" value="<?= $idboning; ?>">
                        <input type="hidden" name="idlabelboning" id="idlabelboning" value="<?= $idlabelboning; ?>">


                        <!-- Qty Input -->
                        <div class="row">
                           <div class="col-8">
                              <div class="form-group">
                                 <input type="text" class="form-control" name="qty" id="qty" placeholder="Weight & Pcs" required value="<?= $data['qty'] . ($data['pcs'] ? '/' . $data['pcs'] : ''); ?>">
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <a href="detailpcs.php?id=<?= $idboning; ?>" class="btn btn-warning btn-block">PcsLabel</a>
                              </div>
                           </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn bg-gradient-primary btn-block" name="submit">Print</button>
                     </form>

                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>
<script>
   document.title = "Boning <?= "BN" . $idboningWithPrefix ?>";
</script>
</div>
<?php
require "../footer.php";
?>