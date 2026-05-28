<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

// Ambil user id dari session (diperlukan untuk submit), tapi TIDAK dipakai untuk prefill form
$idusers = $_SESSION['idusers'] ?? null;

function h($v)
{
   return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Tampilkan flash message sederhana via query string ?msg=...
if (isset($_GET['msg'])) {
   echo '<script>window.addEventListener("DOMContentLoaded",()=>{alert(' . json_encode($_GET['msg']) . ');});</script>';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

   // Validasi parameter
   $kdbarcode = isset($_GET['kdbarcode']) ? trim($_GET['kdbarcode']) : '';
   $idtally   = isset($_GET['idtally']) ? (int)$_GET['idtally'] : 0;

   if ($kdbarcode === '' || $idtally <= 0) {
      header("Location: index.php?msg=Parameter%20tidak%20lengkap");
      exit;
   }

   // Ambil data tallydetail + barang + grade sesuai barcode & idtally
   $sql = "
    SELECT 
      td.*, b.nmbarang, g.nmgrade
    FROM tallydetail td
    LEFT JOIN barang b ON td.idbarang = b.idbarang
    LEFT JOIN grade  g ON td.idgrade  = g.idgrade
    WHERE td.barcode = ? AND td.idtally = ?
    LIMIT 1
  ";
   if (!$stmt = $conn->prepare($sql)) {
      die("DB Error (prepare)");
   }
   $stmt->bind_param('si', $kdbarcode, $idtally);
   if (!$stmt->execute()) {
      die("DB Error (execute)");
   }
   $result = $stmt->get_result();

   if ($result->num_rows === 0) {
      header("Location: index.php?msg=Data%20Barang%20Tidak%20Ditemukan");
      exit;
   }

   $row = $result->fetch_assoc();

   // Nilai untuk ditampilkan (semua dari DB, bukan dari session)
   $idtallydetail = (int)$row['idtallydetail'];
   $idbarang      = (int)$row['idbarang'];
   $idgrade       = (int)$row['idgrade'];
   $nmbarang      = $row['nmbarang'] ?? '';
   $nmgrade       = $row['nmgrade'] ?? '';
   $podRaw        = $row['pod'] ?? '';           // pack on date (YYYY-mm-dd di DB)
   $packdateVal   = $podRaw ? date('Y-m-d', strtotime($podRaw)) : '';
   $weight        = isset($row['weight']) ? (float)$row['weight'] : 0.0;
   $pcs           = isset($row['pcs']) ? (int)$row['pcs'] : 0;
   $phTd          = $row['ph'];                  // bisa NULL

   // Prefill gabungan qty: "berat/pcs" atau "berat" jika pcs=0
   $qtyCombined   = number_format($weight, 2, '.', '') . ($pcs > 0 ? '/' . $pcs : '');

   // Prefill pH dari DB: jika NULL/kosong -> tampil kosong
   $phValDisplay  = ($phTd === null || $phTd === '') ? '' : number_format((float)$phTd, 1, '.', '');
?>
   <div class="content-wrapper">
      <div class="content">
         <div class="container-fluid">
            <div class="row">
               <div class="col-lg-4 mt-2">
                  <div class="card">
                     <div class="card-body">
                        <form method="POST" action="cetakrelabel.php" id="formRelabel">
                           <input type="hidden" name="idtally" value="<?= $idtally ?>">
                           <input type="hidden" name="idtallydetail" value="<?= $idtallydetail ?>">

                           <!-- Barang -->
                           <div class="form-group">
                              <div class="input-group">
                                 <input type="hidden" name="idbarang" value="<?= $idbarang ?>">
                                 <input type="text" class="form-control" value="<?= h($nmbarang) ?>" readonly>
                              </div>
                           </div>

                           <!-- Grade -->
                           <div class="form-group">
                              <div class="input-group">
                                 <input type="hidden" name="idgrade" value="<?= $idgrade ?>">
                                 <input type="text" class="form-control" value="<?= h($nmgrade) ?>" readonly>
                              </div>
                           </div>

                           <!-- Packdate & Checkbox Expired -->
                           <!-- Packdate & Checkbox Expired -->
                           <div class="row">
                              <div class="col-8">
                                 <div class="form-group">
                                    <input type="hidden" name="xpackdate" id="xpackdate" value="<?= h($packdateVal) ?>">
                                    <!-- Atribut readonly sudah DIBUANG, sekarang bebas diedit -->
                                    <input type="date" class="form-control" name="packdate" id="packdate" required value="<?= h($packdateVal) ?>">
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-group mt-2">
                                    <div class="custom-control custom-checkbox mb-0">
                                       <?php
                                       // Cek memori session dari labelboning
                                       $is_exp_checked = (isset($_SESSION['print_exp']) && $_SESSION['print_exp'] == 1) ? 'checked' : '';
                                       ?>
                                       <input type="checkbox" class="custom-control-input" name="print_exp" id="print_exp" value="1" <?= $is_exp_checked ?>>
                                       <label class="custom-control-label text-nowrap" for="print_exp">Show Expired</label>
                                    </div>
                                 </div>
                              </div>
                           </div>

                           <input type="hidden" name="idusers" id="idusers" value="<?= h($idusers) ?>">
                           <input type="hidden" name="kdbarcode" id="kdbarcode" value="<?= h($kdbarcode) ?>">

                           <!-- Qty gabungan (readonly) + pH (ikut DB) -->
                           <div class="form-group mt-2">
                              <div class="row">
                                 <div class="col-8">
                                    <input type="text"
                                       class="form-control"
                                       readonly
                                       name="qty" id="qty"
                                       placeholder="Weight / Pcs (cth: 12.34/5)"
                                       value="<?= h($qtyCombined) ?>"
                                       required>
                                 </div>
                                 <div class="col">
                                    <!-- pH boleh kosong (akan tersimpan NULL di server kalau tidak diisi) -->
                                    <input type="number"
                                       step="0.1" min="5.4" max="5.7"
                                       class="form-control"
                                       name="ph" id="ph"
                                       placeholder="PH 5.4-5.7"
                                       value="<?= h($phValDisplay) ?>" required>
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

   <script>
      // Tidak ada logika session; nilai di form murni dari database.
      // Optional: jika ingin pastikan format packdate valid yyyy-mm-dd
      (function() {
         const pd = document.getElementById('packdate');
         if (pd && pd.value && !/^\d{4}-\d{2}-\d{2}$/.test(pd.value)) {
            const t = new Date(pd.value);
            if (!isNaN(t)) pd.value = t.toISOString().slice(0, 10);
         }
      })();
   </script>

<?php
} else {
   echo "Form tidak dikirimkan.";
}

require "../footer.php";
