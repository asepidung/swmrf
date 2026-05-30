<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id'])) {
   $idsegment = $_GET['id'];

   $hapusdata = mysqli_query($conn, "DELETE FROM segment WHERE idsegment = '$idsegment'");

   // Periksa apakah penghapusan data berhasil dilakukan
   if ($hapusdata) {
      echo "<script>alert('Data berhasil dihapus.'); window.location='segment.php';</script>";
   } else {
      // Jika gagal, tampilkan pesan error
      echo "<script>alert('Maaf, terjadi kesalahan saat menghapus data.'); window.location='segment.php';</script>";
   }
}
