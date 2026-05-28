<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
// include "invnumber.php";
$iddoreceipt = $_GET['iddoreceipt'];

// Mengambil data dari tabel do
$queryDo = "SELECT doreceipt.*, customers.nama_customer, customers.pajak, customers.top, customers.idgroup, customers.tukarfaktur, segment.idsegment, do.iddo
            FROM doreceipt
            INNER JOIN customers ON doreceipt.idcustomer = customers.idcustomer
            INNER JOIN segment ON customers.idsegment = segment.idsegment
            INNER JOIN do ON doreceipt.iddo = do.iddo
            WHERE doreceipt.iddoreceipt = $iddoreceipt";
$resultDo = mysqli_query($conn, $queryDo);
$rowDo = mysqli_fetch_assoc($resultDo);
$tukarfaktur = $rowDo['tukarfaktur'];
$pajak = $rowDo['pajak'];
$top = $rowDo['top'];
$idsegment = $rowDo['idsegment'];
$iddo = $rowDo['iddo'];
$idso = $rowDo['idso'];
$idgroup = $rowDo['idgroup'];
// Mengambil data dari tabel doreceiptdetail, grade, dan barang
$querydoreceiptdetail = "SELECT doreceiptdetail.*, barang.nmbarang, barang.kdbarang, sodetail.price, sodetail.discount
                         FROM doreceiptdetail
                         INNER JOIN barang ON doreceiptdetail.idbarang = barang.idbarang
                         INNER JOIN salesorderdetail sodetail ON sodetail.idbarang = barang.idbarang
                         INNER JOIN salesorder so ON sodetail.idso = so.idso
                         WHERE doreceiptdetail.iddoreceipt = $iddoreceipt
                         AND so.idso = $idso";

$resultdoreceiptdetail = mysqli_query($conn, $querydoreceiptdetail);

?>
<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="prosesinvoice.php">
                  <!-- <input type="hidden" value="<?= $noinvoice ?>" name="noinvoice" id="noinvoice"> -->
                  <input type="hidden" value="<?= $iddoreceipt ?>" name="iddoreceipt" id="iddoreceipt">
                  <input type="hidden" value="<?= $idsegment; ?>" name="idsegment" id="idsegment">
                  <input type="hidden" value="<?= $idgroup; ?>" name="idgroup" id="idgroup">
                  <input type="hidden" value="<?= $top; ?>" name="top">
                  <input type="hidden" value="<?= $iddo; ?>" name="iddo">
                  <input type="hidden" value="<?= $pajak; ?>" name="pajak">
                  <input type="hidden" value="<?= $tukarfaktur; ?>" name="tukarfaktur" id="tukarfaktur">
                  <div class="card">
                     <div class="card-body">
                        <div class="row">
                           <div class="col">
                              <div class="form-group">
                                 <label for="invoice_date">Invoice Date <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <input type="date" class="form-control" name="invoice_date" id="invoice_date" required autofocus>
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label for="nama_customer">Nama Customer</label>
                                 <div class="input-group">
                                    <input type="hidden" name="idcustomer" id="idcustomer" value="<?= $rowDo['idcustomer'] ?>">
                                    <input type="text" class="form-control" name="nama_customer" id="nama_customer" value="<?= $rowDo['nama_customer'] ?>" readonly>
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label for="pocustomer">PO Number</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="pocustomer" id="pocustomer" value="<?= $rowDo['po'] ?>" readonly>
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label for="donumber">DO Number</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="donumber" id="donumber" value="<?= $rowDo['donumber'] ?>" readonly>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="row">
                           <div class="col">
                              <div class="form-group">
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="note" id="note" placeholder="keterangan">
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="card">
                     <div class="card-body">
                        <div id="items-container">
                           <div class="row">
                              <div class="col">
                                 <div class="form-group">
                                    <label>Products</label>
                                 </div>
                              </div>
                              <div class="col-1">
                                 <div class="form-group">
                                    <label>Weight</label>
                                 </div>
                              </div>
                              <div class="col-2">
                                 <div class="form-group">
                                    <label>Price <span class="text-danger">*</span></label>
                                 </div>
                              </div>
                              <div class="col-1">
                                 <div class="form-group">
                                    <label>Disc %</label>
                                 </div>
                              </div>
                              <div class="col-2">
                                 <div class="form-group">
                                    <label>Disc Rp</label>
                                 </div>
                              </div>
                              <div class="col-2">
                                 <div class="form-group">
                                    <label>Amount</label>
                                 </div>
                              </div>
                           </div>
                           <?php while ($rowdoreceiptdetail = mysqli_fetch_assoc($resultdoreceiptdetail)) { ?>
                              <div class="row mt-n2">
                                 <div class="col">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control" name="nmbarang" id="nmbarang" value="<?= $rowdoreceiptdetail['nmbarang'] ?>" readonly>
                                          <input type="hidden" class="form-control" name="idbarang[]" id="idbarang" value="<?= $rowdoreceiptdetail['idbarang'] ?>" readonly>
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-1">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-right" name="weight[]" value="<?= $rowdoreceiptdetail['weight'] ?>" readonly>
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-2">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-right" name="price[]" value="<?= $rowdoreceiptdetail['price'] ?>" required onkeydown="moveFocusToNextInput(event, this, 'price[]')">
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-1">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <?php
                                          // Menentukan nilai default discount sebagai 0
                                          $discountValue = 0;

                                          // Cek apakah 'nama_customer' mengandung 'DCA' atau 'DCB'
                                          if (
                                             isset($rowDo['nama_customer']) &&
                                             (strpos($rowDo['nama_customer'], 'DCA') !== false || strpos($rowDo['nama_customer'], 'DCB') !== false || strpos($rowDo['nama_customer'], 'DCC') !== false)
                                          ) {
                                             $discountValue = 2; // Diskon 2% untuk DCA atau DCB
                                          } elseif (isset($rowdoreceiptdetail['discount']) && is_numeric($rowdoreceiptdetail['discount'])) {
                                             // Jika bukan DCA atau DCB, gunakan nilai discount dari query join salesorderdetail jika ada
                                             $discountValue = $rowdoreceiptdetail['discount'];
                                          }

                                          // Pastikan nilai discount selalu 0 jika tidak valid
                                          $discountValue = is_numeric($discountValue) ? $discountValue : 0;
                                          ?>
                                          <input type="text" class="form-control text-center" name="discount[]" value="<?= htmlspecialchars($discountValue) ?>" onkeydown="moveFocusToNextInput(event, this, 'discount[]')">
                                       </div>

                                    </div>
                                 </div>
                                 <div class=" col-2">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-right" name="discountrp[]" value="0" readonly>
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-2">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-right" name="amount[]" readonly value="0">
                                       </div>
                                    </div>
                                 </div>
                              </div>
                           <?php } ?>
                        </div>
                        <div class="row">
                           <div class="col-1">
                              <button type="button" class="btn btn-link text-success" id="addRowButton">
                                 <i class="fas fa-plus-circle"></i>
                              </button>
                           </div>
                           <div class="col-3 text-right">Weight Total</div>
                           <div class="col-1">
                              <input type="text" name="xweight" id="xweight" class="form-control text-right" readonly>
                           </div>
                           <div class="col-5 text-right">
                              Total Amount
                           </div>
                           <div class="col-2">
                              <input type="text" name="xamount" id="xamount" class="form-control text-right" readonly value="0">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Tax 11%
                           </div>
                           <div class="col-2">
                              <input type="text" name="tax" id="tax" class="form-control text-right" readonly value="0">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Charge
                           </div>
                           <div class="col-2">
                              <input type="text" name="charge" id="charge" class="form-control text-right" value="0">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Down Payment
                           </div>
                           <div class="col-2">
                              <input type="text" name="downpayment" id="downpayment" class="form-control text-right" value="0">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Balance
                           </div>
                           <div class="col-2">
                              <input type="text" name="balance" id="balance" class="form-control text-right" readonly value="0">
                              <input type="hidden" name="xdiscount" id="xdiscount" value="0">
                           </div>
                        </div>
                        <div class="row">
                           <div class="col-3">
                              <button type="button" class="btn btn-block bg-gradient-warning" onclick="calculateAmounts()">Calculate</button>
                           </div>
                           <div class="col-3">
                              <button type="submit" class="btn btn-block bg-gradient-primary" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')" disabled id="submit-btn">Submit</button>
                           </div>
                        </div>
                     </div>
                  </div>
               </form>
            </div>
            <!-- /.card -->
         </div>
      </div>
   </section>
</div>
<script src="../dist/js/hitunginvoice.js"></script>
<script src="../dist/js/movefocus.js"></script>
<script>
   document.title = "Invoice Baru";

   document.addEventListener('DOMContentLoaded', function() {
      // Dapatkan elemen tombol dan container
      var addRowButton = document.getElementById('addRowButton');
      var itemsContainer = document.getElementById('items-container');

      // Tambahkan event listener untuk tombol
      addRowButton.addEventListener('click', function() {
         // Buat elemen-elemen baru untuk row
         var newRow = document.createElement('div');
         newRow.classList.add('row', 'mt-n2');

         // Sesuaikan elemen-elemen baru dengan struktur row yang ada
         var html = `
            <div class="col">
               <div class="form-group">
                  <div class="input-group">
                     <select class="form-control" name="idbarang[]" id="idbarang" required>
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
            <div class="col-1">
               <div class="form-group">
                  <div class="input-group">
                     <input type="text" class="form-control text-right" name="weight[]" value="">
                  </div>
               </div>
            </div>
            <div class="col-2">
               <div class="form-group">
                  <div class="input-group">
                     <input type="text" class="form-control text-right" name="price[]" required onkeydown="moveFocusToNextInput(event, this, 'price[]')">
                  </div>
               </div>
            </div>
            <div class="col-1">
               <div class="form-group">
                  <div class="input-group">
                   
                     <input type="text" class="form-control text-right" name="discount[]" value="0" onkeydown="moveFocusToNextInput(event, this, 'discount[]')" readonly>
                  </div>
               </div>
            </div>
            <div class="col-2">
               <div class="form-group">
                  <div class="input-group">
                     <input type="text" class="form-control text-right" name="discountrp[]" value="0" readonly>
                  </div>
               </div>
            </div>
            <div class="col-2">
               <div class="form-group">
                  <div class="input-group">
                     <input type="text" class="form-control text-right" name="amount[]" readonly value="0">
                  </div>
               </div>
            </div>`;

         newRow.innerHTML = html;
         itemsContainer.appendChild(newRow);
      });
   });
</script>

<?php
include "../footer.php";
?>