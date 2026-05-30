<?php
require "../verifications/auth.php";
require "../konak/conn.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Invalid request']);
    exit;
}

$name = trim($_POST['class_name'] ?? '');
$name = strtoupper($name);

if ($name === '') {
    echo json_encode(['ok' => false, 'msg' => 'Nama class wajib']);
    exit;
}

// cek duplikat
$stmt = $conn->prepare("SELECT 1 FROM cattle_class WHERE class_name=? LIMIT 1");
$stmt->bind_param('s', $name);
$stmt->execute();
if ($stmt->get_result()->fetch_row()) {
    echo json_encode(['ok' => false, 'msg' => 'Class sudah ada']);
    exit;
}
$stmt->close();

// insert
$ins = $conn->prepare("INSERT INTO cattle_class (class_name) VALUES (?)");
$ins->bind_param('s', $name);
if (!$ins->execute()) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan class']);
    exit;
}

echo json_encode(['ok' => true, 'class_name' => $name]);
