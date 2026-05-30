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
$tax = $_POST['tax'] ?? null; // Ambil nilai tax
$top = $_POST['top'] ?? null;

// Ambil data detail
$idrawmate = $_POST['idrawmate'] ?? [];
$weight = $_POST['weight'] ?? [];
$price = $_POST['price'] ?? [];
$notes = $_POST['notes'] ?? [];

// Hitung total harga rawmate
$totalAmount = array_sum(array_map(function ($weight, $price) {
    return normalizeNumber($weight) * normalizeNumber($price);
}, $weight, $price));

// Hitung pajak berdasarkan nilai tax
$taxRate = $tax === '11' ? 0.11 : ($tax === '12' ? 0.12 : 0);
$taxAmount = $totalAmount * $taxRate;

// Total amount termasuk pajak
$xamount = $totalAmount + $taxAmount;

// Validasi data wajib
if (!$idrequest || !$duedate || !$idsupplier) {
    die("Error: Missing required fields.");
}

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
    // Update data di tabel `request`
    $query_request = "UPDATE request SET duedate = ?, idsupplier = ?, note = ?, tax = ?, top = ?, taxrp = ?, xamount = ? WHERE idrequest = ?";
    $stmt_request = mysqli_prepare($conn, $query_request);
    mysqli_stmt_bind_param($stmt_request, "sisdsddi", $duedate, $idsupplier, $note, $tax, $top, $taxAmount, $xamount, $idrequest);

    if (!mysqli_stmt_execute($stmt_request)) {
        throw new Exception("Error updating request table: " . mysqli_stmt_error($stmt_request));
    }

    // Hapus detail lama untuk ID request ini
    $query_delete_details = "DELETE FROM requestdetail WHERE idrequest = ?";
    $stmt_delete_details = mysqli_prepare($conn, $query_delete_details);
    mysqli_stmt_bind_param($stmt_delete_details, "i", $idrequest);

    if (!mysqli_stmt_execute($stmt_delete_details)) {
        throw new Exception("Error deleting old request details: " . mysqli_stmt_error($stmt_delete_details));
    }

    // Masukkan detail baru
    $query_insert_details = "INSERT INTO requestdetail (idrequest, idrawmate, qty, price, notes) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert_details = mysqli_prepare($conn, $query_insert_details);

    foreach ($idrawmate as $i => $rawmate) {
        $qty = normalizeNumber($weight[$i] ?? 0);
        $product_price = normalizeNumber($price[$i] ?? 0);
        $product_note = $notes[$i] ?? '';

        mysqli_stmt_bind_param($stmt_insert_details, "iiids", $idrequest, $rawmate, $qty, $product_price, $product_note);

        if (!mysqli_stmt_execute($stmt_insert_details)) {
            throw new Exception("Error inserting into requestdetail table: " . mysqli_stmt_error($stmt_insert_details));
        }
    }

    mysqli_commit($conn);

    header("Location: index.php");
    exit;
} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Transaction failed: " . $e->getMessage());
}
