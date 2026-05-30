<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id']) && isset($_GET['idso'])) {
   $idtally = intval($_GET['id']);
   $idso = intval($_GET['idso']);

   // Cek apakah stat di tabel tally adalah 'DO'
   $stmt_check_stat = $conn->prepare("SELECT stat FROM tally WHERE idtally = ?");
   if (!$stmt_check_stat) {
      die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
   }
   $stmt_check_stat->bind_param("i", $idtally);
   $stmt_check_stat->execute();
   $stmt_check_stat->bind_result($stat);
   $stmt_check_stat->fetch();
   $stmt_check_stat->close();

   if ($stat === 'DO') {
      // Jika stat adalah 'DO', gagalkan proses dan arahkan ke index.php
      echo "<script>alert('DO Sudah Dibuat'); window.location.href = 'index.php';</script>";
      exit();
   } else {
      // Lanjutkan proses Unapprove jika stat bukan 'DO'
      // Prepare statement untuk update tabel tally
      $stmt = $conn->prepare("UPDATE tally SET stat = ? WHERE idtally = ?");
      if (!$stmt) {
         die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
      }

      $status = "";
      $stmt->bind_param("si", $status, $idtally);

      if ($stmt->execute()) {
         // Prepare statement untuk update tabel salesorder
         $stmt_salesorder = $conn->prepare("UPDATE salesorder SET progress = ? WHERE idso = ?");
         if (!$stmt_salesorder) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
         }

         $progress = "On Process";
         $stmt_salesorder->bind_param("si", $progress, $idso);

         if ($stmt_salesorder->execute()) {
            // Redirect ke halaman index.php setelah kedua update berhasil
            header("location: index.php");
            exit();
         } else {
            echo "Error updating salesorder: " . $stmt_salesorder->error;
            exit();
         }
      } else {
         echo "Error updating tally: " . $stmt->error;
         exit();
      }
   }
} else {
   echo "ID tally atau ID salesorder tidak ditemukan.";
   exit();
}

$conn->close();
