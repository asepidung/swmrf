<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$id = intval($_GET['id'] ?? 0);
$idreturjual = intval($_GET['idreturjual'] ?? 0);

if ($id <= 0 || $idreturjual <= 0) {
    die("Invalid request");
}

// soft delete
mysqli_query($conn, "
    UPDATE returjualdetail
    SET is_deleted = 1
    WHERE idreturjualdetail = $id
");

// balik ke halaman scan
header("Location: scan_retur.php?idreturjual=$idreturjual");
exit;
