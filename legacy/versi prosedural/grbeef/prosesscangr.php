<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$idgr = intval($_POST['idgr']);
$barcode = mysqli_real_escape_string($conn, $_POST['barcode']);

// Cek apakah barcode sudah ada di tabel grbeefdetail dengan idgr terkait
$queryCekDuplikat = "
    SELECT * 
    FROM grbeefdetail 
    WHERE kdbarcode = '$barcode' AND idgr = $idgr AND is_deleted = 0";
$cekDuplikat = mysqli_query($conn, $queryCekDuplikat);

if (mysqli_num_rows($cekDuplikat) > 0) {
    // Jika duplikat ditemukan, redirect kembali ke halaman grscan dengan pesan error
    $_SESSION['error'] = "Data dengan barcode tersebut sudah ada di GR Detail.";
    header("Location: grscan.php?idgr=$idgr");
    exit();
}

// Jika tidak ada duplikat, lanjutkan proses pengambilan data dari tabel tallydetail
$queryTally = "
    SELECT * 
    FROM tallydetail 
    WHERE barcode = '$barcode' 
    LIMIT 1";
$resultTally = mysqli_query($conn, $queryTally);
$dataTally = mysqli_fetch_assoc($resultTally);

if ($dataTally) {
    $idgrade = $dataTally['idgrade'];
    $idbarang = $dataTally['idbarang'];
    $qty = $dataTally['weight'];
    $pcs = $dataTally['pcs'];
    $pod = $dataTally['pod'];
    $origin = $dataTally['origin'];

    // Insert data ke tabel grbeefdetail
    $queryInsertGrDetail = "
        INSERT INTO grbeefdetail (idgr, idgrade, idbarang, kdbarcode, pcs, qty, pod, is_deleted) 
        VALUES ($idgr, $idgrade, $idbarang, '$barcode', $pcs, $qty, '$pod', 0)";
    $insertGrDetail = mysqli_query($conn, $queryInsertGrDetail);

    // Insert data ke tabel stock
    $queryInsertStock = "
        INSERT INTO stock (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin) 
        VALUES ('$barcode', $idgrade, $idbarang, $qty, $pcs, '$pod', $origin)";
    $insertStock = mysqli_query($conn, $queryInsertStock);

    if ($insertGrDetail && $insertStock) {
        $_SESSION['success'] = "Data berhasil diinput ke GR Detail dan Stock.";
    } else {
        $_SESSION['error'] = "Gagal menyimpan data. Silakan coba lagi.";
    }
} else {
    $_SESSION['error'] = "Barcode tidak ditemukan di Tally Detail.";
}

header("Location: grscan.php?idgr=$idgr");
exit();
