<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Validasi input
$idboning = isset($_GET['idboning']) ? (int)$_GET['idboning'] : 0;
$kunci = isset($_GET['kunci']) ? (int)$_GET['kunci'] : 0;

// Pastikan ID valid
if ($idboning > 0) {
   // Update kolom `kunci` di tabel boning
   $query = "UPDATE boning SET kunci = $kunci WHERE idboning = $idboning";
   if (mysqli_query($conn, $query)) {
      // Redirect kembali ke halaman utama setelah berhasil
      header("Location: databoning.php");
      exit;
   } else {
      echo "Error: " . mysqli_error($conn);
   }
} else {
   echo "Invalid ID.";
}
