<?php
require "../konak/conn.php";

if (isset($_POST['idbarang']) && isset($_POST['idgroup'])) {
   $idbarang = $_POST['idbarang'];
   $idgroup = $_POST['idgroup'];
   $query = "SELECT price FROM pricelistdetail WHERE idbarang = $idbarang AND idpricelist IN (SELECT idpricelist FROM pricelist WHERE idgroup = $idgroup)";
   $result = mysqli_query($conn, $query);

   if ($result) {
      $row = mysqli_fetch_assoc($result);
      echo $row['price'];
   } else {
      echo "Error: " . $query . "<br>" . mysqli_error($conn);
   }
} else {
   echo "idbarang or idgroup is not set";
}
