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
         <div class="row justify-content-center">
            <div class="col-md-6">
               <div class="card card-dark mt-3">
                  <div class="card-header">
                     <h3 class="card-title">Data Customer Baru</h3>
                  </div>
                  <form method="POST" action="inputcustomer.php">
                     <div class="card-body">
                        <div class="form-group">
                           <div class="input-group">
                              <select class="form-control" name="idgroup" id="idgroup" required>
                                 <option value="">Pilih Group</option>
                                 <?php
                                 $query = "SELECT * FROM groupcs ORDER BY nmgroup ASC";
                                 $result = mysqli_query($conn, $query);
                                 while ($row = mysqli_fetch_assoc($result)) {
                                    $idgroup = $row['idgroup'];
                                    $nmgroup = $row['nmgroup'];
                                    echo "<option value=\"$idgroup\">$nmgroup</option>";
                                 }
                                 ?>
                              </select>
                              <div class="input-group-append">
                                 <a href="../group/newgroup.php" class="btn btn-dark"><i class="fas fa-plus"></i></a>
                              </div>
                           </div>
                        </div>

                        <div class="form-group">
                           <input type="text" class="form-control" placeholder="Nama Customer" name="nama_customer" id="nama_customer" required autofocus>
                        </div>

                        <div class="form-group">
                           <input type="text" class="form-control" placeholder="Alamat" name="alamat" id="alamat" required>
                        </div>

                        <div class="form-group">
                           <div class="input-group">
                              <select class="form-control" name="idsegment" id="idsegment" required>
                                 <option value="">Payment Method</option>
                                 <?php
                                 $query = "SELECT * FROM segment";
                                 $result = mysqli_query($conn, $query);
                                 while ($row = mysqli_fetch_assoc($result)) {
                                    $idsegment = $row['idsegment'];
                                    $nmsegment = $row['nmsegment'];
                                    echo "<option value=\"$idsegment\">$nmsegment</option>";
                                 }
                                 ?>
                              </select>
                              <div class="input-group-append">
                                 <a href="../segment/segment.php" class="btn btn-warning"><i class="fas fa-plus"></i></a>
                              </div>
                           </div>
                        </div>

                        <div class="form-group">
                           <input type="number" class="form-control" placeholder="T.O.P" name="top" id="top" required>
                        </div>

                        <div class="form-group">
                           <select class="form-control" name="tukarfaktur" id="tukarfaktur" required>
                              <option value="">Tukar Faktur</option>
                              <option value="NO">NO</option>
                              <option value="YES">YES</option>
                           </select>
                        </div>

                        <!-- <div class="form-group">
                           <input type="tel" class="form-control" placeholder="Telepon" name="telepon" id="telepon">
                        </div> -->

                        <div class="form-group">
                           <input type="text" class="form-control" placeholder="Catatan" name="catatan" id="catatan">
                        </div>

                        <div class="form-group">
                           <label>Dokumen yang Diperlukan Saat pengiriman :</label>
                           <div class="row">
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="invoice" value="invoice">
                                    <label class="form-check-label" for="invoice">Invoice</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="nkv" value="NKV">
                                    <label class="form-check-label" for="nkv">NKV</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="halal" value="Halal">
                                    <label class="form-check-label" for="halal">Halal</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="sv" value="SV">
                                    <label class="form-check-label" for="sv">SV</label>
                                 </div>
                              </div>
                           </div>
                           <div class="row">
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="joss" value="Joss">
                                    <label class="form-check-label" for="joss">Joss</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="phd" value="PHD">
                                    <label class="form-check-label" for="phd">PHD</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="ujilab" value="Uji Lab">
                                    <label class="form-check-label" for="ujilab">Uji Lab</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <!-- <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dokumen[]" id="booking" value="booking">
                                    <label class="form-check-label" for="booking">Booking</label>
                                 </div> -->
                              </div>
                           </div>
                        </div>
                        <div class="form-group">
                           <button type="submit" class="btn btn-primary btn-block">Submit</button>
                        </div>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<?php include "../footer.php" ?>