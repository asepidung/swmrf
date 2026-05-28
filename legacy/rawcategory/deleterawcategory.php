<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mendapatkan idrawcategory dari URL
$idrawcategory = $_GET['idrawcategory'];

// Menghapus data rawcategory dari database berdasarkan idrawcategory
$sql = "DELETE FROM rawcategory WHERE idrawcategory = '$idrawcategory'";

// Mengeksekusi query
if (mysqli_query($conn, $sql)) {
  echo "<script>alert('Data rawcategory berhasil dihapus.'); window.location='index.php';</script>";
} else {
  echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

// Menutup koneksi ke database
mysqli_close($conn);
