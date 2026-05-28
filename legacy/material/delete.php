<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$idstockout = (int)($_GET['id'] ?? 0);
if ($idstockout <= 0) {
    die("ID dokumen tidak valid.");
}

/* ================================
   Pastikan data ada & belum dihapus
================================ */
$stmtCek = $conn->prepare("
    SELECT idstockout
    FROM raw_stock_out
    WHERE idstockout = ? AND is_deleted = 0
    LIMIT 1
");
$stmtCek->bind_param("i", $idstockout);
$stmtCek->execute();
$cek = $stmtCek->get_result()->fetch_assoc();
$stmtCek->close();

if (!$cek) {
    die("Data tidak ditemukan atau sudah dihapus.");
}

/* ================================
   Soft delete
================================ */
$stmtDel = $conn->prepare("
    UPDATE raw_stock_out
    SET is_deleted = 1
    WHERE idstockout = ?
");
$stmtDel->bind_param("i", $idstockout);
$stmtDel->execute();
$stmtDel->close();

header("Location: index.php?msg=" . urlencode("Data berhasil dihapus."));
exit;
