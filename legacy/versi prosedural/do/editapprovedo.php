<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$iddo = isset($_GET['iddo']) ? intval($_GET['iddo']) : 0;

if ($iddo > 0) {
   // Cek apakah iddo ada di database
   $query_check = "SELECT COUNT(*) FROM do WHERE iddo = ?";
   $stmt_check = $conn->prepare($query_check);
   $stmt_check->bind_param("i", $iddo);
   $stmt_check->execute();
   $stmt_check->bind_result($count);
   $stmt_check->fetch();
   $stmt_check->close();

   if ($count == 0) {
      die("Error: Data DO tidak ditemukan.");
   }

   // Cek apakah status DO sudah "Invoiced"
   $query_check_status = "SELECT status FROM do WHERE iddo = ?";
   $stmt_check_status = $conn->prepare($query_check_status);
   $stmt_check_status->bind_param("i", $iddo);
   $stmt_check_status->execute();
   $stmt_check_status->bind_result($status);
   $stmt_check_status->fetch();
   $stmt_check_status->close();

   if ($status == "Invoiced") {
      // Jika status DO sudah "Invoiced", kembalikan ke halaman do.php dengan pesan error
      header("location: do.php?message=Gagal Unapprove! Invoice sudah terbit.");
      exit();
   }

   // Soft delete doreceipt
   $query_soft_delete_doreceipt = "UPDATE doreceipt SET is_deleted = 1 WHERE iddo = ?";
   $stmt = $conn->prepare($query_soft_delete_doreceipt);
   $stmt->bind_param("i", $iddo);
   $stmt->execute();
   $stmt->close();

   // Update status do menjadi Unapproved
   $query_update_do = "UPDATE do SET status = 'Unapproved' WHERE iddo = ?";
   $stmt = $conn->prepare($query_update_do);
   $stmt->bind_param("i", $iddo);
   $stmt->execute();
   $stmt->close();

   // Ambil donumber berdasarkan iddo
   $query_select_donumber = "SELECT donumber FROM do WHERE iddo = ?";
   $stmt = $conn->prepare($query_select_donumber);
   $stmt->bind_param("i", $iddo);
   $stmt->execute();
   $stmt->bind_result($donumber);
   $stmt->fetch();
   $stmt->close();

   if (!isset($_SESSION['idusers'])) {
      die("Error: Pengguna tidak dikenali.");
   }

   // Insert ke logactivity
   $idusers = $_SESSION['idusers'];
   $event = "Unapproved DO";
   $waktu = date('Y-m-d H:i:s');

   $queryLogActivity = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, ?)";
   $stmt = $conn->prepare($queryLogActivity);
   $stmt->bind_param("isss", $idusers, $event, $donumber, $waktu);
   $stmt->execute();
   $stmt->close();

   // Redirect ke halaman do.php
   header("location: do.php");
   exit();
} else {
   die("Error: ID DO tidak valid.");
}
