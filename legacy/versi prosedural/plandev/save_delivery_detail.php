<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request");
}

$driver   = $_POST['driver'] ?? '';
$armada  = $_POST['armada'] ?? '';
$loadtime = $_POST['loadtime'] ?? '';
$idsoArr = $_POST['idso'] ?? [];
$noteArr = $_POST['note'] ?? [];

if (
    empty($driver) ||
    empty($armada) ||
    empty($loadtime) ||
    empty($idsoArr)
) {
    die("Data tidak lengkap");
}

/*
   Ambil deliverydate & idcustomer
   dari SO pertama (semua sama)
*/
$firstIdso = $idsoArr[0];
$q = mysqli_query($conn, "
   SELECT idcustomer, deliverydate
   FROM salesorder
   WHERE idso = '$firstIdso'
");
$soHeader = mysqli_fetch_assoc($q);

$idcustomer   = $soHeader['idcustomer'];
$deliverydate = $soHeader['deliverydate'];

/* Prepare statement */
$stmt = $conn->prepare("
   INSERT INTO delivery_plan_detail
   (idso, idcustomer, deliverydate, driver, armada, loadtime, note)
   VALUES (?, ?, ?, ?, ?, ?, ?)
");

for ($i = 0; $i < count($idsoArr); $i++) {
    $idso = $idsoArr[$i];
    $note = $noteArr[$i] ?? '';

    $stmt->bind_param(
        "iisssss",
        $idso,
        $idcustomer,
        $deliverydate,
        $driver,
        $armada,
        $loadtime,
        $note
    );
    $stmt->execute();
}

$stmt->close();
$conn->close();

/* balik ke index */
header("Location: index.php");
exit;
