<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mendapatkan idrawmate dari URL
$idrawmate = $_GET['idrawmate'];

// Menghapus data rawmate dari database berdasarkan idrawmate
$sql = "DELETE FROM rawmate WHERE idrawmate = '$idrawmate'";

// Mengeksekusi query
if (mysqli_query($conn, $sql)) {
  echo "<script>alert('Data rawmate berhasil dihapus.'); window.location='index.php';</script>";
} else {
  echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

// Menutup koneksi ke database
mysqli_close($conn);
