<?php
require "../verifications/auth.php";
require "../konak/conn.php";

session_start();

function backWithError($errors, $old, $idreceive)
{
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old']    = $old;
    header("Location: edit.php?id=" . (int)$idreceive);
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// ================= CSRF =================
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    backWithError(['Invalid CSRF token.'], $_POST, (int)($_POST['idreceive'] ?? 0));
}

// ================= HEADER INPUT =================
$idreceive    = (int)($_POST['idreceive'] ?? 0);
$idpo         = (int)($_POST['idpo'] ?? 0);
$receipt_date = trim($_POST['receipt_date'] ?? '');
$doc_no       = trim($_POST['doc_no'] ?? '');
$sv_ok        = (!empty($_POST['sv_ok']) && $_POST['sv_ok'] == '1') ? 1 : 0;
$skkh_ok      = (!empty($_POST['skkh_ok']) && $_POST['skkh_ok'] == '1') ? 1 : 0;
$note         = trim($_POST['note'] ?? '');
$iduser       = isset($_SESSION['idusers']) ? (int)$_SESSION['idusers'] : null;

// ================= VALIDASI HEADER =================
if ($idreceive <= 0) $errors[] = "Receive id tidak valid.";
if ($idpo <= 0)      $errors[] = "PO tidak valid.";

if ($receipt_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $receipt_date)) {
    $errors[] = "Receipt Date tidak valid (YYYY-MM-DD).";
}
if ($doc_no !== '' && mb_strlen($doc_no) > 50) {
    $errors[] = "Doc No maksimal 50 karakter.";
}
if (mb_strlen($note) > 255) {
    $errors[] = "Note maksimal 255 karakter.";
}

// ================= CEK RECEIVE =================
if (empty($errors)) {
    $cek = $conn->prepare("SELECT idpo FROM cattle_receive WHERE idreceive=? AND is_deleted=0 LIMIT 1");
    $cek->bind_param("i", $idreceive);
    $cek->execute();
    $row = $cek->get_result()->fetch_assoc();

    if (!$row) {
        $errors[] = "Receive tidak ditemukan.";
    } elseif ((int)$row['idpo'] !== $idpo) {
        $errors[] = "Data tidak konsisten (idpo).";
    }
}

// ================= DETAIL INPUT =================
$class  = $_POST['class']  ?? [];
$eartag = $_POST['eartag'] ?? [];
$weight = $_POST['weight'] ?? [];
$notes  = $_POST['notes']  ?? [];

$rows = [];
$dupe = [];

$cnt = max(count($class), count($eartag), count($weight), count($notes));
for ($i = 0; $i < $cnt; $i++) {

    $c  = trim($class[$i] ?? '');
    $et = trim($eartag[$i] ?? '');
    $wt_raw = trim((string)($weight[$i] ?? ''));
    $nt = trim($notes[$i] ?? '');

    // skip baris kosong total
    if ($c === '' && $et === '' && $wt_raw === '' && $nt === '') continue;

    if ($c === '') {
        $errors[] = "Class baris #" . ($i + 1) . " wajib.";
    }

    if ($et === '') {
        $errors[] = "Eartag baris #" . ($i + 1) . " wajib.";
    }

    $et_norm = strtoupper($et);
    $wt_norm = str_replace(',', '.', $wt_raw);

    if ($wt_norm === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $wt_norm) || (float)$wt_norm < 0) {
        $errors[] = "Weight baris #" . ($i + 1) . " harus angka >= 0.";
    }

    if (mb_strlen($nt) > 255) {
        $errors[] = "Notes baris #" . ($i + 1) . " maksimal 255 karakter.";
    }

    // cek duplikat di form
    $key = strtolower($et_norm);
    if (isset($dupe[$key])) {
        $errors[] = "Eartag duplikat di baris #" . $dupe[$key] . " dan #" . ($i + 1);
    } else {
        $dupe[$key] = $i + 1;
    }

    $rows[] = [
        'class'  => $c,                 // SIMPAN APA ADANYA
        'eartag' => $et_norm,
        'weight' => round((float)$wt_norm, 2),
        'notes'  => $nt,
    ];
}

if (empty($rows)) {
    $errors[] = "Minimal satu baris detail harus diisi.";
}

if (!empty($errors)) {
    backWithError($errors, $_POST, $idreceive);
}

// ================= DB DUPLICATE CHECK =================
$tags = [];
foreach ($rows as $r) {
    $tags[$r['eartag']] = $r['eartag'];
}
$tags = array_values($tags);

if (!empty($tags)) {
    $ph = implode(',', array_fill(0, count($tags), '?'));
    $types = str_repeat('s', count($tags)) . 'i';

    $sql = "
        SELECT d.eartag, r.idreceive, r.receipt_date
        FROM cattle_receive_detail d
        JOIN cattle_receive r ON r.idreceive = d.idreceive
        WHERE r.is_deleted = 0
          AND d.eartag IN ($ph)
          AND d.idreceive <> ?
    ";

    $stmt = $conn->prepare($sql);
    $bind = [];
    $bind[] = $types;

    foreach ($tags as $k => $v) {
        $bind[] = &$tags[$k];
    }
    $bind[] = &$idreceive;

    call_user_func_array([$stmt, 'bind_param'], $bind);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($r = $res->fetch_assoc()) {
        $errors[] = "Eartag '{$r['eartag']}' sudah ada (receipt {$r['idreceive']}, {$r['receipt_date']})";
    }

    if (!empty($errors)) {
        backWithError($errors, $_POST, $idreceive);
    }
}

// ================= SIMPAN =================
try {
    $conn->begin_transaction();

    // update header
    $up = $conn->prepare("
        UPDATE cattle_receive
           SET receipt_date=?,
               doc_no=?,
               sv_ok=?,
               skkh_ok=?,
               note=?,
               updatetime=NOW(),
               updateby=?
         WHERE idreceive=? AND is_deleted=0
    ");
    $up->bind_param(
        "ssiisii",
        $receipt_date,
        $doc_no,
        $sv_ok,
        $skkh_ok,
        $note,
        $iduser,
        $idreceive
    );
    $up->execute();

    // hapus detail lama
    $del = $conn->prepare("DELETE FROM cattle_receive_detail WHERE idreceive=?");
    $del->bind_param("i", $idreceive);
    $del->execute();

    // insert ulang detail
    $ins = $conn->prepare("
        INSERT INTO cattle_receive_detail
        (idreceive, eartag, weight, class, notes, creatime, createby)
        VALUES (?,?,?,?,?,NOW(),?)
    ");

    foreach ($rows as $r) {
        $ins->bind_param(
            "isdssi",
            $idreceive,
            $r['eartag'],
            $r['weight'],
            $r['class'],
            $r['notes'],
            $iduser
        );
        $ins->execute();
    }

    $conn->commit();
    header("Location: view.php?id=" . $idreceive . "&msg=updated");
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    backWithError(['Transaksi gagal: ' . $e->getMessage()], $_POST, $idreceive);
}
