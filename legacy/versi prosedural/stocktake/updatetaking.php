<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $idst = intval($_POST['idst']);
   $tglst = $_POST['tglst'];
   $note = $_POST['note'];

   if ($idst > 0 && !empty($tglst)) {
      $query = "UPDATE stocktake SET tglst = ?, note = ? WHERE idst = ?";
      $stmt = mysqli_prepare($conn, $query);

      if ($stmt) {
         mysqli_stmt_bind_param($stmt, "ssi", $tglst, $note, $idst);
         $result = mysqli_stmt_execute($stmt);

         if ($result) {
            $_SESSION['message'] = "Stock taking record updated successfully.";
         } else {
            $_SESSION['error'] = "Failed to update stock taking record.";
         }

         mysqli_stmt_close($stmt);
      } else {
         $_SESSION['error'] = "Failed to prepare the update statement.";
      }
   } else {
      $_SESSION['error'] = "Invalid data provided.";
   }

   header("location: index.php");
   exit;
} else {
   header("location: edit_page.php");
   exit;
}
