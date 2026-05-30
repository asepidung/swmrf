<?php
require "../konak/conn.php";

// Mendapatkan tahun, bulan, dan tanggal sekarang
$currentYear = date("y");  // Tahun dalam format 2 digit (misalnya 23 untuk 2023)
$currentMonth = date("m"); // Bulan dalam format 2 digit
$currentDay = date("d");   // Tanggal dalam format 2 digit

// Membuat prefix berdasarkan tahun, bulan, dan tanggal: YYMMDD
$prefix = $currentYear . $currentMonth . $currentDay;  // Misalnya: 240229

// Menghitung jumlah data dalam tahun berjalan
$sql = mysqli_query($conn, "SELECT COUNT(*) AS total FROM returjualdetail WHERE YEAR(creatime) = YEAR(CURRENT_DATE)");
$data = mysqli_fetch_array($sql);
$count = $data['total'];  // Jumlah data yang ditemukan untuk tahun ini

// Tambahkan 1 untuk nomor urut berikutnya
$kode = $count + 1;

// Format nomor urut menjadi 5 digit
$seriallabel = sprintf("%09s", $kode);

// Menggabungkan prefix dengan nomor urut untuk menghasilkan ID lengkap
$kodeauto = $prefix . $seriallabel;

// Menampilkan nomor auto-generated (opsional untuk debugging)
// echo $kodeauto;
