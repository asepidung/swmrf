<?php
require "../verifications/auth.php";
require "../konak/conn.php";
if (isset($_POST['barcode'])) {
   $barcode = $_POST['barcode'];
   $idrepack = $_POST['idrepack'];

   // Langsung query ke tabel detailbahan berdasarkan barcode
   $cekBarcodeQuery = "SELECT iddetailbahan FROM detailbahan WHERE idrepack = $idrepack AND barcode = '$barcode'";
   $cekBarcodeResult = mysqli_query($conn, $cekBarcodeQuery);

   if (mysqli_num_rows($cekBarcodeResult) > 0) {
      // Barcode sudah ada di tabel detailbahan, arahkan kembali ke halaman dengan status "duplicate"
      header("location: detailbahan.php?id=$idrepack&stat=duplicate");
      exit;
   } else {
      // Barcode belum ada di tabel detailbahan, lanjutkan dengan pengecekan di tabel stock
      $query = "SELECT idbarang, idgrade, qty, pcs, pod, origin FROM stock WHERE kdbarcode = '$barcode'";
      $result = mysqli_query($conn, $query);

      if ($result && $row = mysqli_fetch_assoc($result)) {
         $idbarang = $row['idbarang'];
         $idgrade = $row['idgrade'];
         $qty = $row['qty'];
         $pcs = ($row['pcs'] === null || $row['pcs'] === '') ? 0 : (int)$row['pcs'];
         $pod = $row['pod'];
         $origin = $row['origin'];

         // Lanjutkan dengan operasi insert ke tabel detailbahan
         $insertQuery = "INSERT INTO detailbahan (idrepack, barcode, idbarang, idgrade, qty, pcs, pod, origin) VALUES ('$idrepack', '$barcode', '$idbarang',  '$idgrade', '$qty', '$pcs', '$pod', '$origin')";
         mysqli_query($conn, $insertQuery);

         // Hapus data dari tabel stock
         $deleteQuery = "DELETE FROM stock WHERE kdbarcode = '$barcode'";
         mysqli_query($conn, $deleteQuery);

         // Redirect kembali ke halaman detailbahan.php dengan status "success"
         header("location: detailbahan.php?id=$idrepack&stat=success");
         exit;
      } else {
         // Barcode tidak ditemukan di tabel stock
         $_SESSION['barcode'] = $barcode;
         header("location: detailbahan.php?id=$idrepack&stat=unknown");
         exit;
      }
   }
}
