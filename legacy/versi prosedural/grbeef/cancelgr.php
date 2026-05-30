<?php
// cancelgr.php (minimal)
// Ubah pobeef.stat => 2 (cancel) lalu redirect ke draftgr.php
// Pastikan file ini berada di folder yang sama dengan verifikasi dan koneksi Anda

require "../verifications/auth.php";
require "../konak/conn.php";

$idpo = isset($_GET['id']) ? intval($_GET['id']) : 0;
$redirect = 'draft.php';

if ($idpo <= 0) {
    header("Location: {$redirect}");
    exit;
}

$stmt = $conn->prepare("UPDATE pobeef SET stat = 2 WHERE idpo = ? AND stat = 0");
$stmt->bind_param("i", $idpo);
$stmt->execute();
// optional: $affected = $stmt->affected_rows;
$stmt->close();

header("Location: {$redirect}");
exit;
