<?php
require "../verifications/auth.php";
require "../konak/conn.php";

session_start();

function backWithError($errors, $old)
{
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old']    = $old;
    header("Location: newpocattle.php");
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: newpocattle.php");
    exit;
}

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    backWithError(['Invalid CSRF token.'], $_POST);
}

/**
 * ===== NOMOR PO =====
 */
ob_start();
include "ponumber.php";   // harus set $nopocattle
ob_end_clean();

if (empty($nopocattle)) {
    backWithError(['Nomor PO gagal dibuat.'], $_POST);
}

// ===== INPUT HEADER =====
$podate       = trim($_POST['podate'] ?? '');
$arrival_date = trim($_POST['arrival_date'] ?? '');
$idsupplier   = $_POST['idsupplier'] ?? '';
$note         = trim($_POST['note'] ?? '');
$iduser       = $_SESSION['idusers'] ?? null;

// ===== INPUT DETAIL =====
$class  = $_POST['class']  ?? [];
$qty    = $_POST['qty']    ?? [];
$price  = $_POST['price']  ?? [];
$notes  = $_POST['notes']  ?? [];

// ===== VALIDASI HEADER =====
if ($podate === '') $errors[] = "Tanggal PO wajib.";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $podate)) {
    $errors[] = "Format PO Date tidak valid (YYYY-MM-DD).";
}
if ($arrival_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival_date)) {
    $errors[] = "Format Arrival Date tidak valid.";
}
if ($idsupplier === '' || !ctype_digit((string)$idsupplier)) {
    $errors[] = "Supplier wajib.";
}

/* =========================
   AMBIL MASTER CLASS
========================= */
$classMaster = [];
$qm = $conn->query("SELECT class_name FROM cattle_class");
if ($qm) {
    while ($r = $qm->fetch_assoc()) {
        $classMaster[$r['class_name']] = true;
    }
}
if (empty($classMaster)) {
    $errors[] = "Master cattle class belum tersedia.";
}

/* =========================
   VALIDASI DETAIL
========================= */
$rows = [];
for ($i = 0; $i < count($class); $i++) {
    $c  = trim($class[$i] ?? '');
    $qv = (string)($qty[$i] ?? '');
    $pv = trim((string)($price[$i] ?? ''));
    $nt = trim($notes[$i] ?? '');

    // lewati baris kosong total
    if ($c === '' && $qv === '' && $pv === '' && $nt === '') continue;

    // blokir opsi "Tambah Class Baru"
    if ($c === '__NEW__') {
        $errors[] = "Cattle Class baris #" . ($i + 1) . " tidak valid.";
        continue;
    }

    if ($c === '') {
        $errors[] = "Cattle Class baris #" . ($i + 1) . " wajib.";
    } elseif (!isset($classMaster[$c])) {
        $errors[] = "Cattle Class baris #" . ($i + 1) . " tidak terdaftar.";
    }

    if ($qv === '' || !ctype_digit($qv) || (int)$qv <= 0) {
        $errors[] = "Qty baris #" . ($i + 1) . " harus angka bulat > 0.";
    }

    if ($pv !== '' && !preg_match('/^\d+(\.\d{1,2})?$/', $pv)) {
        $errors[] = "Price baris #" . ($i + 1) . " tidak valid (max 2 desimal).";
    }

    $rows[] = [
        'class' => $c,
        'qty'   => (int)$qv,
        'price' => $pv,
        'notes' => $nt,
    ];
}

if (empty($rows)) {
    $errors[] = "Minimal satu baris detail harus diisi.";
}

/* =========================
   CEK DUPLIKAT NOPO
========================= */
if (empty($errors)) {
    $stmt = $conn->prepare("SELECT 1 FROM pocattle WHERE nopo=? LIMIT 1");
    $stmt->bind_param('s', $nopocattle);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "No. PO duplikat. Silakan submit ulang.";
    }
    $stmt->close();
}

if (!empty($errors)) {
    backWithError($errors, $_POST);
}

/* =========================
   SIMPAN (TRANSAKSI)
========================= */
try {
    $conn->begin_transaction();

    $arrivalDB = ($arrival_date === '' ? null : $arrival_date);

    // HEADER
    $sqlH = "INSERT INTO pocattle
             (nopo, podate, arrival_date, idsupplier, note, creatime, createby)
             VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmtH = $conn->prepare($sqlH);
    $stmtH->bind_param('sssisi', $nopocattle, $podate, $arrivalDB, $idsupplier, $note, $iduser);
    if (!$stmtH->execute()) {
        throw new Exception($stmtH->error);
    }
    $idpo = $stmtH->insert_id;
    $stmtH->close();

    // DETAIL
    $sqlD = "INSERT INTO pocattledetail
             (idpo, class, qty, price, notes, creatime, createby)
             VALUES (?, ?, ?, NULLIF(? COLLATE utf8mb4_unicode_ci, '' COLLATE utf8mb4_unicode_ci), ?, NOW(), ?)";
    $stmtD = $conn->prepare($sqlD);

    foreach ($rows as $r) {
        $stmtD->bind_param(
            'isissi',
            $idpo,
            $r['class'],
            $r['qty'],
            $r['price'],
            $r['notes'],
            $iduser
        );
        if (!$stmtD->execute()) {
            throw new Exception($stmtD->error);
        }
    }

    $stmtD->close();
    $conn->commit();
    header("Location: index.php?msg=created");
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    backWithError(['Transaksi gagal: ' . $ex->getMessage()], $_POST);
}
