<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mendapatkan ID pricelist dari URL
$idpricelist = $_GET['idpricelist'];

// Hapus pricelistdetail terlebih dahulu
$queryDeleteDetail = "DELETE FROM pricelistdetail WHERE idpricelist = ?";
$stmtDeleteDetail = $conn->prepare($queryDeleteDetail);
$stmtDeleteDetail->bind_param("i", $idpricelist);

if ($stmtDeleteDetail->execute()) {
   // Setelah pricelistdetail dihapus, hapus pricelist
   $queryDeletePricelist = "DELETE FROM pricelist WHERE idpricelist = ?";
   $stmtDeletePricelist = $conn->prepare($queryDeletePricelist);
   $stmtDeletePricelist->bind_param("i", $idpricelist);

   if ($stmtDeletePricelist->execute()) {
      echo "<script>alert('Pricelist berhasil dihapus.'); window.location='index.php';</script>";
   } else {
      echo "<script>alert('Gagal menghapus pricelist.'); window.location='index.php';</script>";
   }
} else {
   echo "<script>alert('Gagal menghapus pricelistdetail.'); window.location='index.php';</script>";
}

$stmtDeleteDetail->close();
$stmtDeletePricelist->close();
$conn->close();
