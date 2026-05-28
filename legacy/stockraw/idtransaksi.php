<?php
require "../konak/conn.php";

// Mendapatkan tahun, bulan, dan tanggal sekarang
$currentYear = date('y'); // Contoh: 24 untuk tahun 2024
$currentMonth = date('m'); // Contoh: 12 untuk bulan Desember
$currentDay = date('d');   // Contoh: 29 untuk tanggal 29

// Membuat prefix berdasarkan tahun, bulan, dan tanggal sekarang: BNYYMMDD
$prefix = "2" . $currentYear; // Misalnya: BN240229

// Menghitung jumlah data yang ada pada tahun berjalan, termasuk yang sudah dihapus
$query = "SELECT COUNT(*) as total FROM stockraw WHERE YEAR(creatime) = YEAR(CURRENT_DATE)";
$stmt = $conn->prepare($query);
$stmt->execute();
$stmt->bind_result($total); // Mengambil hasil COUNT
$stmt->fetch();

// Mengambil jumlah data dan menambahkannya dengan 1
$urut = $total + 1;

// Menambahkan format nomor urut 5 digit dan membuat ID lengkap
$idtransaksi = $prefix . sprintf("%08s", $urut); // Format 5 digit: BN24022900001

// Menutup statement setelah selesai
$stmt->close();
