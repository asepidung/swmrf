<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Mendapatkan data dari form
$idcustomer = $_POST['idcustomer'];
$nama_customer = $_POST['nama_customer'];
$alamat = $_POST['alamat'];
$idsegment = $_POST['idsegment'];
$top = $_POST['top'];
// $pajak = $_POST['pajak'];
$tukarfaktur = $_POST['tukarfaktur'];
// $telepon = $_POST['telepon'];
// $email = $_POST['email'];
$catatan = $_POST['catatan'];
$idgroup = $_POST['idgroup'];

// Mendapatkan data checkbox dokumen
$invoice = isset($_POST['dokumen']) && in_array('invoice', $_POST['dokumen']) ? 1 : 0;
$nkv = isset($_POST['dokumen']) && in_array('NKV', $_POST['dokumen']) ? 1 : 0;
$halal = isset($_POST['dokumen']) && in_array('Halal', $_POST['dokumen']) ? 1 : 0;
$sv = isset($_POST['dokumen']) && in_array('SV', $_POST['dokumen']) ? 1 : 0;
$joss = isset($_POST['dokumen']) && in_array('Joss', $_POST['dokumen']) ? 1 : 0;
$phd = isset($_POST['dokumen']) && in_array('PHD', $_POST['dokumen']) ? 1 : 0;
$ujilab = isset($_POST['dokumen']) && in_array('Uji Lab', $_POST['dokumen']) ? 1 : 0;

// Update data customer
$query = "UPDATE customers SET nama_customer = ?, alamat1 = ?, idsegment = ?, top = ?, tukarfaktur = ?, catatan = ?, idgroup = ?, invoice = ?, nkv = ?, halal = ?, sv = ?, joss = ?, phd = ?, ujilab = ? WHERE idcustomer = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssisssiiiiiiiii", $nama_customer, $alamat, $idsegment, $top, $tukarfaktur, $catatan, $idgroup, $invoice, $nkv, $halal, $sv, $joss, $phd, $ujilab, $idcustomer);

if ($stmt->execute()) {
   echo "<script>alert('Data berhasil diperbarui.'); window.location='customer.php';</script>";
} else {
   echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
