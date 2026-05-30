<?php
require "../konak/conn.php";

// Mendapatkan tahun, bulan, dan tanggal sekarang
$currentYear = date('y'); // Contoh: 24 untuk tahun 2024
$currentMonth = date('m'); // Contoh: 12 untuk bulan Desember
$currentDay = date('d');   // Contoh: 29 untuk tanggal 29

// Membuat prefix berdasarkan tahun, bulan, dan tanggal sekarang: BNYYMMDD
$prefix = "RJ-" . $currentYear; // Misalnya: BN240229

// Menghitung jumlah data yang ada pada tahun berjalan, termasuk yang sudah dihapus
$sql = mysqli_query($conn, "SELECT COUNT(*) as total FROM returjual WHERE YEAR(creatime) = YEAR(CURRENT_DATE)");
$data = mysqli_fetch_array($sql);

// Mengambil jumlah data dan menambahkannya dengan 1
$urut = $data['total'] + 1;

// Menambahkan format nomor urut 3 digit dan membuat ID lengkap
$returnnumber = $prefix . sprintf("%03s", $urut); // Format 3 digit: BN240229001
