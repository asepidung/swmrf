<?php
require "../konak/conn.php";
$currentYear = date('y');
$prefix = "PO-LOG#" . $currentYear;

$sql = mysqli_query($conn, "SELECT COUNT(*) as total FROM repack WHERE YEAR(dibuat) = YEAR(CURRENT_DATE)");
$data = mysqli_fetch_array($sql);
$urut = $data['total'] + 1;
$norepack = $prefix . sprintf("%04s", $urut);
echo $norepack;
