<?php
require "../verifications/auth.php";
require "../konak/conn.php";

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$idweigh     = (int)($_POST['idweigh'] ?? 0);
$weigh_date  = $_POST['weigh_date'] ?? date('Y-m-d');
$note        = trim($_POST['note'] ?? '');
$iduser      = (int)($_SESSION['idusers'] ?? 0);

$idweighdetail = $_POST['idweighdetail'] ?? [];
$actual_weight = $_POST['actual_weight'] ?? [];
$detail_notes  = $_POST['detail_notes'] ?? [];

if ($idweigh <= 0) {
    die("Error: Data timbang tidak valid.");
}
if ($iduser <= 0) {
    die("Error: Session user tidak valid.");
}
if (empty($idweighdetail)) {
    die("Error: Tidak ada data detail yang dikirim.");
}

// Fungsi normalisasi angka
function normalizeNumber($number)
{
    $number = trim((string)$number);
    if ($number === '') {
        return null;
    }

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
    } else {
        if (substr_count($number, '.') > 1) {
            $parts = explode('.', $number);
            $last  = array_pop($parts);
            $number = implode('', $parts) . '.' . $last;
        }
    }

    if (!is_numeric($number)) {
        return null;
    }
    return (float)$number;
}

// ========================
// Validasi detail (0 BOLEH)
// ========================
$rows   = [];
$errors = [];

foreach ($idweighdetail as $i => $idwd) {
    $idwd       = (int)$idwd;
    $rawWeight  = $actual_weight[$i] ?? '';
    $w          = normalizeNumber($rawWeight);
    $noteDetail = trim($detail_notes[$i] ?? '');

    // wajib diisi
    if ($rawWeight === '') {
        $errors[] = "Berat actual baris " . ($i + 1) . " belum diisi.";
        continue;
    }

    // 0 boleh, yang dilarang: bukan angka atau negatif
    if ($w === null || $w < 0) {
        $errors[] = "Berat actual baris " . ($i + 1) . " tidak valid.";
        continue;
    }

    $rows[] = [
        'idweighdetail' => $idwd,
        'weight'        => $w,          // 0 = tidak ditimbang
        'notes'         => $noteDetail,
    ];
}

if (!empty($errors)) {
    echo "<h3>Error:</h3><ul>";
    foreach ($errors as $err) {
        echo "<li>" . e($err) . "</li>";
    }
    echo "</ul>";
    echo '<p><a href="javascript:history.back()">Kembali</a></p>';
    exit;
}

if (empty($rows)) {
    die("Error: Tidak ada baris valid untuk disimpan.");
}

// ========================
// UPDATE DATA (TRANSAKSI)
// ========================
$conn->begin_transaction();

try {
    // Pastikan header masih ada & aktif
    $stmt = $conn->prepare("
        SELECT idweigh 
        FROM weight_cattle 
        WHERE idweigh = ? AND is_deleted = 0 
        LIMIT 1
    ");
    $stmt->bind_param('i', $idweigh);
    $stmt->execute();
    $stmt->bind_result($existing);
    if (!$stmt->fetch()) {
        $stmt->close();
        throw new Exception("Data penimbangan tidak ditemukan atau sudah dihapus.");
    }
    $stmt->close();

    // Update HEADER
    $stmt = $conn->prepare("
        UPDATE weight_cattle
        SET weigh_date = ?, 
            note       = ?, 
            updateby   = ?, 
            updatetime = NOW()
        WHERE idweigh = ?
    ");
    $stmt->bind_param(
        'ssii',
        $weigh_date,
        $note,
        $iduser,
        $idweigh
    );
    $stmt->execute();
    $stmt->close();

    // Update DETAIL pakai hasil validasi $rows
    $stmt = $conn->prepare("
        UPDATE weight_cattle_detail
        SET weight    = ?,
            notes     = ?,
            updateby  = ?,
            updatetime = NOW()
        WHERE idweighdetail = ? AND idweigh = ?
    ");

    foreach ($rows as $r) {
        $idwd = $r['idweighdetail'];
        $w    = $r['weight'];
        $nd   = $r['notes'];

        $stmt->bind_param(
            'dsiii',
            $w,
            $nd,
            $iduser,
            $idwd,
            $idweigh
        );
        $stmt->execute();
    }
    $stmt->close();

    $conn->commit();

    header("Location: index.php");
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    die("Terjadi kesalahan: " . e($ex->getMessage()));
}
