<?php
require "../konak/conn.php";

// Mendapatkan dua digit tahun dan bulan saat ini
$currentYear = date("y");  // Contoh: 23 untuk tahun 2023
$currentMonth = date("m");  // Contoh: 12 untuk bulan Desember

// Fungsi untuk mengonversi angka ke Romawi
function angkaToRomawi($angka)
{
   $romawi = [
      'I',
      'II',
      'III',
      'IV',
      'V',
      'VI',
      'VII',
      'VIII',
      'IX',
      'X',
      'XI',
      'XII'
   ];
   return $angka >= 1 && $angka <= 12 ? $romawi[$angka - 1] : "Invalid";
}

// Konversi bulan ke format Romawi
$romawiMonth = angkaToRomawi($currentMonth);

// Menghitung jumlah data pada tahun berjalan
$sql = mysqli_query($conn, "SELECT COUNT(*) as total FROM invoice WHERE YEAR(creatime) = YEAR(CURRENT_DATE)");
$data = mysqli_fetch_array($sql);

// Mengambil jumlah data dan menambahkannya dengan 1
$urut = $data['total'] + 1;

// Format nomor urut menjadi 3 digit
$invnumber = sprintf("%04s", $urut);

// Membuat nomor invoice
$noinvoice = "INV-SWM/" . $currentYear . "/" . $romawiMonth . "/" . $invnumber;

// Output untuk debugging
echo $noinvoice;
