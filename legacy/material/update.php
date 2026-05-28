<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak diizinkan.");
}

/* ================================
   Ambil data utama
================================ */
$idstockout = (int)($_POST['idstockout'] ?? 0);
$idusers    = (int)($_POST['idusers'] ?? ($_SESSION['idusers'] ?? 0));

$tgl        = $_POST['tgl'] ?? date('Y-m-d');
$kegiatan   = $_POST['kegiatan'] ?? '';
$ref_no     = trim($_POST['ref_no'] ?? '');
$keg_note   = trim($_POST['kegiatan_note'] ?? '');

if ($idstockout <= 0) {
    die("ID dokumen tidak valid.");
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
if ($kegiatan === 'LAINNYA' && $keg_note === '') {
    die("Kegiatan lainnya wajib diisi.");
}

/* ================================
   Ambil detail dari form
================================ */
$idrawmate = $_POST['idrawmate'] ?? [];
$qty       = $_POST['qty'] ?? [];
$row_note  = $_POST['row_note'] ?? [];

if (count($idrawmate) === 0) {
    die("Tidak ada data material.");
}

/* ================================
   Mulai transaksi
================================ */
$conn->begin_transaction();

try {

    /* ================================
       Update HEADER
    =============================== */
    $stmtH = $conn->prepare("
        UPDATE raw_stock_out
        SET
            tgl = ?,
            kegiatan = ?,
            ref_no = ?,
            kegiatan_note = ?,
            idusers = ?
        WHERE idstockout = ? AND is_deleted = 0
    ");
    $stmtH->bind_param(
        "ssssii",
        $tgl,
        $kegiatan,
        $ref_no,
        $keg_note,
        $idusers,
        $idstockout
    );
    $stmtH->execute();
    $stmtH->close();

    /* ================================
       Hapus DETAIL lama
    =============================== */
    $stmtDel = $conn->prepare("
        DELETE FROM raw_stock_out_detail
        WHERE idstockout = ?
    ");
    $stmtDel->bind_param("i", $idstockout);
    $stmtDel->execute();
    $stmtDel->close();

    /* ================================
       Insert DETAIL baru (qty > 0)
    =============================== */
    $stmtIns = $conn->prepare("
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

        $stmtIns->bind_param(
            "iids",
            $idstockout,
            $idr,
            $qv,
            $noteRow
        );
        $stmtIns->execute();
        $adaDetail = true;
    }

    $stmtIns->close();

    if (!$adaDetail) {
        throw new Exception("Minimal satu material harus memiliki qty > 0.");
    }

    /* ================================
       Commit
    =============================== */
    $conn->commit();

    header("Location: index.php?msg=" . urlencode("Pengeluaran material berhasil diperbarui."));
    exit;
} catch (Exception $e) {

    $conn->rollback();
    die("Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES));
}
