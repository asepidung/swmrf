<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idpricelist = $_GET['idpricelist'];

$query = "SELECT * FROM pricelist WHERE idpricelist = '$idpricelist'";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

?>
<div class="content-wrapper">
  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col mt-3">
          <form method="POST" action="prosesupdatepricelist.php">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <input type="hidden" name="idpricelist" value="<?= $idpricelist ?>">
                  <div class="col-4">
                    <div class="form-group">
                      <label for="idgroup">Group <span class="text-danger">*</span></label>
                      <div class="input-group">
                        <select class="form-control" name="idgroup" id="idgroup">
                          <option value="">Pilih Group</option>
                          <?php
                          $query = "SELECT * FROM groupcs ORDER BY nmgroup ASC";
                          $result = mysqli_query($conn, $query);
                          while ($groupRow = mysqli_fetch_assoc($result)) {
                            $idgroup = $groupRow['idgroup'];
                            $nmgroup = $groupRow['nmgroup'];
                            $selected = ($idgroup == $row['idgroup']) ? "selected" : "";
                            echo "<option value=\"$idgroup\" $selected>$nmgroup</option>";
                          }
                          ?>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="col-2">
                    <div class="form-group">
                      <label for="up">U.P</label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="up" id="up" value="<?= $row['up']; ?>">
                      </div>
                    </div>
                  </div>
                  <div class="col">
                    <div class="form-group">
                      <label for="note">Note</label>
                      <div class="input-group">
                        <input type="text" class="form-control" name="note" id="note" value="<?= $row['note']; ?>">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="card">
              <div class="card-body">
                <div id="items-container">
                  <div class="row mb-2">
                    <div class="col-4">
                      Product Desc
                    </div>
                    <div class="col-2">
                      Price
                    </div>
                    <div class="col">
                      Notes
                    </div>
                    <div class="col-1">
                      Action
                    </div>
                  </div>
                  <?php
                  $query_pricelistdetail = "SELECT pricelistdetail.*, barang.nmbarang
                      FROM pricelistdetail
                      INNER JOIN barang ON pricelistdetail.idbarang = barang.idbarang
                      WHERE idpricelist = '$idpricelist'";
                  $result_pricelistdetail = mysqli_query($conn, $query_pricelistdetail);
                  while ($row_pricelistdetail = mysqli_fetch_assoc($result_pricelistdetail)) { ?>
                    <div class="row mb-n2">
                      <div class="col-4">
                        <div class="form-group">
                          <div class="input-group">
                            <select class="form-control" name="idbarang[]" id="idbarang">
                              <option value="">Pilih Barang</option>
                              <?php
                              $querybarang = "SELECT * FROM barang ORDER BY nmbarang ASC";
                              $resultbarang = mysqli_query($conn, $querybarang);
                              while ($barangRow = mysqli_fetch_assoc($resultbarang)) {
                                $idbarang = $barangRow['idbarang'];
                                $nmbarang = $barangRow['nmbarang'];
                                $selectedbarang = ($idbarang == $row_pricelistdetail['idbarang']) ? "selected" : "";
                                echo "<option value=\"$idbarang\" $selectedbarang>$nmbarang</option>";
                              }
                              ?>
                            </select>
                          </div>
                        </div>
                      </div>
                      <div class="col-2">
                        <div class="form-group">
                          <div class="input-group">
                            <input type="text" name="price[]" class="form-control text-right" value="<?= $row_pricelistdetail['price']; ?>" required onkeydown="moveFocusToNextInput(event, this, 'weight[]' )">
                          </div>
                        </div>
                      </div>
                      <div class="col">
                        <div class="form-group">
                          <div class="input-group">
                            <input type="text" name="notes[]" class="form-control" value="<?= $row_pricelistdetail['notes']; ?>">
                          </div>
                        </div>
                      </div>
                      <div class="col-1">
                        <button type="button" class="btn btn-link text-danger btn-remove-item" onclick="removeItem(this)">
                          <i class="fas fa-minus-circle"></i>
                        </button>
                      </div>
                    </div>
                  <?php } ?>
                </div>
                <div class="row">
                  <div class="col-1">
                    <button type="button" class="btn btn-link text-success" onclick="addItem()"><i class="fas fa-plus-circle"></i></button>
                  </div>
                  <div class="col-5"></div>
                  <div class="col">
                    <button type="submit" class="btn btn-block bg-gradient-primary" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')">Update</button>
                  </div>
                  <div class="col-1"></div>
                </div>
              </div>
            </div>
          </form>
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

<!-- Kode JavaScript -->
<script src="../dist/js/movefocus.js"></script>
<script>
  function addItem() {
    var itemsContainer = document.getElementById('items-container');

    // Baris item baru
    var newItemRow = document.createElement('div');
    newItemRow.className = 'item-row';

    // Konten baris item baru
    newItemRow.innerHTML = `
<div class="row mb-n2">
<div class="col-4">
<div class="form-group">
<div class="input-group">
<select class="form-control" name="idbarang[]" required>
<option value="">--Pilih--</option>
<?php
$query = "SELECT * FROM barang ORDER BY nmbarang ASC";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
  $idbarang = $row['idbarang'];
  $nmbarang = $row['nmbarang'];
  echo '<option value="' . $idbarang . '">' . $nmbarang . '</option>';
}
?>
</select>
</div>
</div>
</div>
<div class="col-2">
<div class="form-group">
<div class="input-group">
<input type="text" name="price[]" class="form-control text-right" required onkeydown="moveFocusToNextInput(event, this, 'price[]')">
</div>
</div>
</div>
<div class="col">
<div class="form-group">
<div class="input-group">
<input type="text" name="notes[]" class="form-control">
</div>
</div>
</div>
<div class="col-1">
<button type="button" class="btn btn-link text-danger btn-remove-item" onclick="removeItem(this)">
<i class="fas fa-minus-circle"></i>
</button>
</div>
</div>
`;

    // Tambahkan baris item baru ke dalam container
    itemsContainer.appendChild(newItemRow);
  }

  function removeItem(button) {
    var itemRow = button.closest('.row');

    // Hapus baris item
    itemRow.remove();
  }


  document.title = "Edit Pricelist";
</script>

<?php
include "../footer.php";
?>