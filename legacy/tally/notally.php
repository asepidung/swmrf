<?php
require "../konak/conn.php";


$currentYear = date('y'); 
$currentMonth = date('m'); 
$currentDay = date('d'); 
$prefix = "TS-" . $currentYear; 
$sql = mysqli_query($conn, "SELECT COUNT(*) as total FROM tally WHERE YEAR(creatime) = YEAR(CURRENT_DATE)");
$data = mysqli_fetch_array($sql);
$urut = $data['total'] + 1;
$kodeauto = $prefix . sprintf("%03s", $urut);
echo $kodeauto;
?>
