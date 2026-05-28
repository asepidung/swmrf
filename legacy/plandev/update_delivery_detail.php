<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$idcustomer   = $_POST['idcustomer'];
$deliverydate = $_POST['deliverydate'];
$driver       = $_POST['driver'];
$armada       = $_POST['armada'];
$loadtime     = $_POST['loadtime'];
$idsoArr      = $_POST['idso'] ?? [];
$noteArr      = $_POST['note'] ?? [];

if (empty($idsoArr)) {
    die("Data tidak valid");
}

/* Update HEADER (driver, armada, jam) untuk SEMUA SO */
$stmt = $conn->prepare("
    UPDATE delivery_plan_detail
    SET driver = ?, armada = ?, loadtime = ?
    WHERE idso = ? AND deliverydate = ?
");

for ($i = 0; $i < count($idsoArr); $i++) {
    $idso = $idsoArr[$i];
    $stmt->bind_param("sssis", $driver, $armada, $loadtime, $idso, $deliverydate);
    $stmt->execute();
}
$stmt->close();

/* Update NOTE per SO */
$stmtNote = $conn->prepare("
    UPDATE delivery_plan_detail
    SET note = ?
    WHERE idso = ? AND deliverydate = ?
");

for ($i = 0; $i < count($idsoArr); $i++) {
    $note = $noteArr[$i] ?? '';
    $idso = $idsoArr[$i];
    $stmtNote->bind_param("sis", $note, $idso, $deliverydate);
    $stmtNote->execute();
}

$stmtNote->close();
$conn->close();

header("Location: index.php");
exit;
