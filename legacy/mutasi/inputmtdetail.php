<?php
require "../verifications/auth.php";
require "../konak/conn.php";
if (isset($_POST['kdbarcode']) && isset($_POST['idmutasi'])) {
   $kdbarcode = $_POST['kdbarcode'];
   $id = $_POST['idmutasi'];

   // Selanjutnya, kita akan melakukan pengecekan apakah $kdbarcode sudah ada di tabel mutasidetail
   $cekBarcodeQuery = "SELECT kdbarcode FROM mutasidetail WHERE kdbarcode = '$kdbarcode' AND idmutasi = '$id'";
   $cekBarcodeResult = mysqli_query($conn, $cekBarcodeQuery);

   if (mysqli_num_rows($cekBarcodeResult) > 0) {
      // Barcode sudah ada di tabel mutasidetail untuk idmutasi yang sama
      header("location: mutasidetail.php?id=$id&stat=duplicate");
      exit;
   }

   // Langsung query ke tabel stock berdasarkan kdbarcode
   $query = "SELECT idbarang, idgrade, qty, pcs, pod, origin FROM stock WHERE kdbarcode = '$kdbarcode'";

   // Eksekusi query
   $result = mysqli_query($conn, $query);

   if ($result && $row = mysqli_fetch_assoc($result)) {
      $idbarang = $row['idbarang'];
      $idgrade = $row['idgrade'];
      $qty = $row['qty']; // Menyesuaikan nama kolom di tabel
      $pcs = $row['pcs'];
      $pod = $row['pod'];

      // Barcode belum ada di tabel mutasidetail untuk idmutasi yang sama, lanjutkan dengan query insert
      $insertQuery = "INSERT INTO mutasidetail (idmutasi, kdbarcode, idbarang, idgrade, qty, pcs, pod) VALUES ('$id', '$kdbarcode', '$idbarang',  '$idgrade', '$qty', '$pcs', '$pod')";
      mysqli_query($conn, $insertQuery);

      // Hapus data dari tabel stock
      $updateQuery = "UPDATE stock SET idgrade = '$idgrade' WHERE kdbarcode = '$kdbarcode'";
      mysqli_query($conn, $updateQuery);

      // Redirect kembali ke halaman mutasidetail.php dengan status "success"
      header("location: mutasidetail.php?id=$id&stat=success");
   } else {
      // Barcode tidak ditemukan di tabel stock
      $_SESSION['kdbarcode'] = $kdbarcode;
      header("location: mutasidetail.php?id=$id&stat=unknown");
      exit;
   }
}
