<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// mengambil data dari form
$nmsupplier = $_POST['nmsupplier'];
$alamat = $_POST['alamat'];
$jenis_usaha = $_POST['jenis_usaha'];
$telepon = $_POST['telepon'];
$npwp = $_POST['npwp'];

// membuat query untuk menyimpan data ke database
$sql = "INSERT INTO supplier (nmsupplier, alamat, jenis_usaha, telepon, npwp)
            VALUES ('$nmsupplier', '$alamat', '$jenis_usaha', '$telepon', '$npwp')";

// mengeksekusi query
if (mysqli_query($conn, $sql)) {
   echo "<script>alert('Data berhasil disimpan.'); window.location='supplier.php';</script>";
} else {
   echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

// menutup koneksi ke database
mysqli_close($conn);
