<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "return_number.php"; // menghasilkan $returnnumber

// ======================
// VALIDASI DASAR
// ======================
$idcustomer  = intval($_POST['idcustomer'] ?? 0);
$returdate   = $_POST['returdate'] ?? '';
$donumber    = trim($_POST['donumber'] ?? '');
$note        = trim($_POST['note'] ?? '');
$idusers     = intval($_POST['idusers'] ?? 0);

if ($idcustomer <= 0 || empty($returdate) || $idusers <= 0) {
    die("Data tidak lengkap");
}

// normalisasi donumber
if ($donumber === '') {
    $donumber = null;
}

// ======================
// INSERT RETUR JUAL
// ======================
$stmt = mysqli_prepare($conn, "
    INSERT INTO returjual
        (returnnumber, returdate, idcustomer, donumber, note, idusers)
    VALUES
        (?, ?, ?, ?, ?, ?)
");

mysqli_stmt_bind_param(
    $stmt,
    "ssissi",
    $returnnumber,
    $returdate,
    $idcustomer,
    $donumber,
    $note,
    $idusers
);

if (!mysqli_stmt_execute($stmt)) {
    die("Gagal menyimpan retur jual");
}

$idreturjual = mysqli_insert_id($conn);

header("Location: index.php");
exit;
