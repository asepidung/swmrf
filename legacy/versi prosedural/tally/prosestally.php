<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "notally.php";

if (isset($_POST['submit'])) {
   $idso = $_POST['idso'];
   $deliverydate = $_POST['deliverydate'];
   $idcustomer = $_POST['idcustomer'];
   $po = $_POST['po'];
   $sonumber = $_POST['sonumber'];
   $notally  = $kodeauto;
   $iduser = $_SESSION['idusers']; // Ambil ID user dari sesi yang aktif

   // Cek apakah idso sudah ada di tabel tally
   $checkQuery = "SELECT idso FROM tally WHERE idso = ? AND is_deleted = 0";
   if ($stmt_check = $conn->prepare($checkQuery)) {
      $stmt_check->bind_param("i", $idso);
      $stmt_check->execute();
      $stmt_check->store_result();

      // Jika ada hasil (idso sudah ada), redirect ke index.php
      if ($stmt_check->num_rows > 0) {
         // idso sudah ada, redirect ke halaman index.php
         header("location: index.php?error=Tally Sudah Di Ada Di List");
         exit();
      }

      // Jika idso belum ada, lanjutkan dengan proses INSERT
      $stmt_check->close();

      // Buat query INSERT tanpa pengecekan idso
      $query_tally = "INSERT INTO tally (idso, sonumber, notally, deliverydate, idcustomer, po) VALUES (?, ?, ?, ?, ?, ?)";
      if ($stmt_tally = $conn->prepare($query_tally)) {
         $stmt_tally->bind_param("isssis", $idso, $sonumber, $notally, $deliverydate, $idcustomer, $po);
         if ($stmt_tally->execute()) {
            // Dapatkan idtally yang baru saja diinput
            $last_id = mysqli_insert_id($conn);

            // Update progress di tabel salesorder
            $updateSql = "UPDATE salesorder SET progress = 'On Process' WHERE idso = ?";
            $stmt_update = $conn->prepare($updateSql);
            $stmt_update->bind_param("i", $idso);
            if ($stmt_update->execute()) {
               // Catat log aktivitas setelah pembuatan data tally berhasil
               $event = "Buat Data Tally";
               $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
               $logStmt = $conn->prepare($logQuery);
               $logStmt->bind_param('iss', $iduser, $event, $notally);
               $logStmt->execute();

               $stmt_update->close();
               $logStmt->close();

               $stmt_tally->close();
               $conn->close();

               // Redirect ke halaman tallydetail.php dengan idtally baru
               header("location: tallydetail.php?id=$last_id&stat=ready");
               exit();
            } else {
               echo "Error updating salesorder progress: " . $stmt_update->error;
            }
         } else {
            echo "Error executing query: " . $stmt_tally->error;
         }
         $stmt_tally->close();
      } else {
         echo "Error preparing insert query: " . $conn->error;
      }
   } else {
      echo "Error preparing check query: " . $conn->error;
   }

   $conn->close();
}
