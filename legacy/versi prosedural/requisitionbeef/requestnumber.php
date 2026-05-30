<?php
require "../konak/conn.php";

// Dapatkan tahun berjalan
$currentYear = date('Y');

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
    // Hitung jumlah data untuk tahun berjalan (termasuk data yang dihapus)
    $sqlCount = mysqli_query($conn, "SELECT COUNT(*) as count FROM requestbeef WHERE YEAR(creatime) = $currentYear FOR UPDATE");
    $dataCount = mysqli_fetch_array($sqlCount);

    // Tentukan nomor permintaan berikutnya
    $nextNumber = $dataCount['count'] + 1;

    // Format nomor permintaan
    $requestNumber = sprintf("%04s", $nextNumber);
    $norequest = "RED-" . substr($currentYear, 2) . "/$requestNumber";

    // Commit transaksi (selesai menghitung)
    mysqli_commit($conn);

    // Output nomor permintaan
    echo $norequest;
} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    mysqli_rollback($conn);
    echo "Error: " . $e->getMessage();
}
