<?php
$query_tally = "SELECT tally.*, customers.nama_customer
FROM tally 
INNER JOIN customers ON tally.idcustomer = customers.idcustomer 
WHERE tally.idtally = '$idtally'";
$result_tally = mysqli_query($conn, $query_tally);
$row_tally = mysqli_fetch_assoc($result_tally);

$query_tallydetail = "SELECT tallydetail.*, barang.nmbarang 
      FROM tallydetail 
      INNER JOIN barang ON tallydetail.idbarang = barang.idbarang 
      WHERE idtally = '$idtally'";
$result_tallydetail = mysqli_query($conn, $query_tallydetail);

$productData = [];

while ($row_tallydetail = mysqli_fetch_assoc($result_tallydetail)) {
   $currentProductName = $row_tallydetail['nmbarang'];
   $weight = $row_tallydetail['weight'];

   if (!isset($productData[$currentProductName])) {
      $productData[$currentProductName] = [
         'weights' => [],
         'total' => 0,
      ];
   }

   $productData[$currentProductName]['weights'][] = $weight;
   $productData[$currentProductName]['total'] += $weight;
}


// hitungan tfoot
$totalBoxQuery = "SELECT COUNT(weight) AS total_box FROM tallydetail WHERE idtally = $idtally";
$totalBoxResult = mysqli_query($conn, $totalBoxQuery);
if ($totalBoxResult && $totalBoxRow = mysqli_fetch_assoc($totalBoxResult)) {
   $totalBox = $totalBoxRow['total_box'];
} else {
   $totalBox = 0; // Atur ke 0 jika tidak ada hasil
}

$totalQtyQuery = "SELECT SUM(weight) AS total_qty FROM tallydetail WHERE idtally = $idtally";
$totalQtyResult = mysqli_query($conn, $totalQtyQuery);
if ($totalQtyResult && $totalQtyRow = mysqli_fetch_assoc($totalQtyResult)) {
   $totalQty = $totalQtyRow['total_qty'];
} else {
   $totalQty = 0; // Atur ke 0 jika tidak ada hasil
}
