<?php
require "../verifications/auth.php";
require "../konak/conn.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helper kecil (buat pesan error kalau mau)
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit("Invalid loss id.");
}

$idloss = (int)$_GET['id'];
$iduser = $_SESSION['idusers'] ?? 0;

$conn->begin_transaction();
try {
    // Pastikan data masih aktif
    $stmt = $conn->prepare("
        SELECT idloss 
        FROM cattle_loss_receive 
        WHERE idloss = ? AND is_deleted = 0
        LIMIT 1
    ");
    $stmt->bind_param("i", $idloss);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        // Tidak ada / sudah dihapus
        $conn->rollback();
        header("Location: index.php");
        exit;
    }

    // Soft delete header
    $stmtDel = $conn->prepare("
        UPDATE cattle_loss_receive
        SET is_deleted = 1
        WHERE idloss = ?
        LIMIT 1
    ");
    $stmtDel->bind_param("i", $idloss);
    $stmtDel->execute();
    $stmtDel->close();

    $conn->commit();

    header("Location: index.php");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("Gagal menghapus data loss: " . e($e->getMessage()));
}
