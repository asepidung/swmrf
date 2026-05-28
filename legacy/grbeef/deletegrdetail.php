<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!isset($_GET['idgr']) || !isset($_GET['idgrdetail'])) {
    die('Error: ID GR atau ID GR Detail tidak ditemukan.');
}

$idgr = intval($_GET['idgr']);
$idgrdetail = intval($_GET['idgrdetail']);
$from = isset($_GET['from']) ? $_GET['from'] : ''; // Ambil nilai 'from' jika tersedia

try {
    // Mulai transaksi
    $conn->autocommit(false);

    // Ambil kdbarcode dari tabel grbeefdetail
    $query_get_barcode = "SELECT kdbarcode FROM grbeefdetail WHERE idgrbeefdetail = ?";
    $stmt_get_barcode = $conn->prepare($query_get_barcode);
    $stmt_get_barcode->bind_param("i", $idgrdetail);
    $stmt_get_barcode->execute();
    $result_get_barcode = $stmt_get_barcode->get_result();

    if ($result_get_barcode->num_rows === 0) {
        throw new Exception("Data GR Detail tidak ditemukan.");
    }

    $row = $result_get_barcode->fetch_assoc();
    $kdbarcode = $row['kdbarcode'];

    // Soft delete di tabel grbeefdetail
    $query_soft_delete = "UPDATE grbeefdetail SET is_deleted = 1 WHERE idgrbeefdetail = ?";
    $stmt_soft_delete = $conn->prepare($query_soft_delete);
    $stmt_soft_delete->bind_param("i", $idgrdetail);

    if (!$stmt_soft_delete->execute()) {
        throw new Exception("Gagal melakukan soft delete pada tabel grbeefdetail.");
    }

    // Hard delete di tabel stock berdasarkan kdbarcode
    $query_hard_delete = "DELETE FROM stock WHERE kdbarcode = ?";
    $stmt_hard_delete = $conn->prepare($query_hard_delete);
    $stmt_hard_delete->bind_param("s", $kdbarcode);

    if (!$stmt_hard_delete->execute()) {
        throw new Exception("Gagal menghapus data pada tabel stock.");
    }

    // Commit transaksi jika semuanya berhasil
    $conn->commit();

    // Redirect berdasarkan nilai 'from'
    if ($from === 'grscan') {
        header("Location: grscan.php?idgr=$idgr");
    } else {
        header("Location: grdetail.php?idgr=$idgr");
    }
    exit();
} catch (Exception $e) {
    // Rollback jika terjadi kesalahan
    $conn->rollback();
    echo "Error: " . $e->getMessage();
} finally {
    $conn->autocommit(true);
    if (isset($stmt_get_barcode)) $stmt_get_barcode->close();
    if (isset($stmt_soft_delete)) $stmt_soft_delete->close();
    if (isset($stmt_hard_delete)) $stmt_hard_delete->close();
    $conn->close();
}
