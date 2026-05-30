<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// mengambil data dari form
$nmsegment = $_POST['nmsegment'];
$banksegment = $_POST['banksegment'];
$accname = $_POST['accname'];
$accnumber = $_POST['accnumber'];

// membuat query untuk menyimpan data ke database
$sql = "INSERT INTO segment (nmsegment, banksegment, accname, accnumber)
            VALUES ('$nmsegment', '$banksegment', '$accname', $accnumber)";

// mengeksekusi query
if (mysqli_query($conn, $sql)) {
   echo "<script>alert('Data berhasil disimpan.'); window.location='segment.php';</script>";
} else {
   echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}

// menutup koneksi ke database
mysqli_close($conn);
