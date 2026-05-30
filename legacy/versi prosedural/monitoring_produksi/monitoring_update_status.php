<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$id = $_GET['id'];

// Memperbarui status menjadi In Progress
$sql = "UPDATE monitoring_produksi 
        SET status_qc = 'In Progress', 
            updated_at = NOW() 
        WHERE idmonitoring = '$id'";

if (mysqli_query($conn, $sql)) {
    // Kembali ke halaman utama monitoring
    header("location: index.php");
} else {
    echo "Error updating record: " . mysqli_error($conn);
}
