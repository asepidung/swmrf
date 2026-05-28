<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "nost.php";

if (isset($_POST['submit'])) {
   $tglst = $_POST['tglst'];
   $note = $_POST['note'];
   $iduser = $_SESSION['idusers'];

   // Buat query INSERT
   $query_st = "INSERT INTO stocktake (nost, tglst, note) VALUES (?, ?, ?)";
   $stmt_st = $conn->prepare($query_st);
   $stmt_st->bind_param("sss", $kodeauto, $tglst, $note);
   $stmt_st->execute();

   // Dapatkan ID terakhir yang di-generate
   $last_id = mysqli_insert_id($conn);

   // Insert ke tabel logactivity
   $event = "Buat Stock Taking";
   $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
   $stmt_log = $conn->prepare($logQuery);
   $stmt_log->bind_param("iss", $iduser, $event, $kodeauto);
   $stmt_log->execute();

   $stmt_st->close();
   $stmt_log->close();
   $conn->close();

   // Redirect ke halaman index.php setelah insert berhasil
   header("location: index.php");
   exit();
}
