<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id']) && isset($_GET['iddetail'])) {
   $id = $_GET['id'];
   $iddetail = $_GET['iddetail'];
   $iduser = $_SESSION['idusers']; // Mengambil iduser dari session
   $event = "Delete Tally Detail"; // Event logactivity

   // Set autocommit ke false untuk memulai transaksi
   $conn->autocommit(false);

   try {
      // Ambil data dari tabel tallydetail berdasarkan idtallydetail
      $querySelect = "SELECT barcode, idgrade, idbarang, weight, pcs, pod, origin 
                      FROM tallydetail 
                      WHERE idtallydetail = ?";
      $stmtSelect = $conn->prepare($querySelect);

      // Periksa apakah query prepare berhasil
      if (!$stmtSelect) {
         throw new Exception("Query Select Prepare Error: " . $conn->error);
      }

      $stmtSelect->bind_param('i', $iddetail);
      $stmtSelect->execute();
      $result = $stmtSelect->get_result();
      $tallyDetailData = $result->fetch_assoc();

      // Insert data ke tabel stock
      $queryInsert = "INSERT INTO stock (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
      $stmtInsert = $conn->prepare($queryInsert);

      // Periksa apakah query prepare berhasil
      if (!$stmtInsert) {
         throw new Exception("Query Insert Prepare Error: " . $conn->error);
      }

      // Sesuaikan nama kolom 'weight' dengan 'qty', 'barcode' dengan 'kdbarcode' dalam bind_param
      $stmtInsert->bind_param('siidisi', $tallyDetailData['barcode'], $tallyDetailData['idgrade'], $tallyDetailData['idbarang'], $tallyDetailData['weight'], $tallyDetailData['pcs'], $tallyDetailData['pod'], $tallyDetailData['origin']);
      $stmtInsert->execute();

      // Hapus data dari tabel tallydetail
      $queryDelete = "DELETE FROM tallydetail WHERE idtallydetail = ?";
      $stmtDelete = $conn->prepare($queryDelete);

      // Periksa apakah query prepare berhasil
      if (!$stmtDelete) {
         throw new Exception("Query Delete Prepare Error: " . $conn->error);
      }

      $stmtDelete->bind_param('i', $iddetail);
      $stmtDelete->execute();

      // Insert log activity ke tabel logactivity
      $docnumb = $tallyDetailData['barcode']; // Menggunakan barcode sebagai docnumb
      $queryLogActivity = "INSERT INTO logactivity (iduser, event, docnumb) VALUES (?, ?, ?)";
      $stmtLogActivity = $conn->prepare($queryLogActivity);

      if (!$stmtLogActivity) {
         throw new Exception("Query LogActivity Prepare Error: " . $conn->error);
      }

      $stmtLogActivity->bind_param('iss', $iduser, $event, $docnumb);
      $stmtLogActivity->execute();

      // Commit transaksi jika semua query berhasil dieksekusi
      $conn->commit();

      // Kembalikan ke halaman tallydetail.php
      header("location: tallydetail.php?id=$id&stat=deleted");
   } catch (Exception $e) {
      // Rollback transaksi jika terjadi kesalahan
      $conn->rollback();
      echo "Error: " . $e->getMessage();
   } finally {
      // Set autocommit kembali ke true setelah selesai
      $conn->autocommit(true);
   }
} else {
   // Jika tidak ada ID atau IDDetail yang diberikan, tampilkan pesan atau arahkan ke halaman kesalahan
   echo "ID atau IDDetail tidak valid.";
}
