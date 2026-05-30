<?php
session_start();
header('Content-Type: application/json');

// Jika sesi sudah berakhir, kirim status expired
if (!isset($_SESSION['login'])) {
    echo json_encode(["status" => "expired"]);
    exit();
}

// Cek apakah sesi sudah habis berdasarkan waktu terakhir aktivitas
$inactive_time = time() - ($_SESSION['last_activity'] ?? time());
$timeout = $_SESSION['timeout'] ?? 900;

if ($inactive_time > $timeout) {
    session_unset();
    session_destroy();

    // Hapus cookie sesi agar logout benar-benar terjadi
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    echo json_encode(["status" => "expired"]);
    exit();
}

// Jika sesi masih aktif, kirim status "active"
echo json_encode(["status" => "active"]);
exit();
