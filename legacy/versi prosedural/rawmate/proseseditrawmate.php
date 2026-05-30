<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// =======================
// Ambil & bersihkan input
// =======================
$idrawmate     = isset($_POST['idrawmate']) ? (int)$_POST['idrawmate'] : 0;
$nmrawmate     = isset($_POST['nmrawmate']) ? trim($_POST['nmrawmate']) : '';
$idrawcategory = isset($_POST['idrawcategory']) ? (int)$_POST['idrawcategory'] : 0;
$stock         = isset($_POST['stock']) ? (int)$_POST['stock'] : -1;
$unit          = isset($_POST['unit']) ? trim($_POST['unit']) : '';
$barmin_input  = isset($_POST['barmin']) ? trim($_POST['barmin']) : '';

// =======================
// Normalisasi BARMIN
// =======================
if ($barmin_input === '') {
  $barmin = 0;
} else {
  $barmin = (int)$barmin_input;
  if ($barmin < 0) $barmin = 0;
}

// =======================
// Validasi dasar
// =======================
$valid_units = ["Box", "Ikat", "Kg", "Pack", "Pcs", "Set"];

if (
  $idrawmate <= 0 ||
  $nmrawmate === '' ||
  $idrawcategory <= 0 ||
  !in_array($stock, [0, 1], true)
) {
  echo "<script>
    alert('Form tidak lengkap atau ID tidak valid.');
    window.location='editrawmate.php?idrawmate={$idrawmate}';
  </script>";
  exit();
}

// Validasi unit
if ($unit === '' || !in_array($unit, $valid_units, true)) {
  echo "<script>
    alert('Satuan (unit) tidak valid.');
    window.location='editrawmate.php?idrawmate={$idrawmate}';
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
$checkSql = "
  SELECT idrawmate
  FROM rawmate
  WHERE UPPER(nmrawmate) = ?
    AND idrawmate <> ?
  LIMIT 1
";

$stmt = $conn->prepare($checkSql);
if (!$stmt) {
  echo "<script>alert('Database error (prepare check).'); window.location='editrawmate.php?idrawmate={$idrawmate}';</script>";
  exit();
}

$stmt->bind_param("si", $nmrawmate_upper, $idrawmate);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
  $stmt->close();
  echo "<script>
    alert('Nama material sudah ada. Gunakan nama lain.');
    window.location='editrawmate.php?idrawmate={$idrawmate}';
  </script>";
  exit();
}
$stmt->close();

// =======================
// Update data rawmate
// =======================
$updateSql = "
  UPDATE rawmate
  SET
    nmrawmate     = ?,
    idrawcategory = ?,
    stock         = ?,
    unit          = ?,
    barmin        = ?
  WHERE idrawmate = ?
";

$stmt = $conn->prepare($updateSql);
if (!$stmt) {
  echo "<script>alert('Database error (prepare update).'); window.location='editrawmate.php?idrawmate={$idrawmate}';</script>";
  exit();
}

$stmt->bind_param(
  "siisii",
  $nmrawmate_upper,
  $idrawcategory,
  $stock,
  $unit,
  $barmin,
  $idrawmate
);

if ($stmt->execute()) {
  $stmt->close();
  echo "<script>
    alert('Data rawmate berhasil diperbarui.');
    window.location='index.php';
  </script>";
  exit();
} else {
  $err = addslashes($stmt->error);
  $stmt->close();
  echo "<script>
    alert('Gagal memperbarui data: {$err}');
    window.location='editrawmate.php?idrawmate={$idrawmate}';
  </script>";
  exit();
}

// =======================
mysqli_close($conn);
