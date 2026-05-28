<?php
session_start();
if (isset($_SESSION['login'])) {
   header("location: ../index.php");
   exit();
}

// Cek apakah ada error dari proses login
$errorMessage = "";
if (isset($_GET['error'])) {
   switch ($_GET['error']) {
      case "invalid":
         $errorMessage = "Username atau password salah.";
         break;
      case "notfound":
         $errorMessage = "Akun tidak ditemukan.";
         break;
      case "inactive":
         $errorMessage = "Akun Anda dinonaktifkan. Hubungi administrator.";
         break;
      case "timeout":
         $errorMessage = "Anda telah logout karena tidak aktif selama 5 menit.";
         break;
   }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>CMS-SWM | Log in</title>
   <link rel="icon" href="../dist/img/favicon.png" type="image/x-icon">
   <!-- Google Font: Source Sans Pro -->
   <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
   <!-- Font Awesome -->
   <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
   <!-- icheck bootstrap -->
   <link rel="stylesheet" href="../plugins/icheck-bootstrap/icheck-bootstrap.min.css">
   <!-- Theme style -->
   <link rel="stylesheet" href="../dist/css/adminlte.min.css">
</head>

<body class="hold-transition login-page">
   <div class="login-box">
      <div class="login-logo">
         <a href="#"><b>Silahkan Login</b></a>
      </div>
      <!-- /.login-logo -->
      <div class="card">
         <div class="card-body login-card-body">
            <p class="login-box-msg">Sign in to start your session</p>

            <!-- Notifikasi Error -->
            <?php if (!empty($errorMessage)): ?>
               <div class="alert alert-danger text-center" role="alert">
                  <?php echo $errorMessage; ?>
               </div>
            <?php endif; ?>

            <form action="proseslogin.php" method="post">
               <div class="input-group mb-3">
                  <input type="text" class="form-control" name="userid" id="userid" placeholder="Username" required>
                  <div class="input-group-append">
                     <div class="input-group-text">
                        <span class="fas fa-user"></span>
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
               <div class="row">
                  <div class="col-8">
                     <div class="icheck-primary">
                        <input type="checkbox" id="remember">
                        <label for="remember">
                           Remember Me
                        </label>
                     </div>
                  </div>
                  <!-- /.col -->
                  <div class="col-4">
                     <button type="submit" name="login" class="btn btn-primary btn-block">Sign In</button>
                  </div>
                  <!-- /.col -->
               </div>
            </form>
         </div>
         <!-- /.login-card-body -->
      </div>
   </div>
   <!-- /.login-box -->

   <!-- jQuery -->
   <script src="../plugins/jquery/jquery.min.js"></script>
   <!-- Bootstrap 4 -->
   <script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
   <!-- AdminLTE App -->
   <script src="../dist/js/adminlte.min.js"></script>
</body>

</html>