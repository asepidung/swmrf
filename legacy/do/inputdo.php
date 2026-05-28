<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "donumber.php";

if (isset($_POST['submit'])) {
   $donumber = $kodeauto;
   $deliverydate = $_POST['deliverydate'];
   $idcustomer = $_POST['idcustomer'];
   $po = $_POST['po'];
   $driver = $_POST['driver'];
   $plat = $_POST['plat'];
   $xbox = $_POST['xbox'];
   $xweight = $_POST['xweight'];
   $status = "Unapproved";
   $note = $_POST['note'];
   $idso = $_POST['idso'];
   $sealnumb = $_POST['sealnumb'];
   $idtally = $_POST['idtally'];
   $idusers = $_SESSION['idusers'];

   // Cek apakah idtally atau idso sudah ada di tabel DO dengan is_deleted = 0
   $query_check = "SELECT COUNT(*) as count FROM do WHERE (idtally = ? OR idso = ?) AND is_deleted = 0";
   $stmt_check = $conn->prepare($query_check);
   $stmt_check->bind_param("ii", $idtally, $idso);
   $stmt_check->execute();
   $result_check = $stmt_check->get_result();
   $row_check = $result_check->fetch_assoc();
   $stmt_check->close();

   if ($row_check['count'] > 0) {
      // Jika sudah ada, hentikan proses
      echo "<script>alert('DO Sudah dibuat.'); window.location='do.php';</script>";
      exit();
   }

   $query_do = "INSERT INTO do (donumber, idso, idtally, deliverydate, idcustomer, po, driver, plat, note, xbox, xweight, status, idusers, sealnumb) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
   $stmt_do = $conn->prepare($query_do);
   $stmt_do->bind_param("siisissssidsis", $donumber, $idso, $idtally, $deliverydate, $idcustomer, $po, $driver, $plat, $note, $xbox, $xweight, $status, $idusers, $sealnumb);
   if ($stmt_do->execute()) {
      // Eksekusi berhasil
      $last_id = $stmt_do->insert_id;

      // Update tabel tally kolom stat menjadi 'DO'
      $query_update_tally = "UPDATE tally SET stat = 'DO' WHERE idtally = ?";
      $stmt_update_tally = $conn->prepare($query_update_tally);
      $stmt_update_tally->bind_param("i", $idtally);
      $stmt_update_tally->execute();
      $stmt_update_tally->close();

      // Log activity untuk Buat DO
      $event = "Buat DO";
      $query_log = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
      $stmt_log = $conn->prepare($query_log);
      $stmt_log->bind_param("iss", $idusers, $event, $donumber);
      $stmt_log->execute();
      $stmt_log->close();
   } else {
      // Eksekusi gagal, tampilkan pesan kesalahan
      echo "Error: " . $stmt_do->error;
   }

   $idbarang = $_POST['idbarang'];
   $box = $_POST['box'];
   $weight = $_POST['weight'];
   $notes = $_POST['notes'];

   $query_dodetail = "INSERT INTO dodetail (iddo, idbarang, box, weight, notes) VALUES (?,?,?,?,?)";
   $stmt_dodetail = $conn->prepare($query_dodetail);

   for ($i = 0; $i < count($idbarang); $i++) {
      $stmt_dodetail->bind_param("iiids", $last_id, $idbarang[$i], $box[$i], $weight[$i], $notes[$i]);
      $stmt_dodetail->execute();
   }

   $query_update_salesorder = "UPDATE salesorder SET progress = 'On Delivery' WHERE idso = ?";
   $stmt_update_salesorder = $conn->prepare($query_update_salesorder);
   $stmt_update_salesorder->bind_param("i", $idso);
   $stmt_update_salesorder->execute();
   $stmt_update_salesorder->close();
   $stmt_dodetail->close();
   $stmt_do->close();
   $conn->close();

   header("location: do.php");
}
