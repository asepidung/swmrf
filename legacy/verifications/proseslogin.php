<?php
require "../konak/conn.php";
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

   $userid   = trim($_POST['userid'] ?? '');
   $password = trim($_POST['password'] ?? '');

   if ($userid === '' || $password === '') {
      header("Location: login.php?error=empty");
      exit();
   }

   // MODIFIKASI: Melakukan JOIN ke tabel role untuk mengambil semua hak akses
   $sql = "SELECT u.idusers, u.userid, u.passuser, u.fullname, u.status, 
                  r.cattle, r.produksi, r.warehouse, r.stock, r.distributions, 
                  r.purchase_module, r.sales, r.finance, r.data_report, r.master_data, r.qc
            FROM users u
            LEFT JOIN role r ON u.idusers = r.idusers
            WHERE u.userid = ? 
            LIMIT 1";

   $stmt = mysqli_prepare($conn, $sql);

   if (!$stmt) {
      die("Prepare failed: " . mysqli_error($conn));
   }

   mysqli_stmt_bind_param($stmt, "s", $userid);
   mysqli_stmt_execute($stmt);
   $result = mysqli_stmt_get_result($stmt);

   if ($result && mysqli_num_rows($result) === 1) {

      $row = mysqli_fetch_assoc($result);

      // Cek status akun
      if ($row['status'] === 'INAKTIF') {
         header("Location: login.php?error=inactive");
         exit();
      }

      // Verifikasi password
      if (!password_verify($password, $row['passuser'])) {
         header("Location: login.php?error=invalid");
         exit();
      }

      // Login sukses
      session_regenerate_id(true);

      $_SESSION['login']    = true;
      $_SESSION['userid']   = $row['userid'];
      $_SESSION['idusers']  = $row['idusers'];
      $_SESSION['fullname'] = $row['fullname'];

      // MODIFIKASI: Menyimpan semua role ke dalam session
      $_SESSION['role'] = [
         'cattle'          => $row['cattle'],
         'produksi'        => $row['produksi'],
         'warehouse'       => $row['warehouse'],
         'stock'           => $row['stock'],
         'distributions'   => $row['distributions'],
         'purchase_module' => $row['purchase_module'],
         'sales'           => $row['sales'],
         'finance'         => $row['finance'],
         'data_report'     => $row['data_report'],
         'master_data'     => $row['master_data'],
         'qc'              => $row['qc'] // Role QC tersimpan di session
      ];

      // Catat log login
      $logSql = "INSERT INTO logactivity (iduser, event) VALUES (?, 'Login')";
      $logStmt = mysqli_prepare($conn, $logSql);

      if ($logStmt) {
         mysqli_stmt_bind_param($logStmt, "i", $row['idusers']);
         mysqli_stmt_execute($logStmt);
         mysqli_stmt_close($logStmt);
      }

      mysqli_stmt_close($stmt);
      mysqli_close($conn);

      header("Location: ../index.php");
      exit();
   } else {
      header("Location: login.php?error=notfound");
      exit();
   }
}

mysqli_close($conn);
