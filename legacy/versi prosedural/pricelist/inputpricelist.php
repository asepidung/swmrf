<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_POST['submit'])) {

   $idgroup = $_POST['idgroup'];
   $note = $_POST['note'];
   $up = $_POST['up'];
   $idusers = $_SESSION['idusers'];
   $latestupdate = date('Y-m-d');

   $query_pricelist = "INSERT INTO pricelist (idgroup, note, idusers, latestupdate, up) VALUES (?,?,?,?,?)";
   $stmt_pricelist = $conn->prepare($query_pricelist);
   $stmt_pricelist->bind_param("isiss", $idgroup, $note, $idusers, $latestupdate, $up);
   $stmt_pricelist->execute();

   $last_id = $stmt_pricelist->insert_id;

   $idbarang = $_POST['idbarang'];
   $price = $_POST['price'];
   $notes = $_POST['notes'];

   $query_pricelistdetail = "INSERT INTO pricelistdetail (idpricelist, idbarang, price, notes) VALUES (?,?,?,?)";
   $stmt_pricelistdetail = $conn->prepare($query_pricelistdetail);

   for ($i = 0; $i < count($idbarang); $i++) {
      $stmt_pricelistdetail->bind_param("iiis", $last_id, $idbarang[$i], $price[$i], $notes[$i]);
      $stmt_pricelistdetail->execute();
   }

   $stmt_pricelistdetail->close();
   $stmt_pricelist->close();
   $conn->close();

   header("location: index.php");
}
