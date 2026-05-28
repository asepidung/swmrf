<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "kdlabel.php"; // menghasilkan $kodeauto (barcode baru)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// ------- helper: normalisasi tanggal ke YYYY-MM-DD -------
function normalize_date_or_fail(string $raw): string
{
    $raw = trim($raw);
    // sudah format yyyy-mm-dd?
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }
    // coba beberapa format populer
    $formats = ['m/d/Y', 'd/m/Y', 'd-m-Y', 'm-d-Y', 'Y/m/d'];
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat('!' . $fmt, $raw);
        if ($dt && $dt->format($fmt) === $raw) {
            return $dt->format('Y-m-d');
        }
    }
    // fallback: kalau user cuma isi tahun/bulan dsb -> dianggap invalid
    exit("Tanggal POD tidak valid. Gunakan format YYYY-MM-DD atau MM/DD/YYYY.");
}

// ---------- Ambil & validasi input ----------
$idusers  = (int)($_SESSION['idusers'] ?? 0);
$idbarang = isset($_POST['idbarang']) ? (int)$_POST['idbarang'] : 0;
$idgrade  = isset($_POST['idgrade'])  ? (int)$_POST['idgrade']  : 0;
$podRaw   = $_POST['pod'] ?? '';
$qtyRaw   = trim($_POST['qty'] ?? '');       // "12.34" atau "12.34/5"
$ph_input = trim($_POST['ph'] ?? '');        // opsional

if ($idusers <= 0 || $idbarang <= 0 || $idgrade <= 0 || $podRaw === '' || $qtyRaw === '') {
    exit('Data tidak boleh kosong.');
}

// normalisasi tanggal POD
$pod = normalize_date_or_fail($podRaw);

// ---------- Parse qty & pcs gabungan ----------
$qty = null;
$pcs = null;
if (preg_match('~^\s*([0-9.,]+)\s*(?:/\s*(\d+))?\s*$~', $qtyRaw, $m)) {
    $qtyNorm = str_replace(',', '.', $m[1]);
    $qty     = (float)$qtyNorm;
    $pcs     = isset($m[2]) ? (int)$m[2] : null;
} else {
    exit('Format Qty tidak valid. Contoh: 12.34 atau 12.34/5');
}
if ($qty <= 0) exit('Quantity harus > 0');
// pastikan 2 desimal
$qty = (float)number_format($qty, 2, '.', '');

// ---------- Normalisasi & validasi pH (opsional) ----------
$phFloat = null;
if ($ph_input !== '') {
    $rawPh = str_replace(',', '.', $ph_input);
    $phVal = filter_var($rawPh, FILTER_VALIDATE_FLOAT);
    if ($phVal === false) exit('Nilai pH tidak valid.');
    if ($phVal < 5.4 || $phVal > 5.7) exit('Nilai pH harus antara 5.4 dan 5.7.');
    // truncate 1 desimal (bukan pembulatan)
    $phFloat = floor($phVal * 10) / 10;
}

// ---------- Origin (default, tidak ada di form) ----------
$origin    = 7;
$kdbarcode = $kodeauto; // dari kdlabel.php

// ---------- Insert ke stockin ----------
$sqlSi = "INSERT INTO stockin
            (kdbarcode, idgrade, idbarang, qty, ph, pcs, pod, origin)
          VALUES
            (?,         ?,       ?,        ?,   ?,  ?,   ?,   ?)";
$stmtSi = $conn->prepare($sqlSi);
if (!$stmtSi) exit('Gagal prepare stockin: ' . $conn->error);

// bind types: s i i d d i s i
$phParam  = is_null($phFloat) ? null : (float)number_format($phFloat, 1, '.', '');
$pcsParam = is_null($pcs) ? null : (int)$pcs;

if (!$stmtSi->bind_param(
    'siididsi',
    $kdbarcode,   // s
    $idgrade,     // i
    $idbarang,    // i
    $qty,         // d
    $phParam,     // d (nullable)
    $pcsParam,    // i (nullable)
    $pod,         // s (YYYY-MM-DD)
    $origin       // i
)) {
    exit('Gagal bind stockin: ' . $stmtSi->error);
}

if (!$stmtSi->execute()) {
    exit('Gagal insert stockin: ' . $stmtSi->error);
}
$idstockin = $stmtSi->insert_id ?: $conn->insert_id;
$stmtSi->close();

// ---------- Insert ke stock ----------
$sqlSt = "INSERT INTO stock
            (kdbarcode, idgrade, idbarang, qty, pcs, ph, pod, origin)
          VALUES
            (?,         ?,       ?,        ?,   ?,   ?,  ?,   ?)";
$stmtSt = $conn->prepare($sqlSt);
if (!$stmtSt) exit('Gagal prepare stock: ' . $conn->error);

// bind types: s i i d i d s i
$phParam2  = is_null($phFloat) ? null : (float)number_format($phFloat, 1, '.', '');
$pcsParam2 = is_null($pcs) ? null : (int)$pcs;

if (!$stmtSt->bind_param(
    'siididsi',
    $kdbarcode,   // s
    $idgrade,     // i
    $idbarang,    // i
    $qty,         // d
    $pcsParam2,   // i (nullable)
    $phParam2,    // d (nullable)
    $pod,         // s (YYYY-MM-DD)
    $origin       // i
)) {
    exit('Gagal bind stock: ' . $stmtSt->error);
}

if (!$stmtSt->execute()) {
    exit('Gagal insert stock: ' . $stmtSt->error);
}
$stmtSt->close();

// ---------- Redirect cetak ----------
header("Location: print_labelstockin.php?idstockin=" . (int)$idstockin);
exit;
