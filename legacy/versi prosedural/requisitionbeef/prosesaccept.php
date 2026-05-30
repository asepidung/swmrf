<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Fungsi untuk membersihkan format angka
function normalizeNumber($number)
{
    return (float) str_replace(['.', ','], '', $number);
}

// Ambil data dari form
$idrequest = $_POST['idrequest'] ?? null;
$duedate = $_POST['duedate'] ?? null;
$idsupplier = $_POST['idsupplier'] ?? null;
$note = $_POST['note'] ?? null;
$top = $_POST['top'] ?? null; // Ambil nilai TOP (Terms of Payment)
$stat = 'Waiting'; // Nilai stat yang akan diperbarui

// Validasi dan ambil nilai tax
$tax = $_POST['tax'] ?? 'No';
if (!in_array($tax, ['No', '11', '12'])) {
    die("Error: Invalid tax value.");
}

// Ambil data detail
$idbarang = $_POST['idbarang'] ?? [];
$weight = $_POST['weight'] ?? [];
$price = $_POST['price'] ?? [];
$notes = $_POST['notes'] ?? [];

// Hitung total harga barang sebelum pajak
$totalAmount = array_sum(array_map(function ($weight, $price) {
    return normalizeNumber($weight) * normalizeNumber($price);
}, $weight, $price));

// Hitung pajak jika tax bukan "No"
if ($tax !== 'No') {
    $taxPercentage = (float) $tax; // Konversi tax menjadi angka
    $taxrp = $totalAmount * ($taxPercentage / 100); // Pajak dihitung dari total harga barang
} else {
    $taxrp = 0; // Jika tidak ada pajak
}

// Hitung total amount termasuk pajak
$xamount = $totalAmount + $taxrp;

// Validasi data wajib
if (!$idrequest || !$duedate || !$idsupplier) {
    die("Error: Missing required fields.");
}

mysqli_begin_transaction($conn);

try {
    // Debug nilai parameter
    var_dump($duedate, $idsupplier, $note, $top, $taxrp, $xamount, $tax, $stat, $idrequest);

    // Update data di tabel `request`
    $query_request = "UPDATE requestbeef SET duedate = ?, idsupplier = ?, note = ?, top = ?, taxrp = ?, xamount = ?, tax = ?, stat = ? WHERE idrequest = ?";
    $stmt_request = mysqli_prepare($conn, $query_request);
    mysqli_stmt_bind_param($stmt_request, "sissdsssi", $duedate, $idsupplier, $note, $top, $taxrp, $xamount, $tax, $stat, $idrequest);

    if (!mysqli_stmt_execute($stmt_request)) {
        // Jika query gagal, tampilkan error
        die("Error executing query: " . mysqli_stmt_error($stmt_request));
    } else {
        echo "Query berhasil, kolom stat diperbarui menjadi 'Waiting' dan TOP disimpan.";
    }

    // Commit transaksi
    mysqli_commit($conn);
    echo "Transaksi berhasil, data berhasil diperbarui.";

    // Redirect setelah berhasil
    header("Location: index.php");
    exit;
} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Transaction failed: " . $e->getMessage());
}
