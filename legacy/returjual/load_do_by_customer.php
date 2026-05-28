<?php
require "../konak/conn.php";

$idcustomer = intval($_GET['idcustomer'] ?? 0);
$data = [];

if ($idcustomer > 0) {
    $q = mysqli_query($conn, "
      SELECT donumber, deliverydate
      FROM do
      WHERE idcustomer = $idcustomer
        AND is_deleted = 0
      ORDER BY deliverydate DESC, created DESC
   ");

    while ($r = mysqli_fetch_assoc($q)) {
        $data[] = $r;
    }
}

header('Content-Type: application/json');
echo json_encode($data);
