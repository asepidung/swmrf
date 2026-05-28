<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Opsional: aktifkan error mysqli saat dev
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

// Ambil id dari GET
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    // kalau param nggak valid, balik saja ke index
    header("Location: index.php");
    exit;
}

$idcarcase = (int)$_GET['id'];

// (opsional) bisa cek user login
$idusers = (int)($_SESSION['idusers'] ?? 0);
if ($idusers <= 0) {
    die("Session user tidak valid.");
}

try {
    // Mulai transaksi supaya hard delete bersifat atomik
    $conn->begin_transaction();

    // 1) Hapus semua detail terkait di carcasedetail
    $stmtDetail = $conn->prepare("
        DELETE FROM carcasedetail
        WHERE idcarcase = ?
    ");
    if (!$stmtDetail) {
        throw new Exception("Gagal menyiapkan statement detail: " . $conn->error);
    }
    $stmtDetail->bind_param("i", $idcarcase);
    $stmtDetail->execute();
    // (opsional) $deletedDetails = $stmtDetail->affected_rows;
    $stmtDetail->close();

    // 2) Hapus data utama di carcase
    $stmtCarcase = $conn->prepare("
        DELETE FROM carcase
        WHERE idcarcase = ?
    ");
    if (!$stmtCarcase) {
        throw new Exception("Gagal menyiapkan statement carcase: " . $conn->error);
    }
    $stmtCarcase->bind_param("i", $idcarcase);
    $stmtCarcase->execute();

    // Pastikan baris carcase memang ada dan dihapus
    if ($stmtCarcase->affected_rows === 0) {
        // rollback sebelum lempar error
        $stmtCarcase->close();
        $conn->rollback();
        throw new Exception("Data carcase dengan id {$idcarcase} tidak ditemukan (atau sudah dihapus).");
    }

    $stmtCarcase->close();

    // Commit transaksi jika semua sukses
    $conn->commit();

    // Selesai, kembali ke index
    header("Location: index.php");
    exit;
} catch (Exception $ex) {
    // Jika terjadi error, rollback dan tampilkan pesan sederhana
    if ($conn->connect_errno === 0) {
        // Jika koneksi masih valid, pastikan rollback
        try {
            $conn->rollback();
        } catch (Exception $e) {
            // ignore
        }
    }
    die("Terjadi kesalahan saat menghapus data: " . e($ex->getMessage()));
}
