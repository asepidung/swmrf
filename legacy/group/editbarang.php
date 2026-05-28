<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "kdbarangunik.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Mendapatkan idbarang dari URL
$idbarang = $_GET['idbarang'];

// Mengambil data barang dari database berdasarkan idbarang
$query = "SELECT * FROM barang WHERE idbarang = '$idbarang'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

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
              <h3 class="card-title">Edit Barang</h3>
            </div>
            <!-- /.card-header -->
            <!-- form start -->
            <form method="POST" action="proseseditbarang.php">
              <div class=" card-body">
                <div class="form-group">
                  <label for="kdbarang">Kode</label>
                  <input type="hidden" name="idbarang" value="<?= $idbarang ?>" ?>
                  <input type="text" class="form-control" name="kdbarang" id="kdbarang" value="<?= $row['kdbarang']; ?>" readonly>
                </div>
                <div class="form-group">
                  <label for="nmbarang">Nama Product <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nmbarang" id="nmbarang" value="<?= $row['nmbarang']; ?>">
                </div>
              </div>
              <div class="form-group mr-3 text-right">
                <button type="submit" class="btn bg-gradient-primary"><i class="fas fa-level-up-alt"></i> Update</button>
              </div>
            </form>
          </div>
          <!-- /.card -->
        </div>
      </div>
    </div>
  </section>
</div><!-- /.container-fluid -->
<!-- /.content -->
<!-- </div> -->
<!-- /.content-wrapper -->

<?php
include "../footer.php";
include "../footnote.php";
?>