<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   // Validasi ID Mutasi
   $idmutasi = isset($_POST['idmutasi']) ? intval($_POST['idmutasi']) : 0;

   if ($idmutasi <= 0) {
      echo "Invalid ID Mutasi. <a href='javascript:history.back()'>Kembali</a>";
      exit;
   }

   // Validasi Tindakan (idgrade)
   $idgrade = isset($_POST['tindakan']) ? intval($_POST['tindakan']) : 0;

   if ($idgrade <= 0) {
      echo "Invalid Tindakan. <a href='javascript:history.back()'>Kembali</a>";
      exit;
   }

   // Update idgrade pada semua data di tabel mutasidetail yang memiliki idmutasi $idmutasi
   $updateQuery = "UPDATE mutasidetail SET idgrade = $idgrade WHERE idmutasi = $idmutasi";
   $updateResult = mysqli_query($conn, $updateQuery);

   // ...

   if ($updateResult) {
      // Update idgrade pada tabel mutasidetail berhasil, sekarang update juga tabel stock
      $updateStockQuery = "
      UPDATE stock
      SET idgrade = $idgrade
      WHERE kdbarcode IN (
         SELECT kdbarcode
         FROM mutasidetail
         WHERE idmutasi = $idmutasi
      )";
      $updateStockResult = mysqli_query($conn, $updateStockQuery);

      if ($updateStockResult) {
         // Redirect kembali ke halaman sebelumnya dengan status "success"
         header("location: mutasidetail.php?id=$idmutasi&stat=success");
      } else {
         // Tampilkan pesan kesalahan jika terjadi masalah saat mengupdate data di tabel stock
         echo "Terjadi kesalahan saat mengupdate data di tabel stock. <a href='javascript:history.back()'>Kembali</a>";
      }
   } else {
      // Tampilkan pesan kesalahan jika terjadi masalah saat mengupdate data di tabel mutasidetail
      echo "Terjadi kesalahan saat mengupdate data di tabel mutasidetail. <a href='javascript:history.back()'>Kembali</a>";
   }
}
