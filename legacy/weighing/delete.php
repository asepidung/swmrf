<?php
require "../verifications/auth.php";
require "../konak/conn.php";

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Delete harus via GET id (atau bisa kamu ganti POST kalau mau)
$idweigh = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$iduser  = (int)($_SESSION['idusers'] ?? 0);

if ($idweigh <= 0) {
    die("Data timbang tidak dikenali.");
}
if ($iduser <= 0) {
    die("Session user tidak valid.");
}

try {
    // Soft delete: hanya tandai is_deleted = 1 di HEADER
    $stmt = $conn->prepare("
        UPDATE weight_cattle
        SET is_deleted = 1,
            updateby   = ?,
            updatetime = NOW()
        WHERE idweigh = ? AND is_deleted = 0
        LIMIT 1
    ");
    $stmt->bind_param('ii', $iduser, $idweigh);
    $stmt->execute();

    $affected = $stmt->affected_rows;
    $stmt->close();

    // Kalau tidak ada baris yang berubah, bisa jadi sudah dihapus / tidak ada
    if ($affected <= 0) {
        $msg = "Data timbang tidak ditemukan atau sudah dihapus.";
    } else {
        $msg = "Data timbang berhasil dihapus (soft delete).";
    }

    header("Location: index.php?msg=" . urlencode($msg));
    exit;
} catch (Exception $ex) {
    die("Terjadi kesalahan: " . e($ex->getMessage()));
}
