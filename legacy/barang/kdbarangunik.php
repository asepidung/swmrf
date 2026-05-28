<?php
require "../konak/conn.php";
$sql = mysqli_query($conn, "SELECT MAX(idbarang) as maxID from barang");
$data = mysqli_fetch_array($sql);
$kode = $data['maxID'];
$kode++;
$kodeauto = sprintf("%04s", $kode);

// echo $kodeauto;
