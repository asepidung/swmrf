<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// =======================
// Ambil & bersihkan input
// =======================
$kdrawmate       = isset($_POST['kdrawmate']) ? trim($_POST['kdrawmate']) : '';
$nmrawmate       = isset($_POST['nmrawmate']) ? trim($_POST['nmrawmate']) : '';
$tampilkan_stock = isset($_POST['tampilkan_stock']) ? trim($_POST['tampilkan_stock']) : '';
$idrawcategory   = isset($_POST['idrawcategory']) ? (int)$_POST['idrawcategory'] : 0;
$unit            = isset($_POST['unit']) ? trim($_POST['unit']) : '';
$barmin_input    = isset($_POST['barmin']) ? trim($_POST['barmin']) : '';

// =======================
// Normalisasi BARMIN
// - boleh kosong
// - default 0
// =======================
if ($barmin_input === '') {
   $barmin = 0;
} else {
   $barmin = (int)$barmin_input;
   if ($barmin < 0) $barmin = 0;
}

// =======================
// Validasi field wajib
// =======================
if (
   $kdrawmate === '' ||
   $nmrawmate === '' ||
   $idrawcategory <= 0 ||
   $tampilkan_stock === '' ||
   $unit === ''
) {
   echo "<script>
      alert('Form tidak lengkap. Pastikan semua field wajib terisi.');
      window.location='newrawmate.php';
   </script>";
   exit();
}

// =======================
// Uppercase nama material
// =======================
$nmrawmate_upper = mb_strtoupper($nmrawmate, 'UTF-8');

// =======================
// Cek duplikasi nama
// =======================
$checkSql = "SELECT idrawmate FROM rawmate WHERE UPPER(nmrawmate) = ? LIMIT 1";
$stmt = $conn->prepare($checkSql);
if (!$stmt) {
   echo "<script>alert('Database error (prepare check).'); window.location='newrawmate.php';</script>";
   exit();
}

$stmt->bind_param("s", $nmrawmate_upper);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
   $stmt->close();
   echo "<script>
      alert('Nama material sudah ada dalam database.');
      window.location='newrawmate.php';
   </script>";
   exit();
}
$stmt->close();

// =======================
// Insert data rawmate
// =======================
$insertSql = "
   INSERT INTO rawmate
   (kdrawmate, nmrawmate, idrawcategory, stock, unit, barmin)
   VALUES (?, ?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($insertSql);
if (!$stmt) {
   echo "<script>alert('Database error (prepare insert).'); window.location='newrawmate.php';</script>";
   exit();
}

$stock_int = (int)$tampilkan_stock;
$stmt->bind_param(
   "ssissi",
   $kdrawmate,
   $nmrawmate_upper,
   $idrawcategory,
   $stock_int,
   $unit,
   $barmin
);

if ($stmt->execute()) {
   $stmt->close();
   echo "<script>
      alert('Data berhasil disimpan.');
      window.location='index.php';
   </script>";
   exit();
} else {
   $err = addslashes($stmt->error);
   $stmt->close();
   echo "<script>
      alert('Gagal menyimpan data: {$err}');
      window.location='newrawmate.php';
   </script>";
   exit();
}

// =======================
mysqli_close($conn);
