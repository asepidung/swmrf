<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// mengambil data dari form
$nmgroup = $_POST['nmgroup'];

// Mengecek apakah nama group sudah ada dalam database
$checkQuery = "SELECT nmgroup FROM groupcs WHERE nmgroup = '$nmgroup'";
$checkResult = mysqli_query($conn, $checkQuery);

if (mysqli_num_rows($checkResult) > 0) {
   // Nama group sudah ada dalam database, tampilkan peringatan
   echo "<script>alert('Nama group sudah ada dalam database.');</script>";
   // Redirect atau lakukan tindakan lain sesuai kebutuhan
   echo "<script>window.location='newgroup.php';</script>";
} else {
   // Nama group belum ada dalam database, lanjutkan dengan penyimpanan

   // membuat query untuk menyimpan data ke database
   $sql = "INSERT INTO groupcs (nmgroup) VALUES ('$nmgroup')";

   // mengeksekusi query
   if (mysqli_query($conn, $sql)) {
      echo "<script>alert('Data berhasil disimpan.'); window.location='../customer/newcustomer.php';</script>";
   } else {
      echo "Error: " . $sql . "<br>" . mysqli_error($conn);
   }
}

// menutup koneksi ke database
mysqli_close($conn);
