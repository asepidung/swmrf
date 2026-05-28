<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Akses tidak diizinkan.");

// =====================================================
// 0) Data dasar
// =====================================================
$iduser   = $_SESSION['idusers'] ?? 0;
$idrepack = intval($_POST['idrepack'] ?? 0);
$sumber   = 'REPACK';
$idsumber = $idrepack;

if ($idsumber <= 0) die("ID repack tidak valid.");

$rows = $_POST['rows'] ?? [];
if (!is_array($rows) || empty($rows)) die("Tidak ada data produk yang dikirim.");

// =====================================================
// 1) Hapus data lama pemakaian bahan repack ini
// =====================================================
$stmtDel = $conn->prepare("DELETE FROM raw_usage WHERE sumber=? AND idsumber=?");
$stmtDel->bind_param("si", $sumber, $idsumber);
$stmtDel->execute();
$stmtDel->close();

// =====================================================
// 2) Helper: ambil mapping BOM aktif per barang
// =====================================================
function get_bom_map_by_barang(mysqli $conn, int $idbarang): array
{
    $sql = "SELECT r.idrawmate, r.nmrawmate, r.idrawcategory
            FROM bom_rawmate b
            JOIN rawmate r ON r.idrawmate = b.idrawmate
            WHERE b.idbarang = ? AND b.is_active = 1";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $idbarang);
    $st->execute();
    $res = $st->get_result();

    $map = [
        'top'    => 0,
        'bottom' => 0,
        'linier' => 0,
        'vacuum' => 0,
        'tray'   => 0,
        'karung' => 0,
    ];

    while ($r = $res->fetch_assoc()) {
        $cat = (int)$r['idrawcategory'];
        $nmU = strtoupper($r['nmrawmate']);

        // Karton (cat 2): TOP / BOTTOM
        if ($cat === 2) {
            if (strpos($nmU, 'TOP') !== false && $map['top'] === 0) {
                $map['top'] = (int)$r['idrawmate'];
            } elseif (strpos($nmU, 'BOTTOM') !== false && $map['bottom'] === 0) {
                $map['bottom'] = (int)$r['idrawmate'];
            }
        }
        // Plastik (cat 3): LINIER vs VACUUM
        elseif ($cat === 3) {
            if (strpos($nmU, 'LINIER') !== false && $map['linier'] === 0) {
                $map['linier'] = (int)$r['idrawmate'];
            } else {
                if ($map['vacuum'] === 0) $map['vacuum'] = (int)$r['idrawmate'];
            }
        }
        // Karung (cat 21)
        elseif ($cat === 21 && $map['karung'] === 0) {
            $map['karung'] = (int)$r['idrawmate'];
        }
        // Tray (cat 22)
        elseif (($cat === 22 || strpos($nmU, 'TRAY') !== false) && $map['tray'] === 0) {
            $map['tray'] = (int)$r['idrawmate'];
        }
    }

    $st->close();
    return $map;
}

// =====================================================
// 3) Hitung total pemakaian bahan berdasarkan BOM
// =====================================================
$totalUsage = []; // [idrawmate] => total qty

foreach ($rows as $idbarang => $vals) {
    $idbarang = (int)$idbarang;
    if ($idbarang <= 0) continue;
    $bom = get_bom_map_by_barang($conn, $idbarang);

    foreach ($vals as $key => $qty) {
        $qty = (float)$qty;
        if ($qty <= 0) continue;

        $idraw = $bom[$key] ?? 0;
        if ($idraw <= 0) continue;

        if (!isset($totalUsage[$idraw])) $totalUsage[$idraw] = 0;
        $totalUsage[$idraw] += $qty;
    }
}

// =====================================================
// 4) Simpan hasil agregasi ke raw_usage
// =====================================================
$ins = $conn->prepare("
    INSERT INTO raw_usage (sumber, idsumber, idrawmate, qty, note, iduser, createtime)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        qty = VALUES(qty),
        note = VALUES(note),
        iduser = VALUES(iduser),
        createtime = NOW()
");

foreach ($totalUsage as $idraw => $totalQty) {
    $note = '';
    $ins->bind_param("siidis", $sumber, $idsumber, $idraw, $totalQty, $note, $iduser);
    $ins->execute();
}
$ins->close();

// =====================================================
// 5) Simpan material tambahan (EXTRA)
// =====================================================
if (!empty($_POST['extra_idrawmate']) && !empty($_POST['extra_qty'])) {
    $ins2 = $conn->prepare("
        INSERT INTO raw_usage (sumber, idsumber, idrawmate, qty, note, iduser, createtime)
        VALUES (?, ?, ?, ?, 'EXTRA', ?, NOW())
        ON DUPLICATE KEY UPDATE
            qty = VALUES(qty),
            note = VALUES(note),
            iduser = VALUES(iduser),
            createtime = NOW()
    ");

    foreach ($_POST['extra_idrawmate'] as $i => $idr) {
        $idr = (int)$idr;
        $qty = (float)($_POST['extra_qty'][$i] ?? 0);
        if ($idr <= 0 || $qty <= 0) continue;
        $ins2->bind_param("siidi", $sumber, $idsumber, $idr, $qty, $iduser);
        $ins2->execute();
    }
    $ins2->close();
}

// =====================================================
header("Location: laporan_rawusage_repack.php?id=$idsumber&msg=" . urlencode("Pemakaian bahan repack berhasil disimpan."));
exit;
