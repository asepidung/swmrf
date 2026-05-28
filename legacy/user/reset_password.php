<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!isset($_SESSION['idusers']) || $_SESSION['idusers'] != 1) {
    die("Akses ditolak");
}

if (!isset($_GET['id'])) {
    header("Location: user.php");
    exit;
}

$idusers = (int) $_GET['id'];

// password default = 123
$password_default = password_hash("1234", PASSWORD_DEFAULT);

$query = "UPDATE users 
          SET passuser = '$password_default' 
          WHERE idusers = $idusers";

mysqli_query($conn, $query);

header("Location: user.php");
exit;
