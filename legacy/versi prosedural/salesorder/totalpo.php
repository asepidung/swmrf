<?php
$totalPOQuery = "SELECT SUM(weight) AS total_weight FROM salesorderdetail WHERE idso = $idso";
$totalPOResult = mysqli_query($conn, $totalPOQuery);
if ($totalPOResult && $totalPORow = mysqli_fetch_assoc($totalPOResult)) {
   $totalPO = $totalPORow['total_weight'];
} else {
   $totalPO = 0; // Atur ke 0 jika tidak ada hasil
}
