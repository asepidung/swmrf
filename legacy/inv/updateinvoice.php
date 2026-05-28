<?php
require "../verifications/auth.php";

// Aktifkan laporan error untuk debugging (bisa dimatikan saat production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   require "../konak/conn.php";
   $conn->set_charset('utf8mb4');

   // ---- 1. FUNGSI NORMALISASI ANGKA (Aman untuk format 1.000,00 atau 1,000.00) ----
   function normalizeNumber($number)
   {
      if ($number === null) return 0.0;
      $number = trim((string)$number);
      // Hapus spasi dan hidden character
      $number = str_replace(["\xc2\xa0", ' ', "'"], '', $number);

      $lastCommaPos = strrpos($number, ',');
      $lastDotPos   = strrpos($number, '.');

      if ($lastCommaPos !== false && $lastDotPos !== false) {
         if ($lastCommaPos > $lastDotPos) { // Format Luar (1.000,00)
            $number = str_replace('.', '', $number);
            $number = str_replace(',', '.', $number);
         } else { // Format Indo/Standar (1,000.00)
            $number = str_replace(',', '', $number);
         }
      } else {
         if ($lastCommaPos !== false) {
            // Asumsi jika hanya ada koma, itu desimal (Indo)
            $number = str_replace('.', '', $number);
            $number = str_replace(',', '.', $number);
         }
         // Jika hanya titik, dianggap desimal standar
      }

      // Bersihkan karakter selain angka, titik, dan minus
      $number = preg_replace('/[^0-9.\-]/', '', $number);

      if ($number === '' || $number === '-' || $number === '.') return 0.0;

      return (float)$number;
   }

   function normalizeArray($arr)
   {
      $out = [];
      foreach ((array)$arr as $v) $out[] = normalizeNumber($v);
      return $out;
   }

   // ---- 2. AMBIL DATA DARI FORM ----

   // Header non-angka
   $idinvoice    = isset($_POST['idinvoice']) ? (int) $_POST['idinvoice'] : 0;
   $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
   $noinvoice    = $_POST['noinvoice'] ?? '';
   $note         = $_POST['note'] ?? '';

   // Validasi ID Invoice
   if ($idinvoice <= 0) {
      http_response_code(400);
      exit('ID invoice tidak valid.');
   }

   // Angka Header
   $charge      = normalizeNumber($_POST['charge']      ?? 0);
   $downpayment = normalizeNumber($_POST['downpayment'] ?? 0);
   $tax         = normalizeNumber($_POST['tax']         ?? 0);

   // Array Detail Barang
   $idbarang   = $_POST['idbarang']   ?? [];
   $weight     = normalizeArray($_POST['weight']     ?? []);
   $price      = normalizeArray($_POST['price']      ?? []);
   $discount   = normalizeArray($_POST['discount']   ?? []);   // Persen
   $discountrp = normalizeArray($_POST['discountrp'] ?? []);   // Rupiah

   // Hitung jumlah baris data
   $n = min(count($idbarang), count($weight), count($price), count($discount), count($discountrp));

   // ---- 3. MULAI PROSES DATABASE ----
   try {
      $conn->begin_transaction();

      // A) Hapus detail lama (Reset isi invoice)
      $stmtDel = $conn->prepare("DELETE FROM invoicedetail WHERE idinvoice = ?");
      $stmtDel->bind_param("i", $idinvoice);
      $stmtDel->execute();
      $stmtDel->close();

      // B) Insert detail baru + Hitung Subtotal & Total Weight
      // Menggunakan Prepared Statement untuk Insert
      $stmtIns = $conn->prepare("
            INSERT INTO invoicedetail
                (idinvoice, idbarang, weight, price, discount, discountrp, amount)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

      $subtotal = 0.0;
      $totalWeight = 0.0;

      for ($i = 0; $i < $n; $i++) {
         $idbarangVal   = (int) $idbarang[$i];
         $weightVal     = (float) $weight[$i];
         $priceVal      = (float) $price[$i];
         $discountPct   = (float) $discount[$i];
         $discountrpVal = (float) $discountrp[$i];

         // Hitung Amount per baris
         if ($weightVal <= 0) {
            $amount = 0.0;
         } else {
            $discPerUnit = $discountrpVal / $weightVal; // Diskon per kg/unit
            $amount = ($priceVal - $discPerUnit) * $weightVal;
         }

         // Akumulasi ke header
         $subtotal += $amount;
         $totalWeight += max(0.0, $weightVal);

         // --- PERBAIKAN UTAMA DI SINI ---
         // "iiddddd" artinya:
         // i (int)    : idinvoice
         // i (int)    : idbarang
         // d (double) : weight      <-- PENTING: Pakai 'd' agar desimal 7.1 tidak jadi 7
         // d (double) : price
         // d (double) : discount %
         // d (double) : discount Rp
         // d (double) : amount

         $stmtIns->bind_param(
            "iiddddd",
            $idinvoice,
            $idbarangVal,
            $weightVal,
            $priceVal,
            $discountPct,
            $discountrpVal,
            $amount
         );
         $stmtIns->execute();
      }
      $stmtIns->close();

      // C) Hitung Ulang & Update Header Invoice
      $xamount = round($subtotal, 2);
      $xweight = round($totalWeight, 2);

      // Hitung Balance akhir
      $balance = round($xamount + $tax + $charge - $downpayment, 2);

      $stmtUpd = $conn->prepare("
            UPDATE invoice
               SET invoice_date = ?,
                   note         = ?,
                   xweight      = ?,
                   xamount      = ?,
                   tax          = ?,
                   charge       = ?,
                   downpayment  = ?,
                   balance      = ?
             WHERE idinvoice    = ?
        ");

      // s=string, d=double, i=integer
      $stmtUpd->bind_param(
         "ssddddddi",
         $invoice_date,
         $note,
         $xweight,
         $xamount,
         $tax,
         $charge,
         $downpayment,
         $balance,
         $idinvoice
      );
      $stmtUpd->execute();
      $stmtUpd->close();

      // D) Catat Log Aktivitas
      $idusers = isset($_SESSION['idusers']) ? (int) $_SESSION['idusers'] : 0;
      $event   = "Edit Invoice";

      $stmtLog = $conn->prepare("INSERT INTO logactivity (iduser, docnumb, event, waktu) VALUES (?, ?, ?, NOW())");
      $stmtLog->bind_param("iss", $idusers, $noinvoice, $event);
      $stmtLog->execute();
      $stmtLog->close();

      // E) Selesai - Commit Transaksi
      $conn->commit();

      // Redirect kembali ke halaman invoice
      header("Location: invoice.php");
      exit();
   } catch (mysqli_sql_exception $e) {
      // F) Jika Error - Rollback (Batalkan semua perubahan)
      $conn->rollback();

      http_response_code(500);
      echo "<h3>Terjadi Kesalahan!</h3>";
      echo "Pesan Error: " . htmlspecialchars($e->getMessage());
      echo "<br><a href='invoice.php'>Kembali</a>";
      exit();
   }
}
