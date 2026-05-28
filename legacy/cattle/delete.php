<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$iduser = $_SESSION['idusers'] ?? 0;

// Validasi parameter
if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    header("Location: index.php?err=invalid");
    exit;
}
$idpo = (int)$_GET['id'];

$conn->begin_transaction();
try {
    // Cek: jika sudah ada penerimaan aktif, tolak hapus
    $cek = $conn->prepare("SELECT 1 FROM cattle_receive WHERE idpo = ? AND is_deleted = 0 LIMIT 1");
    $cek->bind_param("i", $idpo);
    $cek->execute();
    if ($cek->get_result()->fetch_row()) {
        $conn->rollback();
        header("Location: index.php?err=received"); // Data Sudah di Proses
        exit;
    }

    // Soft delete detail (jika ada)
    $sd = $conn->prepare("
        UPDATE pocattledetail
           SET is_deleted = 1, updatetime = NOW(), updateby = ?
         WHERE idpo = ? AND is_deleted = 0
    ");
    $sd->bind_param("ii", $iduser, $idpo);
    if (!$sd->execute()) {
        throw new Exception("Gagal soft delete detail: " . $sd->error);
    }

    // Soft delete header
    $sh = $conn->prepare("
        UPDATE pocattle
           SET is_deleted = 1, updatetime = NOW(), updateby = ?
         WHERE idpo = ? AND is_deleted = 0
         LIMIT 1
    ");
    $sh->bind_param("ii", $iduser, $idpo);
    if (!$sh->execute()) {
        throw new Exception("Gagal soft delete header: " . $sh->error);
    }

    $conn->commit();
    header("Location: index.php?msg=deleted");
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    // Opsional: log $e->getMessage()
    header("Location: index.php?err=fail");
    exit;
}
