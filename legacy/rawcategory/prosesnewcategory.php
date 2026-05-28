<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// mengambil data dari form
$nmcategory = $_POST['nmcategory'];

// Mengecek apakah nama category sudah ada dalam database
$checkQuery = "SELECT nmcategory FROM rawcategory WHERE nmcategory = '$nmcategory'";
$checkResult = mysqli_query($conn, $checkQuery);

if (mysqli_num_rows($checkResult) > 0) {
   // Nama category sudah ada dalam database, tampilkan peringatan
   echo "<script>alert('Nama category sudah ada dalam database.');</script>";
   // Redirect atau lakukan tindakan lain sesuai kebutuhan
   echo "<script>window.location='newcategory.php';</script>";
} else {
   // Nama category belum ada dalam database, lanjutkan dengan penyimpanan

   // membuat query untuk menyimpan data ke database
   $sql = "INSERT INTO rawcategory (nmcategory) VALUES ('$nmcategory')";

   // mengeksekusi query
   if (mysqli_query($conn, $sql)) {
      echo "<script>alert('Data berhasil disimpan.'); window.location='index.php';</script>";
   } else {
      echo "Error: " . $sql . "<br>" . mysqli_error($conn);
   }
}

// menutup koneksi ke database
mysqli_close($conn);
