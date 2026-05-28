<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(["error" => "Parameter ID tidak valid"]);
    exit;
}

$idst = intval($_GET['id']);

// Cek jumlah barang yang belum terscan
$query = "
    SELECT COUNT(*) AS total_missing 
    FROM stock 
    WHERE kdbarcode NOT IN (SELECT kdbarcode FROM stocktakedetail WHERE idst = ?)
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idst);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$total_missing = $row['total_missing'] ?? 0;

echo json_encode(["total_missing" => $total_missing]);
