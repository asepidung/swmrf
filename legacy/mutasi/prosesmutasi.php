<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "nomutasi.php";

if (isset($_POST['submit'])) {
   $tglmutasi = $_POST['tglmutasi'];
   $note = $_POST['note'];
   $driver = $_POST['driver'];
   $nopol = $_POST['nopol'];
   $gudang = $_POST['gudang'];
   $note = $_POST['note'];
   $iduser = $_SESSION['idusers'];

   // Buat query INSERT
   $query_st = "INSERT INTO mutasi (nomutasi, tglmutasi, note, idusers, gudang, nopol, driver) VALUES (?, ?, ?, ?, ?, ?, ?)";
   $stmt_st = $conn->prepare($query_st);
   $stmt_st->bind_param("sssisss", $kodeauto, $tglmutasi, $note, $iduser, $gudang, $nopol, $driver);
   $stmt_st->execute();

   // Dapatkan ID terakhir yang di-generate
   $last_id = mysqli_insert_id($conn);

   // Insert ke tabel logactivity
   $event = "Buat Mutasi";
   $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
   $stmt_log = $conn->prepare($logQuery);
   $stmt_log->bind_param("iss", $iduser, $event, $kodeauto);
   $stmt_log->execute();

   $stmt_st->close();
   $stmt_log->close();
   $conn->close();

   // Redirect ke halaman detailmutasi dengan menggunakan ID terakhir
   header("location: mutasidetail.php?id=$last_id&stat=ready");
   exit();
}
