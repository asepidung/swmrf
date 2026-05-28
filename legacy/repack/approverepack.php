<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Pastikan idrepack diterima
if (isset($_GET['id'])) {
    $idrepack = $_GET['id'];

    // Cek apakah repack sudah terkunci atau sudah diapprove
    $query = "SELECT kunci FROM repack WHERE idrepack = $idrepack";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);

    // Jika repack belum terkunci atau belum diapprove, update kunci menjadi 1
    if ($row['kunci'] == 0) {
        $queryUpdate = "UPDATE repack SET kunci = 1 WHERE idrepack = $idrepack";
        mysqli_query($conn, $queryUpdate);
    }

    // Setelah approve, arahkan kembali ke halaman repack
    header("Location: index.php");
    exit();
} else {
    echo "Error: No ID specified.";
}
