<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id']) && isset($_GET['idmutasidetail'])) {
   $id = $_GET['id'];
   $idmutasidetail = $_GET['idmutasidetail'];

   // Gunakan prepared statement untuk menghindari SQL injection
   $hapusdata = mysqli_prepare($conn, "DELETE FROM mutasidetail WHERE idmutasidetail = ?");
   mysqli_stmt_bind_param($hapusdata, "i", $idmutasidetail);
   mysqli_stmt_execute($hapusdata);

   // Periksa apakah penghapusan data berhasil dilakukan
   if (mysqli_stmt_affected_rows($hapusdata) > 0) {
      header("Location: mutasidetail.php?id=$id&stat=deleted");
   } else {
      // Jika gagal, tampilkan pesan error
      echo "<script>alert('Maaf, terjadi kesalahan saat menghapus data.'); window.location='mutasidetail.php?id=$id';</script>";
   }

   // Tutup statement setelah digunakan
   mysqli_stmt_close($hapusdata);

   // Tutup koneksi setelah selesai
   mysqli_close($conn);
}
