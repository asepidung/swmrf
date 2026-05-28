<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

// Hanya boleh POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$idweight   = (int)($_POST['idweight']   ?? 0);
$idsupplier = (int)($_POST['idsupplier'] ?? 0);
$killdate   = $_POST['killdate']        ?? date('Y-m-d');
$note       = trim($_POST['note']       ?? '');
$idusers    = (int)($_SESSION['idusers'] ?? 0);

$idweighdetail = $_POST['idweighdetail'] ?? [];
$live_weight   = $_POST['live_weight']   ?? [];
$eartag        = $_POST['eartag']        ?? [];
$breed         = $_POST['breed']         ?? [];
$carcase1      = $_POST['carcase1']      ?? [];
$carcase2      = $_POST['carcase2']      ?? [];
$hides         = $_POST['hides']         ?? [];
$tails         = $_POST['tails']         ?? [];

if ($idweight <= 0 || $idsupplier <= 0) {
    die("Error: Data header tidak valid.");
}
if ($idusers <= 0) {
    die("Error: Session user tidak valid.");
}
if (empty($idweighdetail)) {
    die("Error: Tidak ada data detail yang dikirim.");
}

// -----------------------------
// Fungsi normalisasi angka
// -----------------------------
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
            // format: 1.234,56  -> 1234.56
            $number = str_replace('.', '', $number);
            $number = str_replace(',', '.', $number);
        } else {
            // format: 1,234.56 -> 1234.56
            $number = str_replace(',', '', $number);
        }
    } elseif ($lastComma !== false) {
        // hanya koma → desimal (1.234,56 or 1234,56)
        $number = str_replace('.', '', $number);
        $number = str_replace(',', '.', $number);
    } else {
        // hanya titik / angka
        if (substr_count($number, '.') > 1) {
            $parts = explode('.', $number);
            $last  = array_pop($parts);
            $number = implode('', $parts) . '.' . $last;
        }
    }

    // final check numeric
    if (!is_numeric($number)) {
        return null;
    }
    return (float)$number;
}

// -----------------------------
// Validasi & siapkan baris detail
// -----------------------------
$rows   = [];   // hanya baris yang benar-benar dipotong
$errors = [];

foreach ($idweighdetail as $i => $idwd) {
    $idwd  = (int)$idwd;
    $etag  = isset($eartag[$i]) ? trim($eartag[$i]) : '';
    $classRaw = isset($breed[$i]) ? strtoupper(trim($breed[$i])) : '';

    // Live weight (boleh kosong)
    $rawLive = $live_weight[$i] ?? '';
    $liveVal = normalizeNumber($rawLive);
    if ($rawLive !== '' && $liveVal === null) {
        $errors[] = "Berat saat penerimaan baris " . ($i + 1) . " tidak valid.";
    }
    // treat null as zero for live weight when needed downstream
    $beratLive = ($liveVal === null || $liveVal < 0) ? 0.0 : $liveVal;

    // Carcase A
    $rawC1 = $carcase1[$i] ?? '';
    $valC1 = normalizeNumber($rawC1);
    if ($rawC1 !== '' && $valC1 === null) {
        $errors[] = "Carcase A baris " . ($i + 1) . " tidak valid.";
    }
    $c1 = ($valC1 === null || $valC1 < 0) ? 0.0 : $valC1;

    // Carcase B
    $rawC2 = $carcase2[$i] ?? '';
    $valC2 = normalizeNumber($rawC2);
    if ($rawC2 !== '' && $valC2 === null) {
        $errors[] = "Carcase B baris " . ($i + 1) . " tidak valid.";
    }
    $c2 = ($valC2 === null || $valC2 < 0) ? 0.0 : $valC2;

    // Hides
    $rawH = $hides[$i] ?? '';
    $valH = normalizeNumber($rawH);
    if ($rawH !== '' && $valH === null) {
        $errors[] = "Hides baris " . ($i + 1) . " tidak valid.";
    }
    $h = ($valH === null || $valH < 0) ? 0.0 : $valH;

    // Tail
    $rawT = $tails[$i] ?? '';
    $valT = normalizeNumber($rawT);
    if ($rawT !== '' && $valT === null) {
        $errors[] = "Tail baris " . ($i + 1) . " tidak valid.";
    }
    $t = ($valT === null || $valT < 0) ? 0.0 : $valT;

    // Apakah sapi ini benar-benar dipotong?
    $isSlaughtered = ($c1 > 0 || $c2 > 0 || $h > 0);

    if (!$isSlaughtered) {
        // sapi belum dipotong → tidak masuk carcase, abaikan
        continue;
    }

    // Kalau ikut dipotong, class/breed wajib diisi
    if ($classRaw === '') {
        $errors[] = "Class (breed) baris " . ($i + 1) . " wajib diisi untuk sapi yang dipotong.";
    }

    if ($idwd <= 0 || $etag === '') {
        $errors[] = "Data eartag / id detail tidak valid pada baris " . ($i + 1) . ".";
        continue;
    }

    $rows[] = [
        'idweightdetail' => $idwd,
        'eartag'         => $etag,
        'breed'          => $classRaw,
        'berat'          => $beratLive,
        'carcase1'       => $c1,
        'carcase2'       => $c2,
        'hides'          => $h,
        'tail'           => $t,
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
    die("Error: Tidak ada sapi yang dipotong. Isi minimal satu baris Carcase/Hides.");
}

// -----------------------------
// Simpan ke database (TRANSAKSI)
// -----------------------------
$conn->begin_transaction();

try {
    // Insert HEADER ke carcase
    $stmt = $conn->prepare("
        INSERT INTO carcase
            (killdate, idsupplier, idweight, note, idusers, is_deleted)
        VALUES (?,?,?,?,?,0)
    ");
    if ($stmt === false) {
        throw new Exception("Prepare header gagal: " . $conn->error);
    }

    $stmt->bind_param(
        'siisi',
        $killdate,
        $idsupplier,
        $idweight,
        $note,
        $idusers
    );
    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan header carcas: " . $stmt->error);
    }
    $idcarcase = $stmt->insert_id;
    $stmt->close();

    // Insert DETAIL ke carcasedetail
    $stmt = $conn->prepare("
        INSERT INTO carcasedetail
            (idcarcase, idweightdetail, breed, berat, eartag, carcase1, carcase2, hides, tail)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");
    if ($stmt === false) {
        throw new Exception("Prepare insert detail gagal: " . $conn->error);
    }

    foreach ($rows as $r) {
        $idwd   = $r['idweightdetail'];
        $breedS = $r['breed'];
        $berat  = $r['berat'];
        $etag   = $r['eartag'];
        $c1     = $r['carcase1'];
        $c2     = $r['carcase2'];
        $h      = $r['hides'];
        $t      = $r['tail'];

        // tipe: i i s d s d d d d
        $bind = $stmt->bind_param(
            'iisdsdddd',
            $idcarcase,
            $idwd,
            $breedS,
            $berat,
            $etag,
            $c1,
            $c2,
            $h,
            $t
        );

        if ($bind === false) {
            throw new Exception("Bind param gagal: " . $stmt->error);
        }

        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan detail carcas: " . $stmt->error);
        }
    }
    $stmt->close();

    $conn->commit();

    // Sukses → balik ke index carcas
    header("Location: index.php");
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    die("Terjadi kesalahan: " . e($ex->getMessage()));
}
