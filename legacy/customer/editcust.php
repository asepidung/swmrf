<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Mendapatkan data customer berdasarkan ID
$idCustomer = $_GET['id']; // Mendapatkan ID dari URL
$queryCustomer = "SELECT * FROM customers WHERE idcustomer = $idCustomer";
$resultCustomer = mysqli_query($conn, $queryCustomer);
$rowCustomer = mysqli_fetch_assoc($resultCustomer);

?>

<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <!-- left column -->
            <div class="col-lg-8 mx-auto">
               <!-- general form elements -->
               <div class="card card-dark mt-3">
                  <div class="card-header">
                     <h3 class="card-title">Edit Data Customer</h3>
                  </div>
                  <form method="POST" action="updatecustomer.php">
                     <input type="hidden" name="idcustomer" value="<?= $rowCustomer['idcustomer']; ?>">
                     <div class=" card-body">
                        <div class="form-group">
                           <label for="nama_customer">Nama Customer <span class="text-danger">*</span></label>
                           <input type="text" class="form-control" name="nama_customer" id="nama_customer" value="<?= $rowCustomer['nama_customer']; ?>" autofocus required>
                        </div>
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
                                    $selected = ($idgroup == $rowCustomer['idgroup']) ? "selected" : "";
                                    echo "<option value=\"$idgroup\" $selected>$nmgroup</option>";
                                 }
                                 ?>
                              </select>
                           </div>
                        </div>
                        <div class="form-group">
                           <label for="alamat">Alamat</label>
                           <input type="text" class="form-control" name="alamat" id="alamat" value="<?= $rowCustomer['alamat1']; ?>">
                        </div>
                        <div class="form-group">
                           <label for="idsegment">Segment <span class="text-danger">*</span></label>
                           <div class="input-group">
                              <select class="form-control" name="idsegment" id="idsegment" required>
                                 <option value="">Pilih Segment</option>
                                 <?php
                                 $query = "SELECT * FROM segment";
                                 $result = mysqli_query($conn, $query);
                                 // Generate options based on the retrieved data
                                 while ($row = mysqli_fetch_assoc($result)) {
                                    $idsegment = $row['idsegment'];
                                    $nmsegment = $row['nmsegment'];
                                    $selected = ($idsegment == $rowCustomer['idsegment']) ? 'selected' : '';
                                    echo "<option value=\"$idsegment\" $selected>$nmsegment</option>";
                                 }
                                 ?>
                              </select>
                              <div class="input-group-append">
                                 <a href="../segment/segment.php" class="btn btn-warning"><i class="fas fa-plus"></i></a>
                              </div>
                           </div>
                        </div>
                        <div class="form-group">
                           <label for="top">T.O.P</label>
                           <input type="number" class="form-control" name="top" id="top" value="<?= $rowCustomer['top']; ?>">
                        </div>
                        <!-- <div class="form-group">
                           <label for="pajak">Customer Dikenakan Pajak</label>
                           <select class="form-control" name="pajak" id="pajak">
                              <option>--Pilih Satu--</option>
                              <option value="YES" <?= ($rowCustomer['pajak'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                              <option value="NO" <?= ($rowCustomer['pajak'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                           </select>
                        </div> -->
                        <div class="form-group">
                           <label for="tukarfaktur">Tukar Faktur</label>
                           <select class="form-control" name="tukarfaktur" id="tukarfaktur">
                              <option>--Pilih Satu--</option>
                              <option value="YES" <?= ($rowCustomer['tukarfaktur'] == 'YES') ? 'selected' : ''; ?>>YES</option>
                              <option value="NO" <?= ($rowCustomer['tukarfaktur'] == 'NO') ? 'selected' : ''; ?>>NO</option>
                           </select>
                        </div>
                        <!-- <div class="form-group">
                           <label for="telepon">Telepon</label>
                           <input type="tel" class="form-control" name="telepon" id="telepon" value="<?= $rowCustomer['telepon']; ?>">
                        </div>
                        <div class="form-group">
                           <label for="email">Email</label>
                           <input type="text" class="form-control" name="email" id="email" value="<?= $rowCustomer['email']; ?>">
                        </div> -->
                        <div class="form-group">
                           <label for="catatan">Catatan</label>
                           <textarea name="catatan" id="catatan" rows="2" class="form-control"><?= $rowCustomer['catatan']; ?></textarea>
                        </div>

                        <!-- Dokumen yang Diperlukan -->
                        <div class="form-group">
                           <label>Dokumen yang Diperlukan Saat Pengiriman</label>
                           <div class="row">
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" value="invoice" id="invoice" <?= ($rowCustomer['invoice'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="invoice">Invoice</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" value="NKV" id="nkv" <?= ($rowCustomer['nkv'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="nkv">NKV</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" value="Halal" id="halal" <?= ($rowCustomer['halal'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="halal">Sertifikat Halal</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" value="SV" id="sv" <?= ($rowCustomer['sv'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sv">SV</label>
                                 </div>
                              </div>
                           </div>
                           <div class="row">
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" value="Joss" id="joss" <?= ($rowCustomer['joss'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="joss">JOSS</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" value="PHD" id="phd" <?= ($rowCustomer['phd'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="phd">PHD</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" value="Uji Lab" id="ujilab" <?= ($rowCustomer['ujilab'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ujilab">Uji Lab</label>
                                 </div>
                              </div>
                              <div class="col">
                              </div>
                           </div>
                           <div class="form-group text-right">
                              <button type="submit" class="btn bg-gradient-primary">Update</button>
                           </div>
                        </div>
                        <!-- /.card-body -->
                  </form>
               </div>
               <!-- /.card -->
            </div>
         </div>
      </div>
   </section>
   <!-- /.content -->
</div>

<?php include '../footer.php'; ?>