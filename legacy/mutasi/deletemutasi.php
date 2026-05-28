<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id'])) {
    $idmutasi = $_GET['id'];

    // Mulai transaksi
    $conn->autocommit(false);

    try {
        // Soft delete data mutasi dengan mengubah is_deleted menjadi 1
        $query_soft_delete = "UPDATE mutasi SET is_deleted = 1 WHERE idmutasi = ?";
        $stmt = $conn->prepare($query_soft_delete);
        if (!$stmt) {
            throw new Exception("Error preparing query: " . $conn->error);
        }
        $stmt->bind_param("i", $idmutasi);

        if (!$stmt->execute()) {
            throw new Exception("Error executing query: " . $stmt->error);
        }
        $stmt->close();

        // Commit transaksi
        $conn->commit();

        // Redirect ke halaman index jika berhasil
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi kesalahan
        $conn->rollback();
        echo "<p>Error: " . $e->getMessage() . "</p>";
        echo "<a href='index.php'>Kembali</a>";
        exit();
    } finally {
        // Set autocommit kembali ke true
        $conn->autocommit(true);
        $conn->close();
    }
} else {
    // Redirect jika id tidak ditemukan
    header("Location: index.php");
    exit();
}
