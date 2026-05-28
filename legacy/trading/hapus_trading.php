<?php
require "../verifications/auth.php";
// Koneksi ke database
require "../konak/conn.php";

// Periksa apakah parameter idtrading dan idtrading telah diterima
if (isset($_GET['id'])) {
   $idtrading = $_GET['id'];


   // Lakukan penghapusan data dari tabel trading
   $hapusdata = mysqli_query($conn, "DELETE FROM trading WHERE idtrading = '$idtrading'");

   // Periksa apakah penghapusan data berhasil dilakukan
   if ($hapusdata) {
      echo "<script>alert('Data berhasil dihapus.'); window.location='trading.php?';</script>";
   } else {
      echo "<script>alert('Maaf, terjadi kesalahan saat menghapus data.'); window.location='trading.php';</script>";
   }
} else {
   header("Location: trading.php?");
}
