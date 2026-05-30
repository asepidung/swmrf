<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$iddo = $_GET['iddo'];

$query = "SELECT do.*, customers.alamat1, customers.nama_customer
          FROM do
          INNER JOIN customers ON do.idcustomer = customers.idcustomer
          WHERE do.iddo = '$iddo'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

?>
<div class="content-wrapper">
  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col mt-3">
          <form method="POST" action="prosesupdatedo.php">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <input type="hidden" name="iddo" value="<?= $iddo ?>">
                  <div class="col-2">
                    <div class="form-group">
                      <label for="deliverydate">Tgl Kirim <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <input type="date" class="form-control" name="deliverydate" id="deliverydate" value="<?= $row['deliverydate']; ?>">
                      </div>
                    </div>
                  </div>
                  <div class="col-4">
                    <div class="form-group">
                      <label for="idcustomer">Customer <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <input type="text" class="form-control" readonly value="<?= $row['nama_customer']; ?>">
                        <input type="hidden" value="<?= $row['idcustomer']; ?>">
                      </div>
                    </div>
                  </div>
                  <div class="col">
                    <div class="form-group">
                      <label for="alamat">Alamat <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <input type="text" readonly class="form-control" value="<?= $row['alamat1']; ?>">
                      </div>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-2">
                    <div class="form-group">
                      <label for="po">Cust PO</label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="po" id="po" value="<?= $row['po']; ?>">
                      </div>
                    </div>
                  </div>
                  <div class="col-2">
                    <div class="form-group">
                      <label for="driver">Driver</label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="driver" id="driver" value="<?= $row['driver']; ?>">
                      </div>
                    </div>
                  </div>
                  <div class="col-2">
                    <div class="form-group">
                      <label for="plat">Plat Number</label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="plat" id="plat" value="<?= $row['plat']; ?>">
                      </div>
                    </div>
                  </div>
                  <div class="col">
                    <div class="form-group">
                      <label for="note">Catatan</label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="note" id="note" placeholder="keterangan" value="<?= $row['note']; ?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-2 ml-1">
                    <button type="submit" class="btn btn-block bg-gradient-primary" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')"><i class="fas fa-paper-plane"></i> Update</button>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
</div>
</section>

<script src="../dist/js/calculateTotals.js"></script>
<script src="../dist/js/movefocus.js"></script>
<script>
  document.title = "Edit Do";
</script>

<?php
include "../footer.php";
?>