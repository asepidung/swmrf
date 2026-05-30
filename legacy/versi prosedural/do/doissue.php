<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idtally = $_GET['id'];
$query = "SELECT tally.*, customers.nama_customer, customers.alamat1
FROM tally 
INNER JOIN customers ON tally.idcustomer = customers.idcustomer
WHERE idtally = $idtally";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$sonumber = $row['sonumber'];
?>
<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="inputdo.php">
                  <div class="card">
                     <div class="card-body">
                        <div class="row">
                           <div class="col-2">
                              <div class="form-group">
                                 <label for="deliverydate">Tgl Kirim <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <input type="hidden" name="idso" value="<?= $row['idso']; ?>">
                                    <input type="hidden" name="idtally" value="<?= $idtally; ?>">
                                    <input type="date" class="form-control" name="deliverydate" id="deliverydate" value="<?= $row['deliverydate'] ?>">
                                 </div>
                              </div>
                           </div>
                           <div class="col-3">
                              <div class="form-group">
                                 <label for="idcustomer">Customer <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" value="<?= $row['nama_customer'] ?>" readonly>
                                    <input type="hidden" name="idcustomer" id="idcustomer" value="<?= $row['idcustomer'] ?>">
                                 </div>
                              </div>
                           </div>
                           <div class="col-4">
                              <div class="form-group">
                                 <label for="alamat">Alamat <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" value="<?= $row['alamat1']; ?>" readonly>
                                    <!-- <select class="form-control" name="alamat" id="alamat" required>
                                       <option value="">Pilih Alamat</option>
                                    </select> -->
                                 </div>
                              </div>
                           </div>
                           <div class="col-3">
                              <div class="form-group">
                                 <label for="po">Cust PO</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="po" id="po" value="<?= $row['po']; ?>">
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="row">
                           <div class="col-2">
                              <div class="form-group">
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="sonumber" id="sonumber" value="<?= $sonumber; ?>" readonly>
                                 </div>
                              </div>
                           </div>
                           <div class=" col-3">
                              <div class="form-group">
                                 <input type="text" class="form-control" name="driver" id="driver" placeholder="Driver">
                              </div>
                           </div>
                           <div class="col-4">
                              <div class="form-group">
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="plat" id="plat" placeholder="Police Number">
                                 </div>
                              </div>
                           </div>
                           <div class="col-3">
                              <div class="form-group">
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="sealnumb" id="sealnumb" value="<?= $row['sealnumb']; ?>">
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="row">
                           <div class="col">
                              <div class="form-group">
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="note" id="note" placeholder="Catatan">
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="card">
                     <div class="card-body">
                        <div id="items-container">
                           <div class="row mb-n3">
                              <div class="col-4">
                                 <div class="form-group">
                                    <label for="idbarang">Product</label>
                                 </div>
                              </div>
                              <div class="col-1">
                                 <div class="form-group">
                                    <label for="box">Box</label>
                                 </div>
                              </div>
                              <div class="col-2">
                                 <div class="form-group">
                                    <label for="weight">Weight</label>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-group">
                                    <label for="notes">Notes</label>
                                 </div>
                              </div>
                           </div>
                           <?php
                           $query_tallydetail = "SELECT tallydetail.idbarang, barang.nmbarang, SUM(tallydetail.weight) as total_weight, COUNT(tallydetail.weight) as total_box
                           FROM tallydetail
                           INNER JOIN barang ON tallydetail.idbarang = barang.idbarang
                           WHERE idtally = '$idtally'
                           GROUP BY tallydetail.idbarang, barang.nmbarang";
                           $result_tallydetail = mysqli_query($conn, $query_tallydetail);

                           while ($row_tallydetail = mysqli_fetch_assoc($result_tallydetail)) {
                              if ($row_tallydetail['total_weight'] > 0) {
                           ?>
                                 <div class="row mb-n2">
                                    <div class="col-4">
                                       <div class="form-group">
                                          <div class="input-group">
                                             <!-- Menampilkan nama barang yang sudah digabung -->
                                             <input type="hidden" name="idbarang[]" value="<?= $row_tallydetail['idbarang'] ?>">
                                             <input type="text" class="form-control" value="<?= $row_tallydetail['nmbarang']; ?>" readonly>
                                          </div>
                                       </div>
                                    </div>
                                    <div class="col-1">
                                       <div class="form-group">
                                          <div class="input-group">
                                             <!-- Menampilkan total jumlah kotak yang sudah dihitung -->
                                             <input type="text" name="box[]" class="form-control text-center" readonly value="<?= $row_tallydetail['total_box']; ?>">
                                          </div>
                                       </div>
                                    </div>
                                    <div class="col-2">
                                       <div class="form-group">
                                          <div class="input-group">
                                             <!-- Menampilkan total berat yang sudah dihitung -->
                                             <input type="text" name="weight[]" class="form-control text-right" readonly value="<?= $row_tallydetail['total_weight']; ?>">
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
                                 </div>
                           <?php
                              }
                           }
                           ?>
                           <div class="row">
                              <div class="col-4"></div>
                              <div class="col-1">
                                 <input type="text" name="xbox" id="xbox" class="text-center form-control" readonly>
                              </div>
                              <div class="col-2">
                                 <input type="text" name="xweight" id="xweight" class="form-control text-right" readonly>
                              </div>
                              <div class="col-2">
                                 <button type="button" class="btn btn-block bg-gradient-warning" onclick="calculateTotals()">Calculate</button>
                              </div>
                              <div class="col-2">
                                 <button type="submit" class="btn btn-block bg-gradient-primary" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')" disabled id="submit-btn">Submit</button>
                              </div>
                              <div class="col-1"></div>
                           </div>
                        </div>
                     </div>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </section>
</div>
<!-- <script src="../dist/js/fill_alamat_note.js"></script> -->
<script src="../dist/js/movefocus.js"></script>
<script src="../dist/js/calculateTotals.js"></script>
<script>
   document.title = "Made New Do";
</script>

<?php
// require "../footnotes.php";
include "../footer.php";
?>