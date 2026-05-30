<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

$idst = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil barcode dari session bila ada (seperti sebelumnya)
$barcodeValue = $_SESSION['barcode'] ?? '';
$origin = ($barcodeValue !== '') ? substr($barcodeValue, 0, 1) : '';
?>
<!-- Main content -->
<section class="content">
   <div class="container-fluid">
      <div class="row mt-3">
         <div class="col-xs">
            <form method="POST" action="prosesmanual.php">
               <div class="card">
                  <div class="card-body">

                     <!-- BARCODE (readonly) -->
                     <div class="form-group">
                        <input
                           type="text"
                           class="form-control text-center"
                           name="kdbarcode"
                           id="kdbarcode"
                           value="<?= htmlspecialchars($barcodeValue, ENT_QUOTES) ?>"
                           readonly>
                     </div>

                     <!-- ITEM -->
                     <div class="form-group">
                        <div class="input-group">
                           <select class="form-control" name="idbarang[]" id="idbarang" required>
                              <option value="">--Pilih Barang--</option>
                              <?php
                              $qBarang = $conn->query("SELECT idbarang, nmbarang FROM barang ORDER BY nmbarang ASC");
                              while ($r = $qBarang->fetch_assoc()) {
                                 $idb = (int)$r['idbarang'];
                                 $nm  = htmlspecialchars($r['nmbarang'], ENT_QUOTES);
                                 echo "<option value=\"$idb\">$nm</option>";
                              }
                              ?>
                           </select>
                        </div>
                     </div>

                     <!-- GRADE -->
                     <div class="form-group">
                        <div class="input-group">
                           <select class="form-control" name="idgrade[]" id="idgrade" required>
                              <option value="">--Pilih Grade--</option>
                              <?php
                              $qGrade = $conn->query("SELECT idgrade, nmgrade FROM grade ORDER BY nmgrade ASC");
                              while ($r = $qGrade->fetch_assoc()) {
                                 $idg = (int)$r['idgrade'];
                                 $nm  = htmlspecialchars($r['nmgrade'], ENT_QUOTES);
                                 echo "<option value=\"$idg\">$nm</option>";
                              }
                              ?>
                           </select>
                        </div>
                     </div>

                     <!-- TANGGAL POD -->
                     <div class="form-group">
                        <input
                           type="date"
                           class="form-control text-center"
                           name="pod"
                           id="pod"
                           required>
                     </div>

                     <!-- QTY (Weight/Pcs) + pH -->
                     <div class="form-group mt-1">
                        <div class="row">
                           <div class="col-8">
                              <input
                                 type="text"
                                 class="form-control"
                                 name="qty"
                                 id="qty"
                                 placeholder="Weight / Pcs (cth: 12.34/5)"
                                 required>
                           </div>
                           <div class="col">
                              <input
                                 type="number"
                                 step="0.1"
                                 min="5.4"
                                 max="5.7"
                                 class="form-control"
                                 name="ph"
                                 id="ph"
                                 placeholder="pH 5.4–5.7">
                           </div>
                        </div>
                     </div>

                     <!-- Hidden -->
                     <input type="hidden" name="origin" value="<?= htmlspecialchars($origin, ENT_QUOTES) ?>">
                     <input type="hidden" name="idst" value="<?= (int)$idst ?>">

                     <!-- Submit -->
                     <div class="form-group">
                        <button type="submit" class="btn btn-block btn-primary">Submit</button>
                     </div>

                  </div>
               </div>
            </form>
         </div>
      </div>
   </div>
</section>

<script>
   document.title = "Manual Tally";
   // Optional: fokus cepat ke qty setelah memilih item (TAB di select)
   document.addEventListener('DOMContentLoaded', function() {
      const sel = document.getElementById('idbarang');
      if (sel) {
         sel.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
               e.preventDefault();
               const q = document.getElementById('qty');
               if (q) q.focus();
            }
         });
      }
   });
</script>

<?php include "../footer.php"; ?>