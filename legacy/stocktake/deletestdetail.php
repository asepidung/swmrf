<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id']) && isset($_GET['iddetail'])) {
   $id = intval($_GET['id']);
   $iddetail = intval($_GET['iddetail']);

   // Mulai transaksi
   mysqli_begin_transaction($conn);

   try {
      // Ambil kdbarcode dari tabel stocktakedetail berdasarkan idstdetail
      $query_kdbarcode = "SELECT kdbarcode FROM stocktakedetail WHERE idstdetail = ?";
      $stmt_kdbarcode = $conn->prepare($query_kdbarcode);
      $stmt_kdbarcode->bind_param("i", $iddetail);
      $stmt_kdbarcode->execute();
      $result_kdbarcode = $stmt_kdbarcode->get_result();

      if ($row_kdbarcode = $result_kdbarcode->fetch_assoc()) {
         $kdbarcode = $row_kdbarcode['kdbarcode'];
         $stmt_kdbarcode->close();

         // Cek apakah barcode ada di manualstock
         $query_check_manualstock = "SELECT COUNT(*) AS count FROM manualstock WHERE kdbarcode = ?";
         $stmt_check = $conn->prepare($query_check_manualstock);
         $stmt_check->bind_param("s", $kdbarcode);
         $stmt_check->execute();
         $result_check = $stmt_check->get_result();
         $row_check = $result_check->fetch_assoc();
         $stmt_check->close();

         // Jika barcode ditemukan di manualstock, hapus juga di manualstock
         if ($row_check['count'] > 0) {
            $query_delete_manualstock = "DELETE FROM manualstock WHERE kdbarcode = ?";
            $stmt_delete_manual = $conn->prepare($query_delete_manualstock);
            $stmt_delete_manual->bind_param("s", $kdbarcode);
            $stmt_delete_manual->execute();
            $stmt_delete_manual->close();
         }

         // Hapus data dari stocktakedetail
         $query_delete_stocktakedetail = "DELETE FROM stocktakedetail WHERE idstdetail = ?";
         $stmt_delete_stock = $conn->prepare($query_delete_stocktakedetail);
         $stmt_delete_stock->bind_param("i", $iddetail);
         $stmt_delete_stock->execute();
         $stmt_delete_stock->close();

         // Insert ke tabel logactivity
         $event = "Delete Detail ST";
         $iduser = $_SESSION['idusers'];
         $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
         $stmt_log = $conn->prepare($logQuery);
         $stmt_log->bind_param("iss", $iduser, $event, $kdbarcode);
         $stmt_log->execute();
         $stmt_log->close();

         // Commit transaksi jika semua berhasil
         mysqli_commit($conn);

         // Redirect ke halaman starttaking.php setelah berhasil menghapus data
         header("Location: starttaking.php?id=$id&stat=deleted");
         exit;
      } else {
         throw new Exception("Data tidak ditemukan.");
      }
   } catch (Exception $e) {
      // Rollback jika ada error
      mysqli_rollback($conn);
      echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='starttaking.php?id=$id';</script>";
   }
}
