<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
   <!-- Content Header (Page header) -->
   <!-- <div class="content-header">
      <div class="container-fluid">
         <div class="row mb-2">
            <div class="col-sm-6">
               <h1 class="m-0">NEW BONING PROJECT</h1>
            </div>
         </div>
      </div>
   </div> -->
   <!-- /.content-header -->

   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <!-- left column -->
            <div class="col-md-6">
               <!-- general form elements -->
               <div class="card card-dark mt-3">
                  <div class="card-header">
                     <h3 class="card-title">Data Supplier Baru</h3>
                  </div>
                  <!-- /.card-header -->
                  <!-- form start -->
                  <form method="POST" action="prosesnewsupplier.php">
                     <div class=" card-body">
                        <div class="form-group">
                           <label for="nmsupplier">Nama Supplier <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" name="nmsupplier" id="nmsupplier" autofocus required>
                           <div class="form-group">
                              <label for="alamat">Alamat</label>
                              <input type="text" class="form-control" name="alamat" id="alamat">
                           </div>
                           <div class="form-group">
                              <label for="jenis_usaha">Barang Yang Disuplai <span class="text-danger">*</span></label>
                              <input type="text" class="form-control" name="jenis_usaha" id="jenis_usaha" required>
                           </div>
                           <div class="form-group">
                              <label for="telepon">No Telepon</label>
                              <input type="text" class="form-control" name="telepon" id="telepon">
                           </div>
                           <div class="form-group">
                              <label for="npwp">NPWP</label>
                              <input type="text" class="form-control" name="npwp" id="npwp">
                           </div>
                        </div>
                        <div class="form-group mr-3 text-right">
                           <button type="submit" class="btn bg-gradient-primary">Submit</button>
                        </div>
                     </div>
                     <!-- /.card-body -->

                  </form>
               </div>
               <!-- /.card -->
            </div>
         </div>
   </section>
</div><!-- /.container-fluid -->
<!-- /.content -->
<!-- </div> -->
<!-- /.content-wrapper -->

<?php include "../footer.php" ?>