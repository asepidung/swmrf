<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$barcode = trim($_POST['barcode'] ?? '');
$barcode = str_replace(["\r", "\n"], '', $barcode);
$idreturjual = intval($_POST['idreturjual'] ?? 0);

if ($barcode === '' || $idreturjual <= 0) {
    header("Location: scan_retur.php?idreturjual=$idreturjual&stat=badinput");
    exit;
}

// Cek duplikat
$cek = mysqli_query($conn, "
    SELECT 1 FROM returjualdetail
    WHERE idreturjual = $idreturjual
      AND kdbarcode = '" . mysqli_real_escape_string($conn, $barcode) . "'
    LIMIT 1
");
if (mysqli_num_rows($cek) > 0) {
    header("Location: scan_retur.php?idreturjual=$idreturjual&stat=duplicate");
    exit;
}

// Ambil data dari tallydetail (TAMBAH ph)
$stmt = mysqli_prepare($conn, "
    SELECT idbarang, idgrade, weight, pcs, pod, ph
    FROM tallydetail
    WHERE barcode = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "s", $barcode);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$data = mysqli_fetch_assoc($res);
if (!$data) {
    header("Location: scan_retur.php?idreturjual=$idreturjual&stat=notfound");
    exit;
}

// Insert ke returjualdetail (TAMBAH ph)
$ins = mysqli_prepare($conn, "
    INSERT INTO returjualdetail
        (idreturjual, idgrade, idbarang, kdbarcode, qty, pcs, ph, pod)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
");

mysqli_stmt_bind_param(
    $ins,
    "iiisdsss",
    $idreturjual,
    $data['idgrade'],
    $data['idbarang'],
    $barcode,
    $data['weight'],
    $data['pcs'],
    $data['ph'],
    $data['pod']
);

mysqli_stmt_execute($ins);

// balik ke scan
header("Location: scan_retur.php?idreturjual=$idreturjual");
exit;
