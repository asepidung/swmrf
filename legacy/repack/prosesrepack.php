<?php
require "../verifications/auth.php";
if (isset($_POST['submit'])) {
   require "../konak/conn.php";
   include "norepack.php";

   // $norepack = $_POST['norepack'];
   $tglrepack = $_POST['tglrepack'];
   $note = $_POST['note'];
   $idusers = $_SESSION['idusers']; // Mendapatkan ID pengguna yang sesuai.

   // Query SQL untuk memasukkan data ke dalam tabel "repack"
   $sql = "INSERT INTO repack (norepack, tglrepack, note, idusers) VALUES (?, ?, ?, ?)";
   $stmt = $conn->prepare($sql);
   $stmt->bind_param("sssi", $norepack, $tglrepack, $note, $idusers);

   if ($stmt->execute()) {
      // Data berhasil dimasukkan, sekarang catat ke logactivity
      $event = "Buat Data Repack";
      $docnumb = $norepack;
      $waktu = date('Y-m-d H:i:s');

      $sql_log = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, ?)";
      $stmt_log = $conn->prepare($sql_log);
      $stmt_log->bind_param("isss", $idusers, $event, $docnumb, $waktu);
      $stmt_log->execute();
      $stmt_log->close();

      // Redirect ke halaman index
      header("Location: index.php");
   } else {
      // Gagal memasukkan data
      echo "Gagal memasukkan data ke dalam tabel repack: " . $stmt->error;
   }

   $stmt->close();
   $conn->close();
} else {
   echo "Akses langsung ke halaman ini tidak diizinkan.";
}
