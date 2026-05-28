<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id'])) {
   $idusers = intval($_GET['id']);

   // Prepare statement untuk update tabel users
   $stmt = $conn->prepare("UPDATE users SET status = ? WHERE idusers = ?");
   if (!$stmt) {
      die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
   }

   $status = "AKTIF";
   $stmt->bind_param("si", $status, $idusers);

   if ($stmt->execute()) {
      // Redirect ke halaman index.php setelah update berhasil
      header("location: user.php");
      exit();
   } else {
      echo "Error updating users: " . $stmt->error;
      exit();
   }
} else {
   echo "ID user tidak ditemukan.";
   exit();
}

$conn->close();
