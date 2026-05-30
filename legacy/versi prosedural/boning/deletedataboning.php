<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mendapatkan idboning dari parameter URL
$idboning = $_GET['idboning'];

// Mengambil semua kdbarcode dari tabel labelboning yang terkait dengan idboning
$query = "SELECT kdbarcode FROM labelboning WHERE idboning = $idboning";
$result = mysqli_query($conn, $query);

// Menyimpan semua kdbarcode dalam array
$kdbarcodes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $kdbarcodes[] = $row['kdbarcode'];
}

// Mengecek apakah semua kdbarcode ada di tabel stock
$allExist = true;
foreach ($kdbarcodes as $kdbarcode) {
    $checkStockQuery = "SELECT COUNT(*) as total FROM stock WHERE kdbarcode = '$kdbarcode'";
    $checkStockResult = mysqli_query($conn, $checkStockQuery);
    $stockData = mysqli_fetch_assoc($checkStockResult);

    // Jika ada satu kdbarcode yang tidak ada di tabel stock, set flag menjadi false
    if ($stockData['total'] == 0) {
        $allExist = false;
        break;
    }
}

if (!$allExist) {
    // Jika ada barang yang tidak ditemukan di stock
    echo '<div class="alert alert-danger" role="alert">';
    echo 'Pengahpusan GAGAL !!! ada barang yang sudah di proses di modul lain, silahkan klik Back';
    echo '</div>';

    // Tombol Back untuk kembali ke halaman sebelumnya
    echo '<button onclick="history.back()" class="btn btn-secondary">Back</button>';
    exit;
} else {
    // Jika semua kdbarcode ditemukan, lanjutkan penghapusan
    // Soft delete data boning
    $softDeleteBoning = "UPDATE boning SET is_deleted = 1 WHERE idboning = $idboning";
    if (mysqli_query($conn, $softDeleteBoning)) {
        // Hapus data di tabel stock yang terkait dengan kdbarcode
        foreach ($kdbarcodes as $kdbarcode) {
            $deleteStock = "DELETE FROM stock WHERE kdbarcode = '$kdbarcode'";
            mysqli_query($conn, $deleteStock);
        }

        // Catat aktivitas ke logactivity setelah data berhasil dihapus
        $idusers = $_SESSION['idusers'];
        $logSql = "INSERT INTO logactivity (iduser, event, docnumb) VALUES ('$idusers', 'Soft Hapus Batch Boning', '$idboning')";
        mysqli_query($conn, $logSql);

        // Redirect setelah berhasil
        header("Location: databoning.php");
        exit;
    } else {
        // Jika gagal melakukan soft delete
        echo "Gagal mengupdate data boning: " . mysqli_error($conn);
    }
}

// Tutup koneksi database
mysqli_close($conn);
