<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$idso = isset($_GET['idso']) ? (int)$_GET['idso'] : 0;

if ($idso <= 0) {
    die("SO tidak valid");
}

// update progress ke Cancel
$stmt = $conn->prepare("
   UPDATE salesorder 
   SET progress = 'Cancel' 
   WHERE idso = ? AND is_deleted = 0
");
$stmt->bind_param("i", $idso);
$stmt->execute();

// balik ke draft tally
header("Location: index.php");
exit;
