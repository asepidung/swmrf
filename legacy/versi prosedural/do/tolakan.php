<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

/* =========================
   AMBIL PARAMETER
========================= */
$iddo = isset($_GET['iddo']) ? (int)$_GET['iddo'] : 0;
$idso = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($iddo <= 0) {
   echo "Invalid DO ID";
   exit();
}

/* =========================
   AMBIL DO + IDTALLY
========================= */
$qdo = mysqli_prepare($conn, "
    SELECT d.idtally, d.idso, c.nama_customer
    FROM do d
    LEFT JOIN customers c ON d.idcustomer = c.idcustomer
    WHERE d.iddo = ?
");
mysqli_stmt_bind_param($qdo, "i", $iddo);
mysqli_stmt_execute($qdo);
$resdo = mysqli_stmt_get_result($qdo);
$do = mysqli_fetch_assoc($resdo);

if (!$do || empty($do['idtally'])) {
   echo "DO tidak memiliki tally";
   exit();
}

$idtally = (int)$do['idtally'];
$nama_customer = $do['nama_customer'] ?? '-';

/* =========================
   AMBIL DATA TALLY DETAIL
========================= */
$query = "
SELECT 
    td.*,
    b.nmbarang,
    g.nmgrade
FROM tallydetail td
INNER JOIN barang b ON td.idbarang = b.idbarang
INNER JOIN grade g ON td.idgrade = g.idgrade
WHERE td.idtally = ?
ORDER BY b.nmbarang
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $idtally);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<div class="content-header">
   <div class="container-fluid">
      <h4 class="text-info">
         Pilih Item Yang Akan Dikembalikan Ke Stock<br>
         <small><?= htmlspecialchars($nama_customer); ?></small>
      </h4>
   </div>
</div>

<section class="content">
   <div class="container-fluid">

      <!-- INPUT SCAN -->
      <div class="card mb-2">
         <div class="card-body p-2">
            <div class="row align-items-center">
               <div class="col-md-6">
                  <input type="text" id="barcode_scan"
                     class="form-control form-control-sm"
                     placeholder="SCAN BARCODE DI SINI (auto enter)" autofocus>
               </div>
               <div class="col-md-6 text-right small text-info">
                  Total dipilih: <b><span id="total-selected">0</span></b> item
               </div>
            </div>
         </div>
      </div>

      <form method="POST" action="pengembalianproduct.php">
         <input type="hidden" name="iddo" value="<?= $iddo; ?>">
         <input type="hidden" name="idso" value="<?= $idso; ?>">
         <input type="hidden" name="idtally" value="<?= $idtally; ?>">

         <div class="card">
            <div class="card-body">
               <table class="table table-bordered table-striped table-sm">
                  <thead class="text-center">
                     <tr>
                        <th>#</th>
                        <th>Barcode</th>
                        <th>Item</th>
                        <th>Code</th>
                        <th>Weight</th>
                        <th>Pcs</th>
                        <th>POD</th>
                        <th>Origin</th>
                        <th>Pilih</th>
                     </tr>
                  </thead>
                  <tbody>
                     <?php
                     $no = 1;
                     while ($row = mysqli_fetch_assoc($result)) {

                        switch ($row['origin']) {
                           case 1:
                              $origin = 'BONING';
                              break;
                           case 2:
                              $origin = 'TRADING';
                              break;
                           case 3:
                              $origin = 'REPACK';
                              break;
                           case 4:
                              $origin = 'RELABEL';
                              break;
                           case 5:
                              $origin = 'IMPORT';
                              break;
                           case 6:
                              $origin = 'RTN';
                              break;
                           default:
                              $origin = 'UNKNOWN';
                        }
                     ?>
                        <tr data-barcode="<?= htmlspecialchars($row['barcode']); ?>" class="text-center">
                           <td><?= $no++; ?></td>
                           <td><?= htmlspecialchars($row['barcode']); ?></td>
                           <td class="text-left"><?= htmlspecialchars($row['nmbarang']); ?></td>
                           <td><?= htmlspecialchars($row['nmgrade']); ?></td>
                           <td><?= number_format((float)$row['weight'], 2); ?></td>
                           <td><?= $row['pcs'] ?: ''; ?></td>
                           <td><?= $row['pod'] ? date('d-M-Y', strtotime($row['pod'])) : ''; ?></td>
                           <td><?= $origin; ?></td>
                           <td>
                              <input type="checkbox"
                                 class="chk-item"
                                 name="items[]"
                                 value="<?= $row['idtallydetail']; ?>">
                           </td>
                        </tr>
                     <?php } ?>
                  </tbody>
               </table>

               <button type="button" id="check-all" class="btn btn-success btn-sm">
                  <i class="fas fa-check-double"></i> Check All
               </button>
            </div>
         </div>

         <a href="approvedo.php?iddo=<?= $iddo; ?>" class="btn btn-warning">Cancel</a>
         <button type="submit" class="btn btn-primary">
            Proses Pengembalian
         </button>
      </form>

   </div>
</section>

<script>
   document.title = "Tally <?= addslashes($nama_customer); ?>";

   // ================= COUNTER =================
   function updateCounter() {
      const total = document.querySelectorAll('.chk-item:checked').length;
      document.getElementById('total-selected').innerText = total;
   }

   document.querySelectorAll('.chk-item').forEach(chk => {
      chk.addEventListener('change', updateCounter);
   });

   // ================= CHECK ALL =================
   document.getElementById('check-all').addEventListener('click', function() {
      const boxes = document.querySelectorAll('.chk-item');
      const allChecked = [...boxes].every(b => b.checked);
      boxes.forEach(b => b.checked = !allChecked);
      updateCounter();
   });

   // ================= SCAN BARCODE =================
   document.getElementById('barcode_scan').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
         e.preventDefault();
         const code = this.value.trim();
         if (!code) return;

         const row = document.querySelector('tr[data-barcode="' + code + '"]');

         if (!row) {
            alert('Barcode tidak ditemukan: ' + code);
            this.value = '';
            return;
         }

         const chk = row.querySelector('.chk-item');

         // 🔒 ANTI DOUBLE SCAN
         if (chk.checked) {
            row.classList.remove('table-success');
            row.classList.add('table-warning');
            setTimeout(() => row.classList.remove('table-warning'), 600);
         } else {
            chk.checked = true;
            row.classList.add('table-success');
            row.scrollIntoView({
               behavior: 'smooth',
               block: 'center'
            });
            updateCounter();
         }

         this.value = '';
      }
   });
</script>

<?php include "../footer.php"; ?>