<?php
session_start();
if (!isset($_SESSION['login'])) {
   header("location: ../verifications/login.php");
   exit;
}
require "../konak/conn.php";

$idst = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($idst > 0) {
   // Start a transaction
   mysqli_begin_transaction($conn);

   try {
      // Ambil nomor stock take (nost) dari tabel stocktake
      $query_nost = "SELECT nost FROM stocktake WHERE idst = ?";
      $stmt_nost = mysqli_prepare($conn, $query_nost);

      if ($stmt_nost) {
         mysqli_stmt_bind_param($stmt_nost, "i", $idst);
         mysqli_stmt_execute($stmt_nost);
         $result_nost = mysqli_stmt_get_result($stmt_nost);
         $row_nost = mysqli_fetch_assoc($result_nost);
         $nost = $row_nost['nost'];
         mysqli_stmt_close($stmt_nost);
      } else {
         throw new Exception("Failed to prepare the select statement for stocktake.");
      }

      // Delete from stocktakedetail
      $query_detail = "DELETE FROM stocktakedetail WHERE idst = ?";
      $stmt_detail = mysqli_prepare($conn, $query_detail);

      if ($stmt_detail) {
         mysqli_stmt_bind_param($stmt_detail, "i", $idst);
         mysqli_stmt_execute($stmt_detail);
         mysqli_stmt_close($stmt_detail);
      } else {
         throw new Exception("Failed to prepare the delete statement for stocktakedetail.");
      }

      // Delete from stocktake
      $query_stocktake = "DELETE FROM stocktake WHERE idst = ?";
      $stmt_stocktake = mysqli_prepare($conn, $query_stocktake);

      if ($stmt_stocktake) {
         mysqli_stmt_bind_param($stmt_stocktake, "i", $idst);
         mysqli_stmt_execute($stmt_stocktake);
         mysqli_stmt_close($stmt_stocktake);
      } else {
         throw new Exception("Failed to prepare the delete statement for stocktake.");
      }

      // Insert log activity
      $event = "Delete Stock Take";
      $iduser = $_SESSION['idusers'];
      $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
      $stmt_log = $conn->prepare($logQuery);
      $stmt_log->bind_param("iss", $iduser, $event, $nost);
      $stmt_log->execute();
      $stmt_log->close();

      // Commit the transaction
      mysqli_commit($conn);
      $_SESSION['message'] = "Stock taking record and its details deleted successfully.";
   } catch (Exception $e) {
      // Rollback the transaction
      mysqli_rollback($conn);
      $_SESSION['error'] = $e->getMessage();
   }
} else {
   $_SESSION['error'] = "Invalid ID provided.";
}

header("location: index.php");
exit;
