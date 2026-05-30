<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "sonumber.php";

if (isset($_POST['submit'])) {

   $sonumber     = $kodeauto;
   $idcustomer   = $_POST['idcustomer'];
   $deliverydate = $_POST['deliverydate'];
   $po           = $_POST['po'];
   $alamat       = $_POST['alamat'];
   $note         = $_POST['note'];
   $progress     = 'Waiting';
   $idusers      = $_SESSION['idusers'];

   /* =========================
      INSERT SALES ORDER (HEADER)
      ========================= */
   $query_so = "
      INSERT INTO salesorder 
      (sonumber, idcustomer, deliverydate, po, alamat, note, progress, idusers) 
      VALUES (?,?,?,?,?,?,?,?)
   ";
   $stmt_so = $conn->prepare($query_so);
   $stmt_so->bind_param(
      "sisssssi",
      $sonumber,
      $idcustomer,
      $deliverydate,
      $po,
      $alamat,
      $note,
      $progress,
      $idusers
   );
   $stmt_so->execute();

   $last_id = $stmt_so->insert_id;

   /* =========================
      INSERT SALES ORDER DETAIL
      ========================= */
   $idbarang = $_POST['idbarang'];
   $weight   = $_POST['weight'];
   $discount = $_POST['discount'];
   $notes    = $_POST['notes'];

   $price = array_map(function ($value) {
      return str_replace(',', '', $value);
   }, $_POST['price']);

   $query_sodetail = "
      INSERT INTO salesorderdetail 
      (idso, idbarang, weight, price, discount, notes) 
      VALUES (?,?,?,?,?,?)
   ";
   $stmt_sodetail = $conn->prepare($query_sodetail);

   for ($i = 0; $i < count($idbarang); $i++) {
      $stmt_sodetail->bind_param(
         "iiiiis",
         $last_id,
         $idbarang[$i],
         $weight[$i],
         $price[$i],
         $discount[$i],
         $notes[$i]
      );
      $stmt_sodetail->execute();
   }

   /* =========================
      LOG ACTIVITY
      ========================= */
   $event = "Buat Sales Order";
   $logQuery = "
      INSERT INTO logactivity (iduser, docnumb, event, waktu) 
      VALUES (?, ?, ?, NOW())
   ";
   $stmt_log = $conn->prepare($logQuery);
   $stmt_log->bind_param("iss", $idusers, $sonumber, $event);
   $stmt_log->execute();

   /* =========================
      CLEAN UP
      ========================= */
   $stmt_sodetail->close();
   $stmt_so->close();
   $stmt_log->close();
   $conn->close();

   header("Location: index.php");
   exit();
}
