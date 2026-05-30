<?php
session_start();
require 'verifications/auth.php';
require 'konak/conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

// Ambil data dari form
$idquote = intval($_POST['idquote'] ?? 0);
$isiquote = trim($_POST['isiquote'] ?? '');

if ($idquote <= 0) {
    $_SESSION['flash_error'] = "Invalid quote ID.";
    header("Location: edit_quotes.php?id=" . $idquote);
    exit;
}

if ($isiquote === '') {
    $_SESSION['flash_error'] = "Quote tidak boleh kosong.";
    header("Location: edit_quotes.php?id=" . $idquote);
    exit;
}

if (mb_strlen($isiquote, 'UTF-8') > 1000) {
    $_SESSION['flash_error'] = "Quote terlalu panjang (maksimum 1000 karakter).";
    header("Location: edit_quotes.php?id=" . $idquote);
    exit;
}

// Update hanya isiquote
$stmt = $conn->prepare("UPDATE quotes SET isiquote = ? WHERE idquote = ?");
$stmt->bind_param("si", $isiquote, $idquote);

if ($stmt->execute()) {
    header("Location: index.php");
    exit;
} else {
    $_SESSION['flash_error'] = "Gagal memperbarui quote.";
    header("Location: edit_quotes.php?id=" . $idquote);
    exit;
}
