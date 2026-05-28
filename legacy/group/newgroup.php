<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <!-- left column -->
            <div class="col-md-6">
               <!-- general form elements -->
               <div class="card card-dark mt-3">
                  <div class="card-header">
                     <h3 class="card-title">Input Group Baru</h3>
                  </div>
                  <!-- /.card-header -->
                  <!-- form start -->
                  <form method="POST" action="prosesnewgroup.php">
                     <div class=" card-body">
                        <div class="form-group">
                           <label for="nmgroup">Nama Group <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" name="nmgroup" id="nmgroup" placeholder="Gunakan Huruf BESAR !!!" required>
                        </div>
                        <p class="text-justify">apabila Karyawan / Warga silahkan pilih reguler, apabila customer hanya memiliki 1 cabang cukup isi nama customernya tidak perlu lengkap</p>
                        <p><strong>Contoh :</strong> Santi Wijaya</p>
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


<?php
include "../footer.php";
include "../footnote.php";
?>