<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Cek apakah form sudah disubmit
if (isset($_POST['submit'])) {
  $idpricelist = $_POST['idpricelist'];
  $idgroup = $_POST['idgroup'];
  $note = $_POST['note'];
  $up = $_POST['up'];
  $latestupdate = date('Y-m-d');

  // Update tabel pricelist
  $update_query = "UPDATE pricelist SET idgroup = '$idgroup', note = '$note', up = '$up', latestupdate = '$latestupdate' WHERE idpricelist = '$idpricelist'";
  $result = mysqli_query($conn, $update_query);

  if ($result) {
    // Hapus semua data pricelistdetail yang terkait dengan idpricelist
    $delete_query = "DELETE FROM pricelistdetail WHERE idpricelist = '$idpricelist'";
    $delete_result = mysqli_query($conn, $delete_query);

    if ($delete_result) {
      // Masukkan ulang data pricelistdetail berdasarkan idpricelist
      $idbarang = $_POST['idbarang'];
      $price = $_POST['price'];
      $notes = $_POST['notes'];

      for ($i = 0; $i < count($idbarang); $i++) {
        $idbarang_item = $idbarang[$i];
        $price_item = $price[$i];
        $notes_item = $notes[$i];

        $insert_query = "INSERT INTO pricelistdetail (idpricelist, idbarang, price, notes) VALUES ('$idpricelist', '$idbarang_item', '$price_item', '$notes_item')";
        mysqli_query($conn, $insert_query);
      }

      // Redirect ke halaman lain atau tampilkan pesan sukses
      header("location: index.php");
    } else {
      echo "Gagal menghapus data pricelistdetail.";
    }
  } else {
    echo "Gagal mengupdate data pricelist.";
  }
}
