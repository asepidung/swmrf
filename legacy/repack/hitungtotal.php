<?php

// Hitung total qty dari tabel detailbahan berdasarkan idrepack
$queryTotalBahan = "SELECT SUM(qty) AS total_bahan
FROM detailbahan
WHERE idrepack = " . $tampil['idrepack'];
$resultTotalBahan = mysqli_query($conn, $queryTotalBahan);
$rowTotalBahan = mysqli_fetch_assoc($resultTotalBahan);

// Hitung total qty dari tabel detailhasil berdasarkan idrepack
$queryTotalHasil = "SELECT SUM(qty) AS total_hasil
FROM detailhasil
WHERE idrepack = " . $tampil['idrepack'] . " AND is_deleted = 0";
$resultTotalHasil = mysqli_query($conn, $queryTotalHasil);
$rowTotalHasil = mysqli_fetch_assoc($resultTotalHasil);

// Cek jika nilai total_bahan dan total_hasil ada dan hitung lost
$totalBahan = isset($rowTotalBahan['total_bahan']) ? $rowTotalBahan['total_bahan'] : 0;
$totalHasil = isset($rowTotalHasil['total_hasil']) ? $rowTotalHasil['total_hasil'] : 0;

$lost = $totalHasil - $totalBahan;
