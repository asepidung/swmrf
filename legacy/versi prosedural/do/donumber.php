<?php
require "../konak/conn.php";

// Mendapatkan tahun dan bulan sekarang
$currentYear = date('y'); // Contoh: 24 untuk tahun 2024
$currentMonth = date('m'); // Contoh: 12 untuk bulan Desember

// Fungsi untuk mengonversi angka ke Romawi
function angkaToRomawi($angka)
{
    $romawi = [
        'I', 'II', 'III', 'IV', 'V', 'VI',
        'VII', 'VIII', 'IX', 'X', 'XI', 'XII'
    ];
    return $angka >= 1 && $angka <= 12 ? $romawi[$angka - 1] : "Invalid";
}

// Konversi bulan ke format Romawi
$romawiMonth = angkaToRomawi($currentMonth);

// Menghitung jumlah data pada tahun berjalan
$sql = mysqli_query($conn, "SELECT COUNT(*) as total FROM do WHERE YEAR(created) = YEAR(CURRENT_DATE)");
$data = mysqli_fetch_array($sql);

// Mengambil jumlah data dan menambahkannya dengan 1
$urut = $data['total'] + 1;

// Format nomor urut menjadi 5 digit
$donumber = sprintf("%04s", $urut);

// Membuat kode auto DO
$kodeauto = "DO-SWM/" . $currentYear . "/" . $romawiMonth . "/" . $donumber;

// Menampilkan kode auto DO (untuk debugging)
echo $kodeauto;
?>
