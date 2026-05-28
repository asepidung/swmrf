<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

   // Amankan idusers
   $idusers = isset($_POST['idusers']) ? intval($_POST['idusers']) : 0;

   // Jika idusers kosong, coba cek parameter 'id' (menyesuaikan dengan form edit sebelumnya)
   if ($idusers <= 0) {
      $idusers = isset($_POST['id']) ? intval($_POST['id']) : 0;
   }

   if ($idusers <= 0) {
      echo "<script>alert('ID user tidak valid.'); window.location='user.php';</script>";
      exit();
   }

   // Inisialisasi semua role dengan default 0 (tidak punya akses)
   $roles = [
      'cattle'          => 0,
      'produksi'        => 0,
      'warehouse'       => 0,
      'stock'           => 0,
      'distributions'   => 0,
      'purchase_module' => 0,
      'sales'           => 0,
      'finance'         => 0,
      'data_report'     => 0,
      'master_data'     => 0,
      'qc'              => 0 // Penambahan role QC
   ];

   // Cek menu yang dicentang di form
   if (isset($_POST['menu_access']) && is_array($_POST['menu_access'])) {
      foreach ($_POST['menu_access'] as $access) {
         if (array_key_exists($access, $roles)) {
            $roles[$access] = 1;
         }
      }
   }

   // Query update disesuaikan dengan kolom 'qc'
   $query = "
      UPDATE role SET 
         cattle = '{$roles['cattle']}',
         produksi = '{$roles['produksi']}', 
         warehouse = '{$roles['warehouse']}', 
         stock = '{$roles['stock']}', 
         distributions = '{$roles['distributions']}', 
         purchase_module = '{$roles['purchase_module']}', 
         sales = '{$roles['sales']}', 
         finance = '{$roles['finance']}', 
         data_report = '{$roles['data_report']}', 
         master_data = '{$roles['master_data']}',
         qc = '{$roles['qc']}'
      WHERE idusers = $idusers
   ";

   if (mysqli_query($conn, $query)) {
      echo "<script>alert('Data berhasil diperbarui.'); window.location='user.php';</script>";
      exit();
   } else {
      echo "<script>alert('Gagal memperbarui data: " . mysqli_error($conn) . "'); window.location='user.php';</script>";
   }
}

mysqli_close($conn);
