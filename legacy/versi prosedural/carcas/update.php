<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$idcarcase = (int)($_POST['idcarcase'] ?? 0);
$killdate  = $_POST['killdate'] ?? date('Y-m-d');
$note      = trim($_POST['note'] ?? '');
$idusers   = (int)($_SESSION['idusers'] ?? 0);

$iddetail       = $_POST['iddetail']        ?? [];
$idweighdetail  = $_POST['idweighdetail']   ?? [];
$eartag         = $_POST['eartag']          ?? [];
$live_weight    = $_POST['live_weight']     ?? [];

$breed     = $_POST['breed']     ?? [];
$carcase1  = $_POST['carcase1']  ?? [];
$carcase2  = $_POST['carcase2']  ?? [];
$hides     = $_POST['hides']     ?? [];
$tails     = $_POST['tails']     ?? [];

if ($idcarcase <= 0) {
    die("ID carcas tidak valid.");
}
if ($idusers <= 0) {
    die("Session user tidak valid.");
}

/* ==============================
 * Normalisasi angka
 * ============================== */
function normalizeNumber($number)
{
    $number = trim((string)$number);
    if ($number === '') return null;

    $number = str_replace(' ', '', $number);
    $lastComma = strrpos($number, ',');
    $lastDot   = strrpos($number, '.');

    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            $number = str_replace('.', '', $number);
            $number = str_replace(',', '.', $number);
        } else {
            $number = str_replace(',', '', $number);
        }
    } elseif ($lastComma !== false) {
        $number = str_replace('.', '', $number);
        $number = str_replace(',', '.', $number);
    }

    if (!is_numeric($number)) return null;
    return (float)$number;
}

/* ==============================
 * Proses baris
 * ============================== */
$rowsUpdate = [];
$rowsInsert = [];
$errors     = [];

foreach ($idweighdetail as $i => $idwd) {
    $idwd  = (int)$idwd;
    $idd   = (int)($iddetail[$i] ?? 0);
    $etag  = trim($eartag[$i] ?? '');
    $breedV = strtoupper(trim($breed[$i] ?? ''));

    $c1 = normalizeNumber($carcase1[$i] ?? '') ?? 0;
    $c2 = normalizeNumber($carcase2[$i] ?? '') ?? 0;
    $h  = normalizeNumber($hides[$i]    ?? '') ?? 0;
    $t  = normalizeNumber($tails[$i]    ?? '') ?? 0;
    $lw = normalizeNumber($live_weight[$i] ?? '') ?? 0;

    // semua kosong → abaikan
    if ($c1 == 0 && $c2 == 0 && $h == 0 && $t == 0) {
        continue;
    }

    if ($breedV === '') {
        $errors[] = "Class wajib diisi pada baris " . ($i + 1);
    }

    if ($idd > 0) {
        // UPDATE
        $rowsUpdate[] = [
            'iddetail' => $idd,
            'breed'    => $breedV,
            'c1'       => $c1,
            'c2'       => $c2,
            'h'        => $h,
            't'        => $t,
        ];
    } else {
        // INSERT
        $rowsInsert[] = [
            'idwd'  => $idwd,
            'etag'  => $etag,
            'breed' => $breedV,
            'lw'    => $lw,
            'c1'    => $c1,
            'c2'    => $c2,
            'h'     => $h,
            't'     => $t,
        ];
    }
}

if (!empty($errors)) {
    echo "<ul>";
    foreach ($errors as $e) echo "<li>" . e($e) . "</li>";
    echo "</ul><a href='javascript:history.back()'>Kembali</a>";
    exit;
}

/* ==============================
 * SIMPAN
 * ============================== */
$conn->begin_transaction();

try {
    // Update header
    $stmt = $conn->prepare("
        UPDATE carcase 
        SET killdate = ?, note = ?
        WHERE idcarcase = ? AND is_deleted = 0
    ");
    $stmt->bind_param('ssi', $killdate, $note, $idcarcase);
    $stmt->execute();
    $stmt->close();

    // UPDATE detail lama
    if ($rowsUpdate) {
        $stmt = $conn->prepare("
            UPDATE carcasedetail
            SET breed=?, carcase1=?, carcase2=?, hides=?, tail=?
            WHERE iddetail=? AND idcarcase=?
        ");
        foreach ($rowsUpdate as $r) {
            $stmt->bind_param(
                'sddddii',
                $r['breed'],
                $r['c1'],
                $r['c2'],
                $r['h'],
                $r['t'],
                $r['iddetail'],
                $idcarcase
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    // INSERT detail baru
    if ($rowsInsert) {
        $stmt = $conn->prepare("
            INSERT INTO carcasedetail
            (idcarcase, idweightdetail, breed, berat, eartag, carcase1, carcase2, hides, tail)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        foreach ($rowsInsert as $r) {
            $stmt->bind_param(
                'iisdsdddd',
                $idcarcase,
                $r['idwd'],
                $r['breed'],
                $r['lw'],
                $r['etag'],
                $r['c1'],
                $r['c2'],
                $r['h'],
                $r['t']
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    $conn->commit();
    header("Location: view.php?id=" . $idcarcase);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("Gagal menyimpan: " . e($e->getMessage()));
}
