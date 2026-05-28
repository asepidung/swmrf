<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Periksa apakah parameter idlabelboning dan idboning telah diterima
if (isset($_GET['id']) && isset($_GET['idboning'])) {
   $idlabelboning = $_GET['id'];
   $idboning = $_GET['idboning'];
   $kdbarcode = $_GET['kdbarcode'];

   // Cek terlebih dahulu apakah kdbarcode masih ada di tabel stock
   $stmtCheckStock = $conn->prepare("SELECT COUNT(*) FROM stock WHERE kdbarcode = ?");
   $stmtCheckStock->bind_param("s", $kdbarcode);
   $stmtCheckStock->execute();
   $stmtCheckStock->bind_result($stockCount);
   $stmtCheckStock->fetch();
   $stmtCheckStock->close();

   // Jika barcode tidak ditemukan di tabel stock, hentikan query dan beri alert
   if ($stockCount == 0) {
      echo "<script>alert('Product Sudah digunakan di proses lain'); window.location='labelboning.php?id=$idboning';</script>";
      exit; // Menghentikan eksekusi lebih lanjut
   }

   // Lakukan soft delete data di tabel labelboning
   $stmtLabelBoning = $conn->prepare("UPDATE labelboning SET is_deleted = 1 WHERE idlabelboning = ?");
   $stmtLabelBoning->bind_param("i", $idlabelboning);
   $stmtLabelBoning->execute();

   // Lakukan hard delete data di tabel stock
   $stmtStock = $conn->prepare("DELETE FROM stock WHERE kdbarcode = ?");
   $stmtStock->bind_param("s", $kdbarcode);
   $stmtStock->execute();

   // Periksa apakah penghapusan data berhasil dilakukan
   if ($stmtStock->affected_rows > 0 && $stmtLabelBoning->affected_rows > 0) {
      // Catat aktivitas ke logactivity setelah data berhasil dihapus
      $idusers = $_SESSION['idusers'];
      $logSql = "INSERT INTO logactivity (iduser, event, docnumb) VALUES (?, 'Hapus Label Boning', ?)";
      $stmtLog = $conn->prepare($logSql);
      $stmtLog->bind_param("is", $idusers, $kdbarcode);
      $stmtLog->execute();

      // Jika berhasil, arahkan kembali ke halaman sebelumnya dengan pesan sukses
      header("Location: labelboning.php?id=$idboning");
      exit;
   } else {
      // Jika gagal, tampilkan pesan error
      echo "<script>alert('Maaf, terjadi kesalahan saat menghapus data.'); window.location='labelboning.php?id=$idboning';</script>";
   }
} else {
   // Jika parameter idlabelboning atau idboning tidak diterima, arahkan kembali ke halaman sebelumnya
   header("Location: labelboning.php?id=$idboning");
   exit;
}
