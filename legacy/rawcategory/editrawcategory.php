<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Mendapatkan idcategory dari URL
$idrawcategory = $_GET['idrawcategory'];

// Mengambil data category dari database berdasarkan idcategory
$query = "SELECT * FROM rawcategory WHERE idrawcategory = '$idrawcategory'";
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
              <h3 class="card-title">EDIT KATEGORI</h3>
            </div>
            <!-- /.card-header -->
            <!-- form start -->
            <form method="POST" action="proseseditrawcategory.php">
              <div class=" card-body">
                <div class="form-group">
                  <label for="nmcategory">Nama Kategori <span class="text-danger">*</span></label>
                  <input type="hidden" name="idrawcategory" id="idrawcategory" value="<?= $idrawcategory; ?>">
                  <input type="text" class="form-control" name="nmcategory" id="nmcategory" value="<?= $row['nmcategory']; ?>">
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
<!-- /.content-wrapper -->

<?php
include "../footer.php";
include "../footnote.php";
?>