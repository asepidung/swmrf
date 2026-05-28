<?php
require "../verifications/auth.php";
// Koneksi ke database
require "../konak/conn.php";

if (isset($_GET['id'])) {
   $idcustomer = $_GET['id'];

   $hapusdata = mysqli_query($conn, "DELETE FROM customers WHERE idcustomer = '$idcustomer'");

   // Periksa apakah penghapusan data berhasil dilakukan
   if ($hapusdata) {
      // Jika berhasil, arahkan kembali ke halaman sebelumnya dengan pesan sukses dan idboning yang dilewatkan sebagai parameter query string
      echo "<script>alert('Data berhasil dihapus.'); window.location='customer.php';</script>";
   } else {
      // Jika gagal, tampilkan pesan error
      echo "<script>alert('Maaf, terjadi kesalahan saat menghapus data.'); window.location='customer.php';</script>";
   }
}
