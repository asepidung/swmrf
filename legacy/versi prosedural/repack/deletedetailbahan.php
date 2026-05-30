<?php
require "../verifications/auth.php";

// Koneksi ke database
require "../konak/conn.php";

if (isset($_GET['id']) && isset($_GET['iddetail'])) {
   $id = $_GET['id'];
   $iddetail = $_GET['iddetail'];
   $iduser = $_SESSION['idusers']; // Ambil ID user dari sesi yang aktif

   // Ambil data dari tabel detailbahan berdasarkan iddetailbahan
   $getDataQuery = "SELECT * FROM detailbahan WHERE iddetailbahan = ?";
   $getDataStmt = $conn->prepare($getDataQuery);

   if ($getDataStmt) {
      $getDataStmt->bind_param('i', $iddetail);
      $getDataStmt->execute();
      $result = $getDataStmt->get_result();
      $detailbahanData = $result->fetch_assoc();

      // Insert data ke tabel stock
      $insertStockQuery = "INSERT INTO stock (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin) VALUES (?, ?, ?, ?, ?, ?, ?)";
      $insertStockStmt = $conn->prepare($insertStockQuery);

      if ($insertStockStmt) {
         $insertStockStmt->bind_param('siidisi', $detailbahanData['barcode'], $detailbahanData['idgrade'], $detailbahanData['idbarang'], $detailbahanData['qty'], $detailbahanData['pcs'], $detailbahanData['pod'], $detailbahanData['origin']);
         $insertStockStmt->execute();

         // Hapus data dari tabel detailbahan
         $deleteQuery = "DELETE FROM detailbahan WHERE iddetailbahan = ?";
         $deleteStmt = $conn->prepare($deleteQuery);

         if ($deleteStmt) {
            $deleteStmt->bind_param('i', $iddetail);
            $deleteStmt->execute();

            // Catat log aktivitas setelah penghapusan berhasil
            $event = "Hapus Bahan Repack";
            $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
            $logStmt = $conn->prepare($logQuery);
            $logStmt->bind_param('iss', $iduser, $event, $detailbahanData['barcode']);
            $logStmt->execute();

            // Redirect ke halaman detailbahan.php dengan status "deleted"
            header("Location: detailbahan.php?id=$id&stat=deleted");
         } else {
            // Jika gagal, tampilkan pesan error
            echo "<script>alert('Maaf, terjadi kesalahan saat menghapus data.'); window.location='detailbahan.php?id=$id';</script>";
         }
      } else {
         // Jika gagal, tampilkan pesan error
         echo "<script>alert('Maaf, terjadi kesalahan saat menyisipkan data ke tabel stock.'); window.location='detailbahan.php?id=$id';</script>";
      }
   } else {
      // Jika gagal, tampilkan pesan error
      echo "<script>alert('Maaf, terjadi kesalahan saat mengambil data.'); window.location='detailbahan.php?id=$id';</script>";
   }
} else {
   // Jika tidak ada ID atau IDDetail yang diberikan, tampilkan pesan atau arahkan ke halaman kesalahan
   echo "ID atau IDDetail tidak valid.";
}
