<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Mendapatkan idboning dari URL
$idboning = $_GET['idboning'];

// Mengambil data boning dari database berdasarkan idboning
$query = "SELECT * FROM boning WHERE idboning = '$idboning'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

?>

<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <!-- <h1 class="m-0">Edit Data Boning</h1> -->
        </div><!-- /.col -->
      </div><!-- /.row -->
    </div><!-- /.container-fluid -->
  </div>
  <!-- /.content-header -->
  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-6">
          <!-- general form elements -->
          <div class="card card-dark mt-3">
            <div class="card-header">
              <h3 class="card-title">Edit Data Boning</h3>
            </div>
            <!-- /.card-header -->
            <!-- form start -->
            <form method="POST" action="proseseditdataboning.php">
              <input type="hidden" name="idboning" value="<?= $idboning ?>">
              <div class="card-body">
                <div class="form-group">
                  <label for="batchboning">Batch Number</label>
                  <input type="text" class="form-control" name="batchboning" id="batchboning" value="<?= $row['batchboning']; ?>" readonly>
                </div>
                <div class="form-group">
                  <label for="tglboning">Tanggal Boning</label>
                  <input type="date" class="form-control" name="tglboning" id="tglboning" value="<?= $row['tglboning']; ?>" required>
                </div>
                <div class="form-group">
                  <label for="idsupplier">Supplier</label>
                  <select class="form-control" name="idsupplier" id="idsupplier" required>
                    <?php
                    // Mengambil data supplier dari database
                    $query_supplier = "SELECT * FROM supplier";
                    $result_supplier = mysqli_query($conn, $query_supplier);
                    // Menampilkan opsi supplier
                    while ($row_supplier = mysqli_fetch_assoc($result_supplier)) {
                      if ($row_supplier['idsupplier'] == $row['idsupplier']) {
                        echo "<option value='" . $row_supplier['idsupplier'] . "' selected>" . $row_supplier['nmsupplier'] . "</option>";
                      } else {
                        echo "<option value='" . $row_supplier['idsupplier'] . "'>" . $row_supplier['nmsupplier'] . "</option>";
                      }
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="qtysapi">Jumlah Sapi</label>
                  <input type="number" class="form-control" name="qtysapi" id="qtysapi" value="<?= $row['qtysapi']; ?>" required>
                </div>
                <div class="form-group">
                  <label for="qtysapi">Keterangan</label>
                  <input type="text" class="form-control" name="keterangan" id="keterangan" value="<?= $row['keterangan']; ?>" required>
                </div>
                <div class="form-group mr-3 text-right">
                  <button type="submit" class="btn bg-gradient-success">Update</button>
                </div>
              </div>
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