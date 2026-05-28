<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// mengambil data dari form
$idrawcategory = $_POST['idrawcategory'];
$nmcategory = $_POST['nmcategory'];

// membuat query untuk memperbarui data category di database
$sql = "UPDATE rawcategory SET nmcategory = '$nmcategory' WHERE idrawcategory = '$idrawcategory'";

// mengeksekusi query
if (mysqli_query($conn, $sql)) {
  echo "<script>alert('Data category berhasil diperbarui.'); window.location='index.php';</script>";
} else {
  echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

// menutup koneksi ke database
mysqli_close($conn);
