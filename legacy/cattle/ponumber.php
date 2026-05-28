<?php
require "../konak/conn.php";

// Dapatkan tahun berjalan
$currentYear = date('Y');

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
    // Hitung jumlah data untuk tahun berjalan (termasuk data yang dihapus)
    $sqlCount = mysqli_query($conn, "SELECT COUNT(*) as count FROM pocattle WHERE YEAR(creatime) = $currentYear FOR UPDATE");
    $dataCount = mysqli_fetch_array($sqlCount);

    // Tentukan nomor permintaan berikutnya
    $nextNumber = $dataCount['count'] + 1;

    // Format nomor permintaan
    $pocattleNumber = sprintf("%04s", $nextNumber);
    $nopocattle = "CPO-" . substr($currentYear, 2) . "$pocattleNumber";

    // Commit transaksi (selesai menghitung)
    mysqli_commit($conn);

    // Output nomor permintaan
    echo $nopocattle;
} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    mysqli_rollback($conn);
    echo "Error: " . $e->getMessage();
}
