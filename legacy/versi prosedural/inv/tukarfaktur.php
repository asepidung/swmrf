<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
$idinvoice = isset($_GET['idinvoice']) ? intval($_GET['idinvoice']) : 0;
if ($idinvoice <= 0) {
   die("ID Invoice tidak valid.");
}

// Proses form submission
?>
<div class="content-wrapper">

   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col">
               <div class="card mt-3">
                  <!-- /.card-header -->
                  <div class="card-body">
                     <form method="POST" action="prosestf.php">
                        <?php
                        $idinvoice = mysqli_real_escape_string($conn, $_GET['idinvoice']); // Sanitize input to prevent SQL injection
                        $ambildata = mysqli_query($conn, "SELECT invoice.*, customers.nama_customer FROM invoice INNER JOIN customers ON invoice.idcustomer = customers.idcustomer WHERE idinvoice = '$idinvoice'");
                        while ($tampil = mysqli_fetch_array($ambildata)) { ?>
                           <div class="col">
                              <div class="form-group">
                                 <label>Customer</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" value="<?= $tampil['nama_customer']; ?>" readonly>
                                    <input type="hidden" name="top" value="<?= $tampil['top']; ?>">
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label>No Invoice</label>
                                 <div class="input-group">
                                    <input type="hidden" name="idinvoice" value="<?= $idinvoice ?>">
                                    <input type="text" class="form-control" value="<?= $tampil['noinvoice']; ?>" readonly>
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label>Invoice Date</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" value="<?= date("d-M-Y", strtotime($tampil['invoice_date'])); ?>" readonly>
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label>Amount</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" value="<?= "Rp." . " " . number_format($tampil['balance'], 2); ?>" readonly>
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label>Tanggal Tukar Faktur</label>
                                 <div class="input-group">
                                    <input type="date" class="form-control" name="tgltf">
                                 </div>
                              </div>
                           </div>
                        <?php } ?>
                        <div class="col">
                           <div class="form-group">
                              <label>Bukti</label>
                              <div class="input-group">
                                 <input type="text" class="form-control" name="note" placeholder="Resi, Email dll">
                              </div>
                           </div>
                        </div>
                        <button type="submit" class="btn btn-info float-right">Tukar Faktur</button>
                     </form>
                  </div>
                  <!-- /.card-body -->
               </div>
               <!-- /.card -->
            </div>
            <!-- /.col -->
         </div>
         <!-- /.row -->
      </div>
      <!-- /.container-fluid -->
   </section>
   <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<script>
   // Mengubah judul halaman web
   document.title = "Tukar Faktur";
</script>
<?php
// require "../footnote.php";
include "../footer.php";
?>