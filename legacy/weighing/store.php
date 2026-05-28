<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "weight_number.php"; // disini dibentuk $wghcattle

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Akses langsung tanpa POST → lempar balik
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Ambil data utama
$idreceive   = (int)($_POST['idreceive'] ?? 0);
$weigh_date  = $_POST['weigh_date'] ?? date('Y-m-d');
$note        = trim($_POST['note'] ?? '');
$iduser      = (int)($_SESSION['idusers'] ?? 0);

$idreceivedetail = $_POST['idreceivedetail'] ?? [];
$actual_weight   = $_POST['actual_weight'] ?? [];
$detail_notes    = $_POST['detail_notes'] ?? [];

if ($idreceive <= 0) {
    die("Error: Penerimaan tidak valid.");
}
if ($iduser <= 0) {
    die("Error: Session user tidak valid.");
}
if (empty($idreceivedetail)) {
    die("Error: Tidak ada data detail yang dikirim.");
}

// Fungsi sederhana untuk normalisasi angka (1.000,50 → 1000.50)
function normalizeNumber($number)
{
    $number = trim((string)$number);
    if ($number === '') {
        return null;
    }

    // Hilangkan spasi
    $number = str_replace(' ', '', $number);

    // Kalau ada koma dan titik, tentukan mana decimal
    $lastComma = strrpos($number, ',');
    $lastDot   = strrpos($number, '.');

    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            // Format: 1.234,56 (koma desimal)
            $number = str_replace('.', '', $number);
            $number = str_replace(',', '.', $number);
        } else {
            // Format: 1,234.56 (titik desimal)
            $number = str_replace(',', '', $number);
        }
    } elseif ($lastComma !== false) {
        // Hanya koma → anggap desimal
        $number = str_replace('.', '', $number);
        $number = str_replace(',', '.', $number);
    } else {
        // Hanya titik / murni angka
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
// Validasi detail
// ========================
$rows = [];
$errors = [];

foreach ($idreceivedetail as $i => $idr) {
    $idr = (int)$idr;
    $rawWeight = $actual_weight[$i] ?? '';
    $w = normalizeNumber($rawWeight);
    $noteDetail = trim($detail_notes[$i] ?? '');

    // WAJIB DIISI
    if ($rawWeight === '') {
        $errors[] = "Berat actual baris " . ($i + 1) . " belum diisi.";
        continue;
    }

    // 0 BOLEH. Yang dilarang hanya: bukan angka atau negatif
    if ($w === null || $w < 0) {
        $errors[] = "Berat actual baris " . ($i + 1) . " tidak valid.";
        continue;
    }

    // 0 = artinya tidak ditimbang, tetap disimpan
    $rows[] = [
        'idreceivedetail' => $idr,
        'weight'          => $w,
        'notes'           => $noteDetail
    ];
}

if (!empty($errors)) {
    echo "WGH-" . e($wghcattle) . "<br><br>";
    echo "<strong>Error:</strong><ul>";
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
// SIMPAN DATA (TRANSAKSI)
// ========================
$conn->begin_transaction();

try {
    // Pastikan belum ada penimbangan untuk receive ini
    $stmt = $conn->prepare("
        SELECT idweigh 
        FROM weight_cattle 
        WHERE idreceive = ? AND is_deleted = 0 
        LIMIT 1
    ");
    $stmt->bind_param('i', $idreceive);
    $stmt->execute();
    $stmt->bind_result($existing);
    if ($stmt->fetch()) {
        $stmt->close();
        throw new Exception("Penerimaan ini sudah pernah diproses penimbangan.");
    }
    $stmt->close();

    // Ambil map eartag untuk idreceivedetail (supaya tidak perlu kirim eartag via form)
    $mapEartag = [];
    $stmt = $conn->prepare("
        SELECT idreceivedetail, eartag
        FROM cattle_receive_detail
        WHERE idreceive = ?
    ");
    $stmt->bind_param('i', $idreceive);
    $stmt->execute();
    $resMap = $stmt->get_result();
    while ($row = $resMap->fetch_assoc()) {
        $mapEartag[(int)$row['idreceivedetail']] = $row['eartag'];
    }
    $stmt->close();

    if (empty($mapEartag)) {
        throw new Exception("Detail penerimaan tidak ditemukan.");
    }

    // Insert HEADER ke weight_cattle (pakai nomor dari weight_number.php)
    $stmt = $conn->prepare("
        INSERT INTO weight_cattle
            (idreceive, weigh_no, weigh_date, idweigher, note, createby)
        VALUES (?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        'issisi',
        $idreceive,
        $wghcattle,
        $weigh_date,
        $iduser,
        $note,
        $iduser
    );
    $stmt->execute();
    if ($stmt->affected_rows <= 0) {
        throw new Exception("Gagal menyimpan header penimbangan.");
    }
    $idweigh = $stmt->insert_id;
    $stmt->close();

    // Insert DETAIL ke weight_cattle_detail
    $stmt = $conn->prepare("
        INSERT INTO weight_cattle_detail
            (idweigh, idreceivedetail, eartag, weight, notes, createby)
        VALUES (?,?,?,?,?,?)
    ");

    foreach ($rows as $r) {
        $idr   = $r['idreceivedetail'];
        if (!isset($mapEartag[$idr])) {
            throw new Exception("Eartag untuk detail ID $idr tidak ditemukan.");
        }
        $etag = $mapEartag[$idr];
        $w    = $r['weight'];
        $nd   = $r['notes'];

        $stmt->bind_param(
            'iisdsi',
            $idweigh,
            $idr,
            $etag,
            $w,
            $nd,
            $iduser
        );
        $stmt->execute();
    }
    $stmt->close();

    // Sukses
    $conn->commit();

    // Balik ke index timbang
    header("Location: index.php");
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    die("Terjadi kesalahan: " . e($ex->getMessage()));
}
