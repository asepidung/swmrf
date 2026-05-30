<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$iduser = $_SESSION['idusers'] ?? 0;

// Validasi parameter
if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: index.php?err=invalid");
    exit;
}
$idreceive = (int)$_GET['id'];

$conn->begin_transaction();
try {
    // Pastikan record ada & belum dihapus
    $cek = $conn->prepare("SELECT idpo FROM cattle_receive WHERE idreceive = ? AND is_deleted = 0 LIMIT 1");
    $cek->bind_param("i", $idreceive);
    $cek->execute();
    $row = $cek->get_result()->fetch_assoc();
    if (!$row) {
        $conn->rollback();
        header("Location: index.php?err=notfound");
        exit;
    }

    // Soft delete HANYA header (detail dibiarkan utuh)
    $upd = $conn->prepare("
        UPDATE cattle_receive
           SET is_deleted = 1,
               updatetime = NOW(),
               updateby   = ?
         WHERE idreceive = ? AND is_deleted = 0
         LIMIT 1
    ");
    $upd->bind_param("ii", $iduser, $idreceive);
    if (!$upd->execute()) {
        throw new Exception("Gagal soft delete header: " . $upd->error);
    }

    $conn->commit();
    header("Location: index.php?msg=deleted");
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    // Optional: log $e->getMessage()
    header("Location: index.php?err=fail");
    exit;
}
