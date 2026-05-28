<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // Tangkap data dari formulir
   $idrepack = $_POST['idrepack'];
   $tglrepack = $_POST['tglrepack'];
   $note = $_POST['note'];
   $norepack = $_POST['norepack'];
   $iduser = $_SESSION['idusers']; // Ambil ID user dari sesi yang aktif

   // Query untuk mengupdate data repack
   $updateRepackQuery = "UPDATE repack SET tglrepack = '$tglrepack', note = '$note' WHERE idrepack = $idrepack";

   // Jalankan query update
   $result = mysqli_query($conn, $updateRepackQuery);

   if ($result) {
      // Jika update berhasil, catat log aktivitas
      $event = "Update Data Repack";
      $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES ('$iduser', '$event', '$norepack', NOW())";
      mysqli_query($conn, $logQuery);

      // Redirect ke halaman lain jika update dan log berhasil
      header("location: index.php");
   } else {
      // Handle kesalahan update
      die("Query Error: " . mysqli_error($conn));
   }
}
