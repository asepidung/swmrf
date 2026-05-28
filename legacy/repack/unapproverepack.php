<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Pastikan idrepack diterima
if (isset($_GET['id'])) {
    $idrepack = $_GET['id'];

    // Cek apakah repack sudah diapprove (kunci = 1)
    $query = "SELECT kunci FROM repack WHERE idrepack = $idrepack";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);

    // Jika repack sudah diapprove (kunci = 1), update kunci menjadi 0
    if ($row['kunci'] == 1) {
        $queryUpdate = "UPDATE repack SET kunci = 0 WHERE idrepack = $idrepack";
        mysqli_query($conn, $queryUpdate);
    }

    // Setelah unapprove, arahkan kembali ke halaman repack
    header("Location: index.php");
    exit();
} else {
    echo "Error: No ID specified.";
}
