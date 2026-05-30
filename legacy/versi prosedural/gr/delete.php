<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mengambil parameter dari URL
$idgr = isset($_GET['idgr']) ? intval($_GET['idgr']) : 0;
$idpo = isset($_GET['idpo']) ? intval($_GET['idpo']) : 0;

// Validasi parameter
if ($idgr <= 0 || $idpo <= 0) {
    echo "<script>alert('Invalid parameters!'); window.location='index.php';</script>";
    exit();
}

// Mulai transaksi
$conn->autocommit(false);

try {
    // Soft delete data dari tabel stockraw berdasarkan idtransaksi yang terkait dengan idgr
    $queryDeleteStock = "
        UPDATE stockraw 
        SET is_deleted = 1 
        WHERE idtransaksi IN (SELECT idtransaksi FROM grrawdetail WHERE idgr = ?)";
    $stmtDeleteStock = $conn->prepare($queryDeleteStock);

    if (!$stmtDeleteStock) {
        throw new Exception("Error preparing delete stockraw query: " . $conn->error);
    }

    $stmtDeleteStock->bind_param("i", $idgr);
    if (!$stmtDeleteStock->execute()) {
        throw new Exception("Error executing delete stockraw query: " . $stmtDeleteStock->error);
    }

    // Soft delete data dari tabel grrawdetail berdasarkan idgr
    $queryDeleteGrDetail = "UPDATE grrawdetail SET is_deleted = 1 WHERE idgr = ?";
    $stmtDeleteGrDetail = $conn->prepare($queryDeleteGrDetail);

    if (!$stmtDeleteGrDetail) {
        throw new Exception("Error preparing delete grrawdetail query: " . $conn->error);
    }

    $stmtDeleteGrDetail->bind_param("i", $idgr);
    if (!$stmtDeleteGrDetail->execute()) {
        throw new Exception("Error executing delete grrawdetail query: " . $stmtDeleteGrDetail->error);
    }

    // Soft delete data dari tabel grraw
    $queryDeleteGr = "UPDATE grraw SET is_deleted = 1 WHERE idgr = ?";
    $stmtDeleteGr = $conn->prepare($queryDeleteGr);

    if (!$stmtDeleteGr) {
        throw new Exception("Error preparing delete grraw query: " . $conn->error);
    }

    $stmtDeleteGr->bind_param("i", $idgr);
    if (!$stmtDeleteGr->execute()) {
        throw new Exception("Error executing delete grraw query: " . $stmtDeleteGr->error);
    }

    // Update status tabel po menjadi 0
    $queryUpdatePo = "UPDATE po SET stat = 0 WHERE idpo = ?";
    $stmtUpdatePo = $conn->prepare($queryUpdatePo);

    if (!$stmtUpdatePo) {
        throw new Exception("Error preparing update po query: " . $conn->error);
    }

    $stmtUpdatePo->bind_param("i", $idpo);
    if (!$stmtUpdatePo->execute()) {
        throw new Exception("Error executing update po query: " . $stmtUpdatePo->error);
    }

    // Commit transaksi
    $conn->commit();

    // Redirect ke halaman index dengan pesan sukses
    echo "<script>alert('Data berhasil dihapus.'); window.location='index.php';</script>";
    exit();
} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    $conn->rollback();
    echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='index.php';</script>";
} finally {
    // Set autocommit kembali ke true dan tutup koneksi
    $conn->autocommit(true);

    if (isset($stmtDeleteStock)) {
        $stmtDeleteStock->close();
    }
    if (isset($stmtDeleteGrDetail)) {
        $stmtDeleteGrDetail->close();
    }
    if (isset($stmtDeleteGr)) {
        $stmtDeleteGr->close();
    }
    if (isset($stmtUpdatePo)) {
        $stmtUpdatePo->close();
    }
    $conn->close();
}
