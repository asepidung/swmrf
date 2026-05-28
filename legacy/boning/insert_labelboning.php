<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "seriallabelboning.php"; // harus mengisi $kodeauto

// Pastikan koneksi dan session tersedia
if (!isset($conn) || !$conn) {
    die("Database connection not available.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

// -----------------------------
// 1) Validasi multi-token (single-use) dari session array
// -----------------------------
$pt = $_POST['form_token'] ?? '';

if (
    empty($pt)
    || !isset($_SESSION['label_form_tokens'])
    || !is_array($_SESSION['label_form_tokens'])
    || !isset($_SESSION['label_form_tokens'][$pt])
) {
    die('Form token tidak valid atau sudah digunakan.');
}

// Token valid -> hapus (single-use)
unset($_SESSION['label_form_tokens'][$pt]);

// Cleanup token lama (> 5 menit) dan batasi jumlah (max 20)
$now = time();
if (isset($_SESSION['label_form_tokens']) && is_array($_SESSION['label_form_tokens'])) {
    foreach ($_SESSION['label_form_tokens'] as $k => $ts) {
        if ($now - (int)$ts > 300) {
            unset($_SESSION['label_form_tokens'][$k]);
        }
    }
    if (count($_SESSION['label_form_tokens']) > 20) {
        asort($_SESSION['label_form_tokens']);
        while (count($_SESSION['label_form_tokens']) > 20) {
            array_shift($_SESSION['label_form_tokens']);
        }
    }
}

// -----------------------------
// 2) Ambil & sanitasi input
// -----------------------------
$idusers  = (int)($_SESSION['idusers'] ?? 0);
$idbarang = (int)($_POST['idbarang'] ?? 0);
$idgrade  = (int)($_POST['idgrade'] ?? 0);
$packdate = trim($_POST['packdate'] ?? '');
$idboning = (int)($_POST['idboning'] ?? 0);
$qtyPcsInput = trim($_POST['qty'] ?? '');

// Ambil status checkbox Print Exp
$print_exp = isset($_POST['print_exp']) ? 1 : 0;

// cek input minimal
if ($idusers <= 0 || $idboning <= 0 || $idbarang <= 0 || $idgrade <= 0) {
    die('Input tidak lengkap.');
}
if ($packdate === '') {
    die('Tanggal pack belum diisi.');
}

// --- LOGIC PERHITUNGAN EXPIRED DATE ---
$exp_val = "NULL"; // Default ke NULL jika checkbox tidak dicentang

if ($print_exp === 1) {
    $dateObj = new DateTime($packdate);
    // Jika Grade J01 (id 1) atau P01 (id 3) = 3 Bulan
    if ($idgrade === 1 || $idgrade === 3) {
        $dateObj->modify('+3 months');
    } else {
        // Selain itu = 1 Tahun
        $dateObj->modify('+1 year');
    }
    // Siapkan format SQL
    $exp_val = "'" . $conn->real_escape_string($dateObj->format('Y-m-d')) . "'";
}
// ---------------------------------------

// PH
$ph_raw = filter_input(INPUT_POST, 'ph', FILTER_VALIDATE_FLOAT);
if ($ph_raw === false || $ph_raw < 5.4 || $ph_raw > 5.7) {
    die('Nilai PH harus antara 5.4 dan 5.7');
}
$ph = number_format($ph_raw, 1, '.', '');

// Pecah qty/pcs
$qty = 0.0;
$pcs = null;
if (strpos($qtyPcsInput, "/") !== false) {
    list($qtyStr, $pcsStr) = explode("/", $qtyPcsInput, 2);
    $qty = (float)str_replace(',', '.', trim($qtyStr));
    $pcs = trim($pcsStr) === '' ? null : (int)$pcsStr;
} else {
    $qty = (float)str_replace(',', '.', $qtyPcsInput);
    $pcs = null;
}
$qty = number_format($qty, 2, '.', '');

// pcs SQL literal
$pcs_sql = ($pcs === null) ? "NULL" : (int)$pcs;

// Escape strings
$packdate_esc = $conn->real_escape_string($packdate);
$ph_esc = $conn->real_escape_string($ph);

// -----------------------------
// 3) Server-side dedupe (identik dalam 5 detik)
// -----------------------------
$existsId = null;
$sqlDedupe = "
    SELECT idlabelboning
    FROM labelboning
    WHERE idboning = " . (int)$idboning . "
      AND idbarang = " . (int)$idbarang . "
      AND idgrade  = " . (int)$idgrade . "
      AND qty = " . $conn->real_escape_string($qty) . "
      AND packdate = '" . $packdate_esc . "'
      AND ph = " . $ph_esc . "
      AND is_deleted = 0
      AND creatime >= (NOW() - INTERVAL 5 SECOND)
    LIMIT 1
";
if ($pcs === null) {
    $sqlDedupe = str_replace("LIMIT 1", "AND pcs IS NULL LIMIT 1", $sqlDedupe);
} else {
    $sqlDedupe = str_replace("LIMIT 1", "AND pcs = " . (int)$pcs . " LIMIT 1", $sqlDedupe);
}

$resD = $conn->query($sqlDedupe);
$rowD = null;
if ($resD) {
    if ($rowTmp = $resD->fetch_assoc()) {
        $rowD = $rowTmp; // simpan row matched untuk referensi
        $existsId = (int)$rowD['idlabelboning'];
    }
    $resD->free();
}

if ($existsId !== null && $existsId > 0) {
    // --- DEBUG LOG: ketika dedupe trigger ---
    $dbg = [
        'ts' => date('c'),
        'event' => 'dedupe_trigger',
        'user_id' => $_SESSION['idusers'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'submitted' => [
            'idboning' => $idboning,
            'idbarang' => $idbarang,
            'idgrade' => $idgrade,
            'qty' => $qty,
            'pcs' => $pcs,
            'ph' => $ph,
            'packdate' => $packdate,
            'raw_post' => $_POST
        ],
        'matched_id' => $existsId
    ];

    if ($rowD) {
        $dbg['matched_row'] = $rowD;
    } else {
        $resMatched = $conn->query("SELECT idlabelboning, idbarang, idgrade, qty, pcs, ph, kdbarcode, creatime, iduser FROM labelboning WHERE idlabelboning = " . (int)$existsId . " LIMIT 1");
        if ($resMatched && $r = $resMatched->fetch_assoc()) {
            $dbg['matched_row'] = $r;
            $resMatched->free();
        }
    }

    error_log("DEDUP_LABELBONING: " . json_encode($dbg));

    header("Location: print_labelboning.php?idlabelboning={$existsId}&idboning={$idboning}");
    exit;
}

// -----------------------------
// 4) Prepare kdbarcode via $kodeauto (seriallabelboning.php must set it)
// -----------------------------
if (empty($kodeauto)) {
    $kodeauto = date('ymd') . date('His') . mt_rand(100, 999);
}
// Susun kdbarcode
$kdbarcode = "1" . $idboning . $kodeauto;
$kdbarcode_esc = $conn->real_escape_string($kdbarcode);

// -----------------------------
// 5) Insert dalam transaksi
// -----------------------------
$conn->begin_transaction();

try {
    // FIX: Menambahkan kolom `exp` ke dalam query INSERT
    $queryInsertLabel = "
        INSERT INTO labelboning
            (idboning, idbarang, qty, pcs, packdate, exp, kdbarcode, iduser, idgrade, ph, creatime)
        VALUES
            (" . (int)$idboning . ",
             " . (int)$idbarang . ",
             " . $conn->real_escape_string($qty) . ",
             " . $pcs_sql . ",
             '" . $packdate_esc . "',
             " . $exp_val . ",
             '" . $kdbarcode_esc . "',
             " . (int)$idusers . ",
             " . (int)$idgrade . ",
             " . $ph_esc . ",
             NOW()
            )
    ";

    if (!$conn->query($queryInsertLabel)) {
        $err = $conn->error;

        $dbgErr = [
            'ts' => date('c'),
            'event' => 'insert_label_failed',
            'error' => $err,
            'user_id' => $_SESSION['idusers'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'kdbarcode' => $kdbarcode,
            'idboning' => $idboning,
            'payload' => [
                'idbarang' => $idbarang,
                'idgrade' => $idgrade,
                'qty' => $qty,
                'pcs' => $pcs,
                'ph' => $ph,
                'packdate' => $packdate,
                'raw_post' => $_POST
            ]
        ];
        error_log("INSERT_LABEL_ERROR: " . json_encode($dbgErr));

        $conn->rollback();
        if (stripos($err, 'Duplicate') !== false) {
            error_log("insert_labelboning: Duplicate kdbarcode '{$kdbarcode_esc}' - " . $err);
            die('Gagal menyimpan label: kode barcode bentrok. Silakan coba lagi.');
        }
        die('Gagal simpan labelboning: ' . $err);
    }

    $idlabelboning = (int)$conn->insert_id;

    // Insert stock (jika tabel stock tidak punya kolom ph, ubah sesuai DB)
    $queryInsertStock = "
        INSERT INTO stock (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin, ph)
        VALUES ('" . $kdbarcode_esc . "', " . (int)$idgrade . ", " . (int)$idbarang . ",
                " . $conn->real_escape_string($qty) . ", " . $pcs_sql . ",
                '" . $packdate_esc . "', 1, " . $ph_esc . ")
    ";

    if (!$conn->query($queryInsertStock)) {
        $err = $conn->error;
        $conn->rollback();
        die('Gagal simpan stock: ' . $err);
    }

    // commit
    $conn->commit();

    // Simpan ke session convenience
    $_SESSION['idbarang'] = $_POST['idbarang'] ?? '';
    $_SESSION['idgrade']  = $_POST['idgrade'] ?? '';
    $_SESSION['packdate'] = $_POST['packdate'] ?? '';
    $_SESSION['ph']       = $ph;
    $_SESSION['print_exp'] = $print_exp;

    // Redirect ke cetak
    header("Location: print_labelboning.php?idlabelboning={$idlabelboning}&idboning={$idboning}");
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    error_log("insert_labelboning exception: " . $e->getMessage());
    die('Terjadi kesalahan saat menyimpan data. Silakan coba lagi.');
}
