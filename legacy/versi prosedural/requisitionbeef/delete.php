<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Ambil ID dari parameter URL
$idrequest = $_GET['id'] ?? null;

if (!$idrequest) {
    die("Error: Missing request ID.");
}

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
    // Update kolom is_deleted dengan timestamp sekarang
    $query = "UPDATE requestbeef SET is_deleted = NOW() WHERE idrequest = ?";

    // Gunakan prepared statement untuk keamanan
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $idrequest);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error updating is_deleted: " . mysqli_stmt_error($stmt));
    }

    // Commit transaksi
    mysqli_commit($conn);

    // Redirect ke index.php
    header("Location: index.php");
    exit;
} catch (Exception $e) {
    // Rollback transaksi jika terjadi kesalahan
    mysqli_rollback($conn);
    die("Transaction failed: " . $e->getMessage());
}
