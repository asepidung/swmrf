<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Memastikan ID diterima dari URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Menggunakan prepared statement untuk menghapus data
    $sql = "DELETE FROM supplier WHERE idsupplier = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    // Mengeksekusi query dan memeriksa hasilnya
    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil dihapus.'); window.location='supplier.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    // Menutup statement dan koneksi
    $stmt->close();
    $conn->close();
} else {
    echo "ID tidak ditemukan.";
}
