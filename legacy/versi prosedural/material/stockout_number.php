<?php
require "../konak/conn.php";
$currentYear = date('y');
$prefix = "SOM-" . $currentYear;

$sql = mysqli_query($conn, "SELECT COUNT(*) as total FROM raw_stock_out WHERE YEAR(createtime) = YEAR(CURRENT_DATE)");
$data = mysqli_fetch_array($sql);
$urut = $data['total'] + 1;
$stockout_number = $prefix . sprintf("%04s", $urut);
