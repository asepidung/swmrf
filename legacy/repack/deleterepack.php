<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Validasi input idrepack
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$idrepack = (int)$_GET['id']; // Pastikan idrepack adalah integer

// Query untuk mendapatkan norepack hanya sebagai informasi untuk log
$norepackQuery = "SELECT norepack FROM repack WHERE idrepack = ?";
$stmt = mysqli_prepare($conn, $norepackQuery);
mysqli_stmt_bind_param($stmt, "i", $idrepack);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $norepack);
if (!mysqli_stmt_fetch($stmt)) {
    echo "Data tidak ditemukan.";
    exit();
}
mysqli_stmt_close($stmt);

// Soft delete berdasarkan idrepack
$softDeleteQuery = "UPDATE repack SET is_deleted = 1 WHERE idrepack = ?";
$stmt = mysqli_prepare($conn, $softDeleteQuery);
mysqli_stmt_bind_param($stmt, "i", $idrepack);
if (!mysqli_stmt_execute($stmt)) {
    echo "Terjadi kesalahan saat menghapus data.";
    exit();
}

// Log aktivitas
$logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, 'Soft Delete Repack', ?, NOW())";
$logStmt = mysqli_prepare($conn, $logQuery);
mysqli_stmt_bind_param($logStmt, "is", $_SESSION['idusers'], $norepack);
mysqli_stmt_execute($logStmt);

header("Location: index.php");
exit();
