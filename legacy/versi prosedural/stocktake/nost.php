<?php
require "../konak/conn.php";
$sql = mysqli_query($conn, "SELECT MAX(idst) as maxID from stocktake");
$data = mysqli_fetch_array($sql);
$kode = $data['maxID'];
$kode++;
$nost = sprintf("%05s", $kode);
$kodeauto = "STK" . $nost;
