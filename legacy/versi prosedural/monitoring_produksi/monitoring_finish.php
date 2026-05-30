<?php
require "../verifications/auth.php";
require "../konak/conn.php";

/* Mengambil data dari URL */
$id = $_GET['id'];
$note = mysqli_real_escape_string($conn, $_GET['note']);

/* Update status menjadi Passed dan mengisi catatan akhir */
$sql = "UPDATE monitoring_produksi 
        SET status_qc = 'Passed', 
            catatan_qc = '$note',
            updated_at = NOW() 
        WHERE idmonitoring = '$id'";

if (mysqli_query($conn, $sql)) {
    /* Kembali ke halaman index monitoring */
    header("location: index.php");
} else {
    /* Menampilkan error jika query gagal */
    echo "Error updating record: " . mysqli_error($conn);
}
