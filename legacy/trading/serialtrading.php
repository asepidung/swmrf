<?php
require "../konak/conn.php";
$sql = mysqli_query($conn, "SELECT MAX(idtrading) as maxID from trading");
$data = mysqli_fetch_array($sql);
$kode = $data['maxID'];
$kode++;
$seriallabel = sprintf("%09s", $kode);
$currentYear = date("y");  // Mendapatkan dua digit tahun saat ini (misalnya 23 untuk tahun 2023)
$currentMonth = date("m");  // Mendapatkan dua digit bulan saat ini
$currentDay = date("d");  // Mendapatkan dua digit tanggal saat ini

$kodeauto = $currentYear . $currentMonth . $currentDay . $seriallabel;
// echo $kodeauto;