<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "swm";

// buat koneksi hanya sekali per request
if (!isset($GLOBALS['conn']) || !$GLOBALS['conn'] instanceof mysqli) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $GLOBALS['conn'] = $conn;

    date_default_timezone_set('Asia/Jakarta');
    $conn->query("SET time_zone = '+07:00'");
} else {
    $conn = $GLOBALS['conn'];
}

// daftar file yang tidak ditutup koneksinya
$whitelist = [
    'get_alamat_note.php',
    // tambahkan file lain di sini jika perlu
];

$currentFile = basename($_SERVER['PHP_SELF']);

// hanya daftarkan fungsi penutup satu kali dan hanya bila bukan file whitelist
if (!in_array($currentFile, $whitelist) && empty($GLOBALS['conn_closed_registered'])) {
    $GLOBALS['conn_closed_registered'] = true; // penanda agar tidak double
    register_shutdown_function(function () {
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            try {
                @$GLOBALS['conn']->close();
            } catch (Throwable $e) {
                // diamkan error jika sudah tertutup
            }
        }
    });
}
