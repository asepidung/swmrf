<?php
require "../verifications/auth.php";
if (!isset($_SESSION['login'])) {
    header("location: ../verifications/login.php");
    exit;
}

// Koneksi ke database
require "../konak/conn.php";

// Pastikan kdbarcode ada dalam URL
if (!isset($_GET['kdbarcode']) || empty($_GET['kdbarcode'])) {
    die("Error: kdbarcode is missing or invalid.");
}

$kdbarcode = mysqli_real_escape_string($conn, $_GET['kdbarcode']);

// Soft delete untuk tabel stockin (update is_deleted = 1)
$query_soft_delete = "UPDATE stockin SET is_deleted = 1 WHERE kdbarcode = '$kdbarcode'";
$result_soft_delete = mysqli_query($conn, $query_soft_delete);

if (!$result_soft_delete) {
    die("Gagal melakukan delete di stockin: " . mysqli_error($conn));
}

// Hard delete untuk tabel stock (hapus data permanen)
$query_hard_delete = "DELETE FROM stock WHERE kdbarcode = '$kdbarcode'";
$result_hard_delete = mysqli_query($conn, $query_hard_delete);

if (!$result_hard_delete) {
    die("Gagal menghapus data di stock: " . mysqli_error($conn));
}

// Redirect kembali ke halaman index.php setelah berhasil
header("Location: index.php");
exit;
