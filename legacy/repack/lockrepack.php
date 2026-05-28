<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Ambil idrepack dari parameter GET
$idrepack = $_GET['id'];

// Pastikan yang login adalah user 1 atau 2
$idusers = $_SESSION['idusers'];
if ($idusers != 1 && $idusers != 2) {
    header("Location: repack.php");
    exit;
}

// Cek apakah kunci sudah bernilai 1 (approved)
$queryCheckKunci = "SELECT kunci FROM repack WHERE idrepack = ?";
$stmtCheckKunci = $conn->prepare($queryCheckKunci);
$stmtCheckKunci->bind_param("i", $idrepack);
$stmtCheckKunci->execute();
$stmtCheckKunci->bind_result($kunci);
$stmtCheckKunci->fetch();
$stmtCheckKunci->close();

// Jika kunci tidak bernilai 1 (approved), redirect dan beri pesan error
if ($kunci != 1) {
    header("Location: repack.php?message=Proses tidak bisa dikunci karena status kunci bukan Approved.");
    exit;
}

// Update status kunci menjadi 2 (locked) di tabel repack
$query = "UPDATE repack SET kunci = 2 WHERE idrepack = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idrepack);
$stmt->execute();
$stmt->close();

// Redirect kembali ke halaman daftar repack
header("Location: index.php");
exit;
