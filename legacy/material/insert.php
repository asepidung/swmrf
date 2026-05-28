<?php
require "../verifications/auth.php";
require "../konak/conn.php";

/* ================================
   Generate nomor stockout
================================ */
include "stockout_number.php"; // hasilkan $stockout_number

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak diizinkan.");
}

/* ================================
   Ambil & validasi header
================================ */
$idusers  = (int)($_POST['idusers'] ?? ($_SESSION['idusers'] ?? 0));
$tgl      = $_POST['tgl'] ?? date('Y-m-d');
$kegiatan = $_POST['kegiatan'] ?? '';

$ref_no        = trim($_POST['ref_no'] ?? '');
$kegiatan_note = trim($_POST['kegiatan_note'] ?? '');

if (!$idusers) {
    die("User tidak valid.");
}

if (!in_array($kegiatan, ['BONING', 'REPACK', 'LAINNYA'], true)) {
    die("Kegiatan tidak valid.");
}

/* ================================
   Validasi wajib sesuai kegiatan
================================ */
if ($kegiatan === 'BONING' && $ref_no === '') {
    die("No Boning wajib diisi.");
}

if ($kegiatan === 'REPACK' && $ref_no === '') {
    die("No Repack wajib diisi.");
}

if ($kegiatan === 'LAINNYA' && $kegiatan_note === '') {
    die("Kegiatan lainnya wajib diisi.");
}

/* ================================
   Ambil detail material
================================ */
$idrawmate = $_POST['idrawmate'] ?? [];
$qty       = $_POST['qty'] ?? [];
$row_note  = $_POST['row_note'] ?? [];

if (count($idrawmate) === 0) {
    die("Tidak ada material.");
}

/* ================================
   Mulai transaksi
================================ */
$conn->begin_transaction();

try {

    /* ================================
       Insert HEADER
    =============================== */
    $stmtH = $conn->prepare("
        INSERT INTO raw_stock_out
            (nostockout, tgl, kegiatan, ref_no, kegiatan_note, idusers)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");
    $stmtH->bind_param(
        "sssssi",
        $stockout_number,
        $tgl,
        $kegiatan,
        $ref_no,
        $kegiatan_note,
        $idusers
    );
    $stmtH->execute();

    $idstockout = $conn->insert_id;
    $stmtH->close();

    if ($idstockout <= 0) {
        throw new Exception("Gagal menyimpan header.");
    }

    /* ================================
       Insert DETAIL (qty > 0 saja)
    =============================== */
    $stmtD = $conn->prepare("
        INSERT INTO raw_stock_out_detail
            (idstockout, idrawmate, qty, note)
        VALUES
            (?, ?, ?, ?)
    ");

    $adaDetail = false;

    for ($i = 0; $i < count($idrawmate); $i++) {
        $idr = (int)$idrawmate[$i];
        $qv  = (float)($qty[$i] ?? 0);

        if ($idr <= 0 || $qv <= 0) {
            continue;
        }

        $noteRow = trim($row_note[$i] ?? '');

        $stmtD->bind_param(
            "iids",
            $idstockout,
            $idr,
            $qv,
            $noteRow
        );
        $stmtD->execute();
        $adaDetail = true;
    }

    $stmtD->close();

    if (!$adaDetail) {
        throw new Exception("Tidak ada material dengan qty > 0.");
    }

    /* ================================
       Commit
    =============================== */
    $conn->commit();

    header("Location: index.php?msg=" . urlencode("Pengeluaran material berhasil disimpan."));
    exit;
} catch (Exception $e) {

    $conn->rollback();
    die("Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES));
}
