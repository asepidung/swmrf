<?php
// store_quotes.php
// Menangani penyimpanan quote. Mengambil idusers dari session. Setelah insert redirect kembali ke new_quotes.php.

// safety: langsung stop jika bukan POST
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action']) || $_POST['action'] !== 'store_quote') {
    http_response_code(405);
    exit('Method Not Allowed');
}

require 'verifications/auth.php';   // pastikan session/akses valid
require 'konak/conn.php';           // koneksi $conn (mysqli)

// Ambil user dari session
$idusers = $_SESSION['idusers'] ?? null;
if (!$idusers) {
    $_SESSION['flash_error'] = 'Anda harus login untuk menambahkan quote.';
    header('Location: new_quotes.php');
    exit;
}

// Ambil input dan trim
$isiquote = trim($_POST['isiquote'] ?? '');

// Validasi sederhana
if ($isiquote === '') {
    $_SESSION['flash_error'] = 'Quote tidak boleh kosong.';
    header('Location: new_quotes.php');
    exit;
}
if (mb_strlen($isiquote, 'UTF-8') > 1000) {
    $_SESSION['flash_error'] = 'Quote terlalu panjang (maks 1000 karakter).';
    header('Location: new_quotes.php');
    exit;
}

// Simpan ke DB menggunakan prepared statement
$stmt = $conn->prepare("INSERT INTO quotes (isiquote, idusers) VALUES (?, ?)");
if (!$stmt) {
    $_SESSION['flash_error'] = 'Gagal menyiapkan query: ' . $conn->error;
    header('Location: new_quotes.php');
    exit;
}

$stmt->bind_param('si', $isiquote, $idusers);
if ($stmt->execute()) {
    $_SESSION['flash_success'] = 'Quote berhasil disimpan.';
} else {
    $_SESSION['flash_error'] = 'Gagal menyimpan quote: ' . $stmt->error;
}
$stmt->close();

// Redirect kembali ke form (atau ke index.php jika mau)
header('Location: index.php');
exit;
