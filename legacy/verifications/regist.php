<?php
session_start();

if (!isset($_SESSION['login'])) {
   header("location: ../verifications/login.php");
   exit();
}

require "../konak/conn.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>Daftar User</title>
   <link rel="icon" href="../dist/img/favicon.png" type="image/x-icon">
   <link rel="stylesheet" href="../https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
   <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
   <link rel="stylesheet" href="../plugins/icheck-bootstrap/icheck-bootstrap.min.css">
   <link rel="stylesheet" href="../dist/css/adminlte.min.css">
</head>

<body class="hold-transition register-page">
   <div class="register-box">
      <div class="register-logo">
         <a href="index2.html"><b>SWM</b>-cms</a>
      </div>

      <div class="card">
         <div class="card-body register-card-body">
            <p class="login-box-msg">Regist New User</p>
            <form action="newuser.php" method="post">
               <div class="input-group mb-3">
                  <input type="text" class="form-control" name="fullname" id="fullname" placeholder="Nama Lengkap" required>
                  <div class="input-group-append">
                     <div class="input-group-text">
                        <i class="fas fa-file-signature"></i>
                     </div>
                  </div>
               </div>
               <div class="input-group mb-3">
                  <input type="text" class="form-control" name="userid" id="userid" placeholder="Buat Username Login Anda" required>
                  <div class="input-group-append">
                     <div class="input-group-text">
                        <i class="fas fa-user-check"></i>
                     </div>
                  </div>
               </div>
               <div class="input-group mb-3">
                  <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                  <div class="input-group-append">
                     <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                     </div>
                  </div>
               </div>
               <div class="input-group mb-3">
                  <input type="password" class="form-control" name="password2" id="password2" placeholder="Ulangi Password" required>
                  <div class="input-group-append">
                     <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                     </div>
                  </div>
               </div>
               <div class="form-group">
                  <label>Pilih Menu yang Bisa Diakses:</label><br>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="cattle" value="cattle">
                     <label class="custom-control-label" for="cattle">Cattle</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="produksi" value="produksi">
                     <label class="custom-control-label" for="produksi">Produksi</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="warehouse" value="warehouse">
                     <label class="custom-control-label" for="warehouse">Warehouse</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="stock" value="stock">
                     <label class="custom-control-label" for="stock">Stock</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="distributions" value="distributions">
                     <label class="custom-control-label" for="distributions">Distributions</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="purchase_module" value="purchase_module">
                     <label class="custom-control-label" for="purchase_module">Purchase Module</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="sales" value="sales">
                     <label class="custom-control-label" for="sales">Sales</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="finance" value="finance">
                     <label class="custom-control-label" for="finance">Finance</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="data_report" value="data_report">
                     <label class="custom-control-label" for="data_report">Data Report</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="master_data" value="master_data">
                     <label class="custom-control-label" for="master_data">Master Data</label>
                  </div>
                  <div class="custom-control custom-checkbox">
                     <input type="checkbox" class="custom-control-input" name="menu_access[]" id="qc" value="qc">
                     <label class="custom-control-label" for="qc">QC</label>
                  </div>
               </div>
               <div class="row">
                  <div class="col-4">
                     <button type="submit" class="btn btn-primary btn-block">Register</button>
                  </div>
               </div>
            </form>
         </div>
      </div>
   </div>
   <script>
      var password = document.getElementById("password");
      var password2 = document.getElementById("password2");

      function validatePassword() {
         if (password.value !== password2.value) {
            password2.setCustomValidity("Passwords do not match");
         } else {
            password2.setCustomValidity("");
         }
      }

      password.onchange = validatePassword;
      password2.onkeyup = validatePassword;
   </script>
   <script src="../plugins/jquery/jquery.min.js"></script>
   <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
   <script src="../dist/js/adminlte.min.js"></script>
</body>

</html>