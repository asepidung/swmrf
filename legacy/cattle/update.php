<?php
require "../verifications/auth.php";
require "../konak/conn.php";

session_start();

function backWithError($errors, $old, $idpo)
{
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old']    = $old;
    header("Location: editpocattle.php?id=" . (int)$idpo);
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    backWithError(['Invalid CSRF token.'], $_POST, (int)($_POST['idpo'] ?? 0));
}

$idpo = (int)($_POST['idpo'] ?? 0);
if ($idpo <= 0) backWithError(['PO id tidak valid.'], $_POST, $idpo);

// Pastikan PO ada
$cek = $conn->prepare("SELECT idpo FROM pocattle WHERE idpo=? AND is_deleted=0 LIMIT 1");
$cek->bind_param("i", $idpo);
$cek->execute();
if (!$cek->get_result()->fetch_row()) {
    backWithError(['PO tidak ditemukan.'], $_POST, $idpo);
}
$cek->close();

/* =========================
   INPUT HEADER
========================= */
$podate       = trim($_POST['podate'] ?? '');
$arrival_date = trim($_POST['arrival_date'] ?? '');
$idsupplier   = $_POST['idsupplier'] ?? '';
$note         = trim($_POST['note'] ?? '');
$iduser       = $_SESSION['idusers'] ?? null;

/* =========================
   INPUT DETAIL
========================= */
$class  = $_POST['class']  ?? [];
$qty    = $_POST['qty']    ?? [];
$price  = $_POST['price']  ?? [];
$notes  = $_POST['notes']  ?? [];

/* =========================
   VALIDASI HEADER
========================= */
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
        'notes' => $nt
    ];
}

if (empty($rows)) {
    $errors[] = "Minimal satu baris detail harus diisi.";
}

if (!empty($errors)) {
    backWithError($errors, $_POST, $idpo);
}

/* =========================
   SIMPAN (TRANSAKSI)
========================= */
try {
    $conn->begin_transaction();

    // UPDATE HEADER
    $arrivalDB = ($arrival_date === '' ? null : $arrival_date);
    $up = $conn->prepare("
        UPDATE pocattle
           SET podate=?,
               arrival_date=?,
               idsupplier=?,
               note=?,
               updatetime=NOW(),
               updateby=?
         WHERE idpo=? AND is_deleted=0
    ");
    if ($up === false) throw new Exception($conn->error);

    $up->bind_param('ssisii', $podate, $arrivalDB, $idsupplier, $note, $iduser, $idpo);
    if (!$up->execute()) throw new Exception($up->error);
    $up->close();

    // HAPUS DETAIL LAMA
    $del = $conn->prepare("DELETE FROM pocattledetail WHERE idpo=?");
    if ($del === false) throw new Exception($conn->error);
    $del->bind_param("i", $idpo);
    if (!$del->execute()) throw new Exception($del->error);
    $del->close();

    // INSERT DETAIL BARU
    $ins = $conn->prepare("
        INSERT INTO pocattledetail
        (idpo, class, qty, price, notes, creatime, createby)
        VALUES (?, ?, ?, NULLIF(? COLLATE utf8mb4_unicode_ci, '' COLLATE utf8mb4_unicode_ci), ?, NOW(), ?)
    ");
    if ($ins === false) throw new Exception($conn->error);

    foreach ($rows as $r) {
        $ins->bind_param(
            'isissi',
            $idpo,
            $r['class'],
            $r['qty'],
            $r['price'],
            $r['notes'],
            $iduser
        );
        if (!$ins->execute()) throw new Exception($ins->error);
    }
    $ins->close();

    $conn->commit();
    header("Location: view.php?id=" . $idpo);
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    backWithError(['Transaksi gagal: ' . $ex->getMessage()], $_POST, $idpo);
}
