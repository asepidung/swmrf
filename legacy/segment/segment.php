<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

$idusers = $_SESSION['idusers'];
$userid = $_SESSION['userid'];

?>
<div class="content-wrapper">
   <!-- Content Header (Page header) -->
   <div class="content-header">
      <div class="container-fluid">
         <div class="row">
            <div class="col-sm-6">
               <!-- <h1 class="m-0">LIST SEGMENT</h1> -->
               <a href="customer.php"><button type="button" class="btn btn-sm btn-success"><i class="fas fa-undo-alt"></i> Customer</button></a>
            </div><!-- /.col -->
         </div><!-- /.row -->
      </div><!-- /.container-fluid -->
   </div>
   <!-- /.content-header -->
   <div class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-lg-4">
               <div class="card">
                  <div class="card-body">
                     <form method="POST" action="newsegment.php">
                        <div class="form-group">
                           <label for="nmsegment">NAMA SEGMENT<span class="text-danger">*</span></label>
                           <div class="input-group">
                              <input type="text" class="form-control" name="nmsegment" id="nmsegment">
                           </div>
                        </div>
                        <div class="form-group">
                           <label for="banksegment">BANK <span class="text-danger">*</span></label>
                           <div class="input-group">
                              <input type="text" class="form-control" name="banksegment" id="banksegment" placeholder="Contoh : BRI (BANK RAKYAT INDONESIA)">
                           </div>
                        </div>
                        <div class="form-group">
                           <label for="accname">ATAS NAMA PEMILIK BANK<span class="text-danger">*</span></label>
                           <div class="input-group">
                              <input type="text" class="form-control" name="accname" id="accname">
                           </div>
                        </div>
                        <div class="form-group">
                           <label for="accnumber">NO REKENING<span class="text-danger">*</span></label>
                           <div class="input-group">
                              <input type="number" class="form-control" name="accnumber" id="accnumber">
                           </div>
                        </div>
                        <button type="submit" class="btn bg-gradient-primary" name="submit">INPUT</button>
                     </form>
                  </div>
               </div>
               <!-- /.card -->
            </div>
            <!-- /.col-md-6 -->
            <div class="col-lg-8">
               <div class="card">
                  <div class="card-body">
                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>NAMA SEGMENT</th>
                              <th>NAMA BANK</th>
                              <th>NAMA AKUN</th>
                              <th>NOMOR REKENIG</th>
                              <th>Actions</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           $ambildata = mysqli_query($conn, "SELECT * FROM segment ORDER BY nmsegment ASC");
                           while ($tampil = mysqli_fetch_array($ambildata)) {
                           ?>
                              <tr>
                                 <td><?= $no; ?></td>
                                 <td><?= $tampil['nmsegment']; ?></td>
                                 <td><?= $tampil['banksegment']; ?></td>
                                 <td><?= $tampil['accname']; ?></td>
                                 <td><?= $tampil['accnumber']; ?></td>
                                 <td class="text-center">
                                    <a href="editsegment.php?id=<?= $tampil['idsegment']; ?>">
                                       <span class="text-success"><i class="fas fa-pencil-alt"></i></i></span>
                                    </a>
                                    |
                                    <a href="hapussegment.php?id=<?= $tampil['idsegment']; ?>">
                                       <span class="text-danger"><i class="fas fa-minus-square"></i></span>
                                    </a>
                                 </td>
                              </tr>
                           <?php
                              $no++;
                           }
                           ?>
                        </tbody>
                     </table>
                  </div>
               </div>
               <!-- /.card -->
            </div>
            <!-- /.col-md-6 -->
         </div>
         <!-- /.row -->
      </div>
      <!-- /.container-fluid -->
   </div>
   <script>
      document.title = "LIST SEGMENT";
   </script>
   <?php
   require "../footnote.php";
   require "../footer.php";
   ?>