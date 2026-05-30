<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

   $idso         = $_POST["idso"];
   $sonumber     = $_POST["sonumber"];
   $idcustomer   = $_POST["idcustomer"];
   $deliverydate = $_POST["deliverydate"];
   $po           = $_POST["po"];
   $alamat       = $_POST["alamat"];
   $note         = $_POST["note"];
   $idbarang     = $_POST["idbarang"];
   $weight       = $_POST["weight"];
   $price        = $_POST["price"];
   $discount     = $_POST["discount"];
   $notes        = $_POST["notes"];
   $idusers      = $_SESSION['idusers'];

   /* =========================
      VALIDASI DASAR
      ========================= */
   if (!$idso || !$idcustomer || !$deliverydate || count($idbarang) == 0) {
      echo "Data tidak lengkap!";
      exit;
   }

   /* =========================
      UPDATE SALES ORDER (HEADER)
      ========================= */
   $updateSalesOrderQuery = "
      UPDATE salesorder 
      SET 
         idcustomer = ?, 
         deliverydate = ?, 
         po = ?, 
         alamat = ?, 
         note = ?
      WHERE idso = ?
   ";
   $stmt = $conn->prepare($updateSalesOrderQuery);
   $stmt->bind_param(
      "issssi",
      $idcustomer,
      $deliverydate,
      $po,
      $alamat,
      $note,
      $idso
   );
   $stmt->execute();
   $stmt->close();

   /* =========================
      RESET & INSERT DETAIL
      ========================= */
   $conn->query("DELETE FROM salesorderdetail WHERE idso = $idso");

   $insertQuery = "
      INSERT INTO salesorderdetail 
      (idso, idbarang, weight, price, discount, notes) 
      VALUES (?, ?, ?, ?, ?, ?)
   ";
   $stmtDetail = $conn->prepare($insertQuery);

   for ($i = 0; $i < count($idbarang); $i++) {
      $idbrg = intval($idbarang[$i]);
      $w     = floatval($weight[$i]);
      $p     = floatval(str_replace(',', '', $price[$i]));
      $d     = floatval($discount[$i]);
      $n     = trim($notes[$i]);

      $stmtDetail->bind_param(
         "iiidds",
         $idso,
         $idbrg,
         $w,
         $p,
         $d,
         $n
      );
      $stmtDetail->execute();
   }
   $stmtDetail->close();

   /* =========================
      LOG ACTIVITY
      ========================= */
   $event = "Edit Sales Order";
   $stmtLog = $conn->prepare("
      INSERT INTO logactivity (iduser, docnumb, event, waktu) 
      VALUES (?, ?, ?, NOW())
   ");
   $stmtLog->bind_param("iss", $idusers, $sonumber, $event);
   $stmtLog->execute();
   $stmtLog->close();

   $conn->close();

   header("Location: index.php");
   exit();
} else {
   echo "Akses tidak sah.";
   exit();
}
