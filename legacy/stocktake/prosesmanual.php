<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
   http_response_code(405);
   exit("Method Not Allowed");
}

// Helper normalisasi tanggal ke YYYY-MM-DD
function normalize_date_or_fail(string $raw): string
{
   $raw = trim($raw);
   if ($raw === '') exit("Tanggal POD tidak boleh kosong.");
   if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
   $formats = ['m/d/Y', 'd/m/Y', 'd-m-Y', 'm-d-Y', 'Y/m/d'];
   foreach ($formats as $fmt) {
      $dt = DateTime::createFromFormat('!' . $fmt, $raw);
      if ($dt && $dt->format($fmt) === $raw) return $dt->format('Y-m-d');
   }
   exit("Tanggal POD tidak valid. Gunakan format YYYY-MM-DD.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
   // Ambil & validasi input
   $idst      = isset($_POST['idst']) ? (int)$_POST['idst'] : 0;
   $kdbarcode = trim($_POST['kdbarcode'] ?? '');
   $idbarang  = isset($_POST['idbarang'][0]) ? (int)$_POST['idbarang'][0] : 0;
   $idgrade   = isset($_POST['idgrade'][0])  ? (int)$_POST['idgrade'][0]  : 0;
   $qtyRaw    = trim($_POST['qty'] ?? '');   // "12.34" atau "12.34/5"
   $podRaw    = trim($_POST['pod'] ?? '');
   $origin    = isset($_POST['origin']) ? (int)$_POST['origin'] : 0;
   $ph_input  = trim($_POST['ph'] ?? '');    // opsional

   if ($idst <= 0 || $kdbarcode === '' || $idbarang <= 0 || $idgrade <= 0 || $qtyRaw === '' || $podRaw === '' || $origin === 0) {
      exit("Parameter tidak lengkap.");
   }

   // Normalisasi tanggal
   $pod = normalize_date_or_fail($podRaw);

   // Parse qty/pcs gabungan
   $qty = null;
   $pcs = null;
   if (preg_match('~^\s*([0-9.,]+)\s*(?:/\s*(\d+))?\s*$~', $qtyRaw, $m)) {
      $qtyNorm = str_replace(',', '.', $m[1]);
      $qty = (float)$qtyNorm;
      $pcs = isset($m[2]) ? (int)$m[2] : null;
   } else {
      exit('Format Qty tidak valid. Contoh: 12.34 atau 12.34/5');
   }
   if ($qty <= 0) exit('Quantity harus > 0');
   $qty = (float)number_format($qty, 2, '.', ''); // fix 2 desimal

   // Normalisasi & validasi pH (opsional)
   $phFloat = null;
   if ($ph_input !== '') {
      $rawPh = str_replace(',', '.', $ph_input);
      $phVal = filter_var($rawPh, FILTER_VALIDATE_FLOAT);
      if ($phVal === false) exit('Nilai pH tidak valid.');
      if ($phVal < 5.4 || $phVal > 5.7) exit('Nilai pH harus antara 5.4 dan 5.7.');
      $phFloat = floor($phVal * 10) / 10; // truncate 1 desimal
   }

   // Cek duplikasi kdbarcode di stocktakedetail
   $checkStmt = $conn->prepare("SELECT COUNT(*) FROM stocktakedetail WHERE kdbarcode = ?");
   $checkStmt->bind_param("s", $kdbarcode);
   $checkStmt->execute();
   $checkStmt->bind_result($count);
   $checkStmt->fetch();
   $checkStmt->close();
   if ((int)$count > 0) {
      header("Location: starttaking.php?id=" . (int)$idst . "&stat=duplicate");
      exit;
   }

   // Transaksi
   $conn->begin_transaction();

   // Nilai yang mungkin NULL
   $pcsParam = is_null($pcs) ? null : (int)$pcs;
   $phParam  = is_null($phFloat) ? null : (float)number_format($phFloat, 1, '.', '');

   // Insert ke stocktakedetail (type string HARUS 9 huruf untuk 9 variabel)
   $stmt1 = $conn->prepare(
      "INSERT INTO stocktakedetail (idst, kdbarcode, idbarang, idgrade, qty, pcs, pod, origin, ph)
     VALUES (?,?,?,?,?,?,?,?,?)"
   );
   // i s i i d i s i d  -> "isiidisid"
   $stmt1->bind_param(
      "isiidisid",
      $idst,        // i
      $kdbarcode,   // s
      $idbarang,    // i
      $idgrade,     // i
      $qty,         // d
      $pcsParam,    // i (nullable)
      $pod,         // s
      $origin,      // i
      $phParam      // d (nullable)
   );
   $stmt1->execute();
   $stmt1->close();

   // Insert ke manualstock
   $stmt2 = $conn->prepare(
      "INSERT INTO manualstock (idst, kdbarcode, idbarang, idgrade, qty, pcs, pod, origin, ph)
     VALUES (?,?,?,?,?,?,?,?,?)"
   );
   // sama: "isiidisid"
   $stmt2->bind_param(
      "isiidisid",
      $idst,
      $kdbarcode,
      $idbarang,
      $idgrade,
      $qty,
      $pcsParam,
      $pod,
      $origin,
      $phParam
   );
   $stmt2->execute();
   $stmt2->close();

   // Commit
   $conn->commit();

   header("Location: starttaking.php?id=" . (int)$idst . "&stat=success");
   exit;
} catch (Throwable $e) {
   if ($conn) {
      $conn->rollback();
   }
   exit("Terjadi kesalahan: " . $e->getMessage());
}
