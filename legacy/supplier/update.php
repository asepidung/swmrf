<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mengecek apakah data sudah diterima dari form
if (isset($_POST['id'])) {
    // Mengambil data dari form
    $id = $_POST['id'];
    $nmsupplier = $_POST['nmsupplier'];
    $alamat = $_POST['alamat'];
    $jenis_usaha = $_POST['jenis_usaha'];
    $telepon = $_POST['telepon'];
    $npwp = $_POST['npwp'];

    // Menggunakan prepared statement untuk menghindari SQL Injection
    $sql = "UPDATE supplier SET nmsupplier = ?, alamat = ?, jenis_usaha = ?, telepon = ?, npwp = ? WHERE idsupplier = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $nmsupplier, $alamat, $jenis_usaha, $telepon, $npwp, $id);

    // Mengeksekusi query
    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui.'); window.location='supplier.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    // Menutup statement dan koneksi
    $stmt->close();
} else {
    echo "ID tidak ditemukan.";
}

// Menutup koneksi ke database
$conn->close();
