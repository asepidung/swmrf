<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak diizinkan.");
}

$idbarang = intval($_POST['idbarang']);
$idusers  = $_SESSION['idusers'] ?? 0;

// Ambil semua input dari form
$karton_top     = $_POST['karton_top'] ?? '';
$karton_bottom  = $_POST['karton_bottom'] ?? '';
$plastik        = $_POST['plastik'] ?? '';
$linier         = isset($_POST['linier']) ? 1 : 0;
$karung         = isset($_POST['karung']) ? 1 : 0;
$drylog_qty     = intval($_POST['drylog'] ?? 0);
$tray_qty       = intval($_POST['tray'] ?? 0);

/**
 * Fungsi bantu insert/update BOM
 */
function save_bom($conn, $idbarang, $idrawmate, $qty = 1, $is_active = 1, $idusers = 0)
{
    if (!$idrawmate) return;
    $qty = max(0, intval($qty));

    $cek = mysqli_query($conn, "SELECT idbom FROM bom_rawmate WHERE idbarang=$idbarang AND idrawmate=$idrawmate");

    if (mysqli_num_rows($cek) > 0) {
        // Update jika sudah ada
        $q = "UPDATE bom_rawmate 
              SET qty=$qty, is_active=$is_active, iduser=$idusers, updatetime=NOW() 
              WHERE idbarang=$idbarang AND idrawmate=$idrawmate";
        mysqli_query($conn, $q);
    } else {
        // Insert baru jika aktif
        if ($is_active == 1) {
            $q = "INSERT INTO bom_rawmate (idbarang, idrawmate, qty, is_active, iduser, createtime, updatetime)
                  VALUES ($idbarang, $idrawmate, $qty, 1, $idusers, NOW(), NOW())";
            mysqli_query($conn, $q);
        }
    }
}

/**
 * Nonaktifkan semua bahan dari kategori tertentu
 */
function deactivate_category($conn, $idbarang, $idrawcategory)
{
    $q = "UPDATE bom_rawmate b
          JOIN rawmate r ON b.idrawmate = r.idrawmate
          SET b.is_active = 0, b.qty = 0, b.updatetime = NOW()
          WHERE b.idbarang = $idbarang AND r.idrawcategory = $idrawcategory";
    mysqli_query($conn, $q);
}

/* ===============================
   PROSES PENYIMPANAN PER KOMPONEN
   =============================== */

// 1️⃣ Karung (checkbox)
if ($karung == 1) {
    $qk = mysqli_query($conn, "SELECT idrawmate FROM rawmate WHERE idrawcategory = 21 LIMIT 1");
    if ($dk = mysqli_fetch_assoc($qk)) {
        save_bom($conn, $idbarang, $dk['idrawmate'], 1, 1, $idusers);
    }
} else {
    deactivate_category($conn, $idbarang, 21);
}

// 2️⃣ Karton Top
if (!empty($karton_top)) {
    save_bom($conn, $idbarang, $karton_top, 1, 1, $idusers);
} else {
    deactivate_category($conn, $idbarang, 2);
}

// 3️⃣ Karton Bottom
if (!empty($karton_bottom)) {
    save_bom($conn, $idbarang, $karton_bottom, 1, 1, $idusers);
}

// 4️⃣ Plastik Cryovac
if (!empty($plastik)) {
    save_bom($conn, $idbarang, $plastik, 1, 1, $idusers);
} else {
    deactivate_category($conn, $idbarang, 3);
}

// 5️⃣ Plastik Linier (checkbox)
if ($linier == 1) {
    $ql = mysqli_query($conn, "SELECT idrawmate FROM rawmate WHERE idrawcategory = 3 AND nmrawmate LIKE '%LINIER%' LIMIT 1");
    if ($dl = mysqli_fetch_assoc($ql)) {
        save_bom($conn, $idbarang, $dl['idrawmate'], 1, 1, $idusers);
    }
} else {
    // Nonaktifkan hanya material Linier
    $ql = mysqli_query($conn, "SELECT idrawmate FROM rawmate WHERE idrawcategory = 3 AND nmrawmate LIKE '%LINIER%' LIMIT 1");
    if ($dl = mysqli_fetch_assoc($ql)) {
        save_bom($conn, $idbarang, $dl['idrawmate'], 0, 0, $idusers);
    }
}

// 6️⃣ Drylog (langsung pakai idrawmate = 5)
if ($drylog_qty > 0) {
    $id_drylog = 5; // ID bahan Drylog tetap
    save_bom($conn, $idbarang, $id_drylog, $drylog_qty, 1, $idusers);
} else {
    // Nonaktifkan hanya material Drylog id 5
    $q = "UPDATE bom_rawmate SET is_active = 0, qty = 0, updatetime = NOW()
          WHERE idbarang = $idbarang AND idrawmate = 5";
    mysqli_query($conn, $q);
}


// 7️⃣ Tray (kategori 22)
if ($tray_qty > 0) {
    $qt = mysqli_query($conn, "SELECT idrawmate FROM rawmate WHERE idrawcategory = 22 LIMIT 1");
    if ($dt = mysqli_fetch_assoc($qt)) {
        save_bom($conn, $idbarang, $dt['idrawmate'], $tray_qty, 1, $idusers);
    }
} else {
    deactivate_category($conn, $idbarang, 22);
}

// ✅ Redirect dengan notifikasi sukses
header('Location: ../barang/barang.php?msg=' . urlencode('<i class="fas fa-bomb text-danger"></i> BOM SUDAH AKTIF'));
exit;
