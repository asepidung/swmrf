<?php
// cancelgr.php (minimal, for table `po`)
// Set po.stat = 2 (cancel) lalu redirect ke draft.php
// REQUIRE: ../verifications/auth.php and ../konak/conn.php (mysqli $conn)

require "../verifications/auth.php";
require "../konak/conn.php";

$idpo = isset($_GET['id']) ? intval($_GET['id']) : 0;
$redirect = 'draft.php'; // kembali ke daftar PO material

if ($idpo <= 0) {
    header("Location: {$redirect}");
    exit;
}

try {
    // Optional safety: hanya update jika stat saat ini = 0
    $stmt = $conn->prepare("UPDATE po SET stat = 2 WHERE idpo = ? AND stat = 0");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $idpo);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Jika ingin tahu apakah ada row terpengaruh:
    // $affected = $stmt->affected_rows;

    $stmt->close();
} catch (Exception $e) {
    // log error jika perlu, lalu tetap redirect
    error_log("cancelgr.php error: " . $e->getMessage());
}

header("Location: {$redirect}");
exit;
