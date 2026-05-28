<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mendapatkan idbarang dari URL
$idbarang = $_GET['idbarang'];

// Menghapus data barang dari database berdasarkan idbarang
$sql = "DELETE FROM barang WHERE idbarang = '$idbarang'";

// Mengeksekusi query dan menangani potensi error
if (mysqli_query($conn, $sql)) {
  echo "<script>alert('Data barang berhasil dihapus.'); window.location='barang.php';</script>";
} else {
  // Mengecek jika error terjadi karena ada foreign key constraint yang terlanggar
  if (mysqli_errno($conn) == 1451) {
    echo "<script>alert('Barang sudah ada di transaksi lain dan tidak bisa dihapus.'); window.location='barang.php';</script>";
  } else {
    echo "Error: " . $sql . "<br>" . mysqli_error($conn);
  }
}

// Menutup koneksi ke database
mysqli_close($conn);
