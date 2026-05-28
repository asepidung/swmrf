<?php
require "../konak/conn.php";
$sql = mysqli_query($conn, "SELECT MAX(idrawmate) as maxID from rawmate");
$data = mysqli_fetch_array($sql);
$kode = $data['maxID'];
$kode++;
$kodeauto = "RM" .  sprintf("%04s", $kode);

// echo $kodeauto;
