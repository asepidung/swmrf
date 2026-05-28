<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['idso'])) {
   $idsalesorder = $_GET['idso'];

   // Ambil sonumber sebelum dihapus
   $query_get_sonumber = "SELECT sonumber FROM salesorder WHERE idso = ?";
   $stmt_get_sonumber = $conn->prepare($query_get_sonumber);
   $stmt_get_sonumber->bind_param("i", $idsalesorder);
   $stmt_get_sonumber->execute();
   $stmt_get_sonumber->bind_result($sonumber);
   $stmt_get_sonumber->fetch();
   $stmt_get_sonumber->close();

   // Hapus data dari tabel plandev terlebih dahulu
   $query_delete_plandev = "DELETE FROM plandev WHERE idso = ?";
   $stmt_delete_plandev = $conn->prepare($query_delete_plandev);
   $stmt_delete_plandev->bind_param("i", $idsalesorder);
   $stmt_delete_plandev->execute();
   $stmt_delete_plandev->close();

   // Soft delete data dari tabel salesorder (set is_deleted = 1)
   $query_soft_delete_salesorder = "UPDATE salesorder SET is_deleted = 1 WHERE idso = ?";
   $stmt_soft_delete_salesorder = $conn->prepare($query_soft_delete_salesorder);
   $stmt_soft_delete_salesorder->bind_param("i", $idsalesorder);
   $stmt_soft_delete_salesorder->execute();
   $stmt_soft_delete_salesorder->close();

   // Insert log activity into logactivity table
   $idusers = $_SESSION['idusers'];
   $event = "Delete Sales Order";
   $logQuery = "INSERT INTO logactivity (iduser, docnumb, event, waktu) 
                VALUES (?, ?, ?, NOW())";
   $stmt_log = $conn->prepare($logQuery);
   $stmt_log->bind_param("iss", $idusers, $sonumber, $event);
   $stmt_log->execute();
   $stmt_log->close();
}

header("location: index.php"); // Redirect to the list page
exit();
