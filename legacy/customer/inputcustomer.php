<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// mengambil data dari form
$nama_customer = $_POST['nama_customer'];
$alamat = $_POST['alamat'];
$idsegment = $_POST['idsegment'];
$top = $_POST['top'];
$pajak = "NO";
// $telepon = $_POST['telepon'];
$email = "-";
$tukarfaktur = $_POST['tukarfaktur'];
$catatan = $_POST['catatan'];
$idgroup = $_POST['idgroup'];

// mengambil data dari checkbox dokumen
$invoice = isset($_POST['dokumen']) && in_array('invoice', $_POST['dokumen']) ? 1 : 0;
$nkv = isset($_POST['dokumen']) && in_array('NKV', $_POST['dokumen']) ? 1 : 0;
$halal = isset($_POST['dokumen']) && in_array('Halal', $_POST['dokumen']) ? 1 : 0;
$sv = isset($_POST['dokumen']) && in_array('SV', $_POST['dokumen']) ? 1 : 0;
$joss = isset($_POST['dokumen']) && in_array('Joss', $_POST['dokumen']) ? 1 : 0;
$phd = isset($_POST['dokumen']) && in_array('PHD', $_POST['dokumen']) ? 1 : 0;
$ujilab = isset($_POST['dokumen']) && in_array('Uji Lab', $_POST['dokumen']) ? 1 : 0;

// membuat prepared statement dengan kolom dokumen
$stmt = $conn->prepare("INSERT INTO customers (nama_customer, alamat1, idsegment, top, pajak, tukarfaktur, email, catatan, idgroup, invoice, nkv, halal, sv, joss, phd, ujilab)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

// bind parameter termasuk nilai checkbox dokumen
$stmt->bind_param("ssiissssiiiiiiii", $nama_customer, $alamat, $idsegment, $top, $pajak, $tukarfaktur, $email, $catatan, $idgroup, $invoice, $nkv, $halal, $sv, $joss, $phd, $ujilab);

// mengeksekusi prepared statement
if ($stmt->execute()) {
   echo "<script>alert('Data berhasil disimpan.'); window.location='customer.php';</script>";
} else {
   echo "Error: " . $stmt->error;
}

// menutup prepared statement
$stmt->close();

// menutup koneksi ke database
$conn->close();
