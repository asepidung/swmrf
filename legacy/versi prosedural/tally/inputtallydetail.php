<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_POST['barcode'])) {
   // Sanitasi dasar
   $barcode = trim($_POST['barcode']);
   $idtally = (int)$_POST['idtally'];
   $_SESSION['limit'] = $_POST['limit'] ?? null;

   if ($barcode === '' || $idtally <= 0) {
      header("location: tallydetail.php?id=$idtally&stat=badinput");
      exit;
   }

   // Escape untuk string yang akan dimasukkan ke query
   $barcodeEsc = mysqli_real_escape_string($conn, $barcode);

   // Ambil idso
   $idso_query = "SELECT idso FROM tally WHERE idtally = $idtally";
   $idso_result = mysqli_query($conn, $idso_query);
   $idso = 0;
   if ($idso_result && ($idso_row = mysqli_fetch_assoc($idso_result))) {
      $idso = (int)$idso_row['idso'];
   }
   if ($idso <= 0) {
      header("location: tallydetail.php?id=$idtally&stat=notally");
      exit;
   }

   // Cek duplikat barcode di tallydetail
   $cekBarcodeQuery = "SELECT idtallydetail FROM tallydetail WHERE idtally = $idtally AND barcode = '$barcodeEsc' LIMIT 1";
   $cekBarcodeResult = mysqli_query($conn, $cekBarcodeQuery);
   if ($cekBarcodeResult && mysqli_num_rows($cekBarcodeResult) > 0) {
      header("location: tallydetail.php?id=$idtally&stat=duplicate");
      exit;
   }

   // Ambil data dari stock
   $query = "SELECT idbarang, idgrade, qty, pcs, ph, pod, origin
              FROM stock
              WHERE kdbarcode = '$barcodeEsc'
              LIMIT 1";
   $result = mysqli_query($conn, $query);

   if ($result && $row = mysqli_fetch_assoc($result)) {
      $idbarang = (int)$row['idbarang'];
      $idgrade  = (int)$row['idgrade'];
      $weight   = (float)$row['qty'];
      $pcs      = (int)$row['pcs'];
      $pod      = $row['pod'];     // format tanggal dari DB
      $origin   = $row['origin'];
      $phRaw    = $row['ph'];

      // --- Normalisasi PH ---
      // Jika kosong/NULL -> gunakan literal SQL NULL (tanpa kutip)
      // Jika ada -> ubah koma jadi titik, validasi float, dan kirim angka (tanpa kutip)
      if ($phRaw === '' || is_null($phRaw)) {
         $phSql = 'NULL';
      } else {
         $phNorm = str_replace(',', '.', (string)$phRaw);
         $phVal  = filter_var($phNorm, FILTER_VALIDATE_FLOAT);
         if ($phVal === false) {
            $phSql = 'NULL';
         } else {
            // Opsional: truncate ke 1 desimal (bukan pembulatan)
            $phVal = floor($phVal * 10) / 10;
            // Pastikan string angka dengan 1 desimal
            $phSql = number_format($phVal, 1, '.', '');
         }
      }

      // Pastikan idbarang ada di salesorderdetail
      $cekBarangQuery = "SELECT 1 FROM salesorderdetail WHERE idso = $idso AND idbarang = $idbarang LIMIT 1";
      $cekBarangResult = mysqli_query($conn, $cekBarangQuery);

      if (!$cekBarangResult || mysqli_num_rows($cekBarangResult) === 0) {
         header("location: tallydetail.php?id=$idtally&stat=unlisted");
         exit;
      }

      // Escape string lain
      $podEsc    = mysqli_real_escape_string($conn, (string)$pod);
      $originEsc = mysqli_real_escape_string($conn, (string)$origin);

      // Bangun INSERT: angka tanpa kutip, string dengan kutip, ph pakai $phSql (bisa NULL tanpa kutip)
      $insertQuery = "
            INSERT INTO tallydetail
                (idtally, barcode, idbarang, idgrade, weight, pcs, pod, origin, ph)
            VALUES
                ($idtally, '$barcodeEsc', $idbarang, $idgrade, $weight, $pcs, '$podEsc', '$originEsc', $phSql)
        ";
      mysqli_query($conn, $insertQuery);

      // Hapus dari stock
      $deleteQuery = "DELETE FROM stock WHERE kdbarcode = '$barcodeEsc'";
      mysqli_query($conn, $deleteQuery);

      header("location: tallydetail.php?id=$idtally&stat=success");
      exit;
   } else {
      // Barcode tidak ditemukan di stock
      $_SESSION['barcode'] = $barcode;
      header("location: tallydetail.php?id=$idtally&stat=unknown");
      exit;
   }
}
