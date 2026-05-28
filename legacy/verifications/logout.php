<?php
session_start();
require "../konak/conn.php"; // Tambahkan koneksi ke database

if (isset($_SESSION['idusers'])) {
   $idusers = $_SESSION['idusers']; // Ambil idusers dari session

   // Insert ke tabel logactivity untuk mencatat aktivitas logout
   $logSql = "INSERT INTO logactivity (iduser, event) VALUES ('$idusers', 'Logout')";
   mysqli_query($conn, $logSql);
}

session_unset();
$_SESSION = [];
session_destroy();
header("location: login.php");
exit;
