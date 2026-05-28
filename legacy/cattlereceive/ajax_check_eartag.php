<?php
require "../verifications/auth.php";
require "../konak/conn.php";
header('Content-Type: application/json; charset=utf-8');

function norm_tag($x)
{
  $x = strtoupper(trim((string)$x));
  return $x;
}

$tag = isset($_GET['eartag']) ? norm_tag($_GET['eartag']) : '';
$idreceive_exclude = isset($_GET['idreceive']) && ctype_digit($_GET['idreceive']) ? (int)$_GET['idreceive'] : null;

if ($tag === '') {
  echo json_encode(['ok' => false, 'active' => false, 'msg' => 'eartag empty']);
  exit;
}

// Cek apakah eartag sudah ada di cattle_receive_detail yang terkait ke cattle_receive aktif
$sql = "SELECT 1
        FROM cattle_receive_detail d
        JOIN cattle_receive r ON r.idreceive = d.idreceive
        WHERE r.is_deleted = 0
          AND d.eartag = ?
";

if ($idreceive_exclude !== null) {
  $sql .= " AND d.idreceive <> ? ";
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
  echo json_encode(['ok' => false, 'active' => false, 'msg' => 'prepare failed']);
  exit;
}

if ($idreceive_exclude !== null) {
  $stmt->bind_param('si', $tag, $idreceive_exclude);
} else {
  $stmt->bind_param('s', $tag);
}
$stmt->execute();
$active = (bool) $stmt->get_result()->fetch_row();

echo json_encode(['ok' => true, 'active' => $active, 'eartag' => $tag]);
