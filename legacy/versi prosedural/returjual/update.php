<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// ================= VALIDASI =================
$idreturjual = intval($_POST['idreturjual'] ?? 0);
$idcustomer  = intval($_POST['idcustomer'] ?? 0);
$returdate   = $_POST['returdate'] ?? '';
$donumber    = trim($_POST['donumber'] ?? '');
$note        = trim($_POST['note'] ?? '');
$idusers     = intval($_POST['idusers'] ?? 0);

if ($idreturjual <= 0 || $idcustomer <= 0 || !$returdate) {
    die("Data tidak lengkap");
}

if ($donumber === '') {
    $donumber = null;
}

// ================= UPDATE =================
$stmt = mysqli_prepare($conn, "
    UPDATE returjual SET
        idcustomer = ?,
        donumber   = ?,
        returdate  = ?,
        note       = ?,
        idusers    = ?
    WHERE idreturjual = ?
");

mysqli_stmt_bind_param(
    $stmt,
    "isssii",
    $idcustomer,
    $donumber,
    $returdate,
    $note,
    $idusers,
    $idreturjual
);

if (!mysqli_stmt_execute($stmt)) {
    die("Gagal update data");
}

header("Location: index.php");
exit;
