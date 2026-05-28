<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "swm";

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone di PHP
date_default_timezone_set('Asia/Jakarta');

// Set timezone di MySQL
$conn->query("SET time_zone = '+07:00'");

// Periksa apakah file saat ini adalah `get_alamat_note.php`
$currentFile = basename($_SERVER['PHP_SELF']);
if ($currentFile !== 'get_alamat_note.php') {
    // Tutup koneksi otomatis untuk file selain `get_alamat_note.php`
    register_shutdown_function(function () use ($conn) {
        $conn->close();
    });
}
