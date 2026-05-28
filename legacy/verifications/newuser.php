<?php
require "../konak/conn.php";

// Pastikan request dari form POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   echo "<script>alert('Akses tidak valid.'); window.location='regist.php';</script>";
   exit();
}

// Mengambil data dari form dan membersihkannya
$userid    = mysqli_real_escape_string($conn, $_POST['userid']);
$fullname  = mysqli_real_escape_string($conn, $_POST['fullname']);
$password  = mysqli_real_escape_string($conn, $_POST['password']);
$password2 = mysqli_real_escape_string($conn, $_POST['password2']);
$menu_access = isset($_POST['menu_access']) ? $_POST['menu_access'] : [];

// Cek password sama
if ($password !== $password2) {
   echo "<script>alert('Password Anda berbeda.'); window.location='regist.php';</script>";
   exit();
}

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// ==== 1. Simpan user baru ke tabel users ====
$stmt = mysqli_prepare($conn, "INSERT INTO users (fullname, userid, passuser) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "sss", $fullname, $userid, $password_hash);

// Eksekusi simpan user
if (mysqli_stmt_execute($stmt)) {
   // Ambil idusers yang baru saja dimasukkan
   $idusers = mysqli_insert_id($conn);

   // ==== 2. Siapkan default role (semua 0) termasuk penambahan role qc ====
   $access = [
      'cattle' => 0,
      'produksi' => 0,
      'warehouse' => 0,
      'stock' => 0,
      'distributions' => 0,
      'purchase_module' => 0,
      'sales' => 0,
      'finance' => 0,
      'data_report' => 0,
      'master_data' => 0,
      'qc' => 0 // Tambahan inisialisasi default QC
   ];

   // Update nilai role berdasarkan checkbox yang dicentang
   foreach ($menu_access as $menu) {
      if (isset($access[$menu])) {
         $access[$menu] = 1;
      }
   }

   // ==== 3. Simpan role ke tabel role ====
   $stmt_role = mysqli_prepare(
      $conn,
      "INSERT INTO role (
         idusers,
         cattle,
         produksi,
         warehouse,
         stock,
         distributions,
         purchase_module,
         sales,
         finance,
         data_report,
         master_data,
         qc
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" // Menambahkan satu parameter (?) untuk QC
   );

   // Menyesuaikan bind_param dengan menambahkan format 'i' (menjadi 12) dan variabel $access['qc']
   mysqli_stmt_bind_param(
      $stmt_role,
      "iiiiiiiiiiii",
      $idusers,
      $access['cattle'],
      $access['produksi'],
      $access['warehouse'],
      $access['stock'],
      $access['distributions'],
      $access['purchase_module'],
      $access['sales'],
      $access['finance'],
      $access['data_report'],
      $access['master_data'],
      $access['qc'] // Parameter binding untuk QC
   );

   if (mysqli_stmt_execute($stmt_role)) {
      // Kalau ini form khusus admin tambah user, enak diarahkan ke daftar user
      echo "<script>alert('Data berhasil disimpan.'); window.location='../index.php';</script>";
   } else {
      echo "Error saat menyimpan role: " . mysqli_error($conn);
   }

   mysqli_stmt_close($stmt_role);
} else {
   echo "Error saat menyimpan user: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
