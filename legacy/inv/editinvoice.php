<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idinvoice = $_GET['idinvoice'];

// Query untuk mengambil data dari tabel invoice berdasarkan idinvoice
$query_invoice = "SELECT invoice.*, customers.nama_customer, customers.pajak
                  FROM invoice
                  INNER JOIN customers ON invoice.idcustomer = customers.idcustomer
                  WHERE invoice.idinvoice = '$idinvoice'";
$result_invoice = mysqli_query($conn, $query_invoice);
$row_invoice = mysqli_fetch_assoc($result_invoice);

// Query untuk mengambil data dari tabel invoicedetail berdasarkan idinvoice
$query_invoicedetail = "SELECT invoicedetail.*, barang.nmbarang
                        FROM invoicedetail
                        INNER JOIN barang ON invoicedetail.idbarang = barang.idbarang
                        WHERE invoicedetail.idinvoice = '$idinvoice'";
$result_invoicedetail = mysqli_query($conn, $query_invoicedetail);

?>

<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="updateinvoice.php">
                  <div class="card">
                     <div class="card-body">
                        <div class="row">
                           <div class="col">
                              <div class="form-group">
                                 <label for="invoice_date">Invoice Date <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <input type="hidden" name="idinvoice" value="<?= $idinvoice; ?>">
                                    <input type="date" class="form-control" name="invoice_date" id="invoice_date" value="<?= $row_invoice['invoice_date']; ?>">
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label for="nama_customer">Nama Customer</label>
                                 <div class="input-group">
                                    <input type="hidden" name="idcustomer" id="idcustomer" value="<?= $row_invoice['idcustomer'] ?>">
                                    <input type="text" class="form-control" name="nama_customer" id="nama_customer" value="<?= $row_invoice['nama_customer'] ?>" readonly>
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label for="pocustomer">PO Number</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="pocustomer" id="pocustomer" value="<?= $row_invoice['pocustomer'] ?>">
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label for="donumber">DO Number</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="donumber" id="donumber" value="<?= $row_invoice['donumber'] ?>" readonly>
                                    <input type="hidden" name="noinvoice" value="<?= $row_invoice['noinvoice'] ?>">

                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="row">
                           <div class="col">
                              <div class="form-group">
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="note" id="note" value="<?= $row_invoice['note'] ?>">
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
                           <?php while ($row_invoicedetail = mysqli_fetch_assoc($result_invoicedetail)) { ?>
                              <div class="row mt-n2">
                                 <div class="col">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control" name="nmbarang" id="nmbarang" value="<?= $row_invoicedetail['nmbarang'] ?>" readonly>
                                          <input type="hidden" class="form-control" name="idbarang[]" id="idbarang" value="<?= $row_invoicedetail['idbarang'] ?>" readonly>
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-1">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-right" name="weight[]" value="<?= $row_invoicedetail['weight'] ?>" readonly>
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-2">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-right" name="price[]" value="<?= $row_invoicedetail['price'] ?>" required onkeydown="moveFocusToNextInput(event, this, 'price[]')">
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-1">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-center" name="discount[]" value="<?= $row_invoicedetail['discount'] ?>" onkeydown="moveFocusToNextInput(event, this, 'discount[]')">
                                       </div>
                                    </div>
                                 </div>
                                 <div class="col-2">
                                    <div class="form-group">
                                       <div class="input-group">
                                          <input type="text" class="form-control text-right" name="discountrp[]" value="<?= $row_invoicedetail['discountrp'] ?>" readonly>
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
                           <div class="col-4 text-right">Weight Total</div>
                           <div class="col-1">
                              <input type="text" name="xweight" id="xweight" class="form-control text-right" readonly value="<?= $row_invoice['xweight'] ?>">
                           </div>
                           <div class="col-5 text-right">
                              Total Amount
                           </div>
                           <div class="col-2">
                              <input type="text" name="xamount" id="xamount" class="form-control text-right" readonly value="<?= $row_invoice['xamount'] ?>">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Tax 11%
                           </div>
                           <div class="col-2">
                              <input type="text" name="tax" id="tax" class="form-control text-right" readonly value="<?= $row_invoice['tax'] ?>">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Charge
                           </div>
                           <div class="col-2">
                              <input type="text" name="charge" id="charge" class="form-control text-right" value="<?= $row_invoice['charge'] ?>">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Down Payment
                           </div>
                           <div class="col-2">
                              <input type="text" name="downpayment" id="downpayment" class="form-control text-right" value="<?= $row_invoice['downpayment'] ?>">
                           </div>
                        </div>
                        <div class="row mt-1">
                           <div class="col-10 text-right">
                              Balance
                           </div>
                           <div class="col-2">
                              <input type="text" name="balance" id="balance" class="form-control text-right" readonly value="<?= $row_invoice['balance'] ?>">
                              <input type="hidden" name="xdiscount" id="xdiscount" value="<?= $row_invoice['xdiscount'] ?>">
                           </div>
                        </div>
                        <div class="row">
                           <div class="col-3">
                              <button type="button" class="btn btn-block bg-gradient-warning" onclick="calculateAmounts()">Calculate</button>
                           </div>
                           <div class="col-3">
                              <button type="submit" class="btn btn-block bg-gradient-primary" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')" disabled id="submit-btn">Update</button>
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
   document.title = "Edit Invoice";
</script>
<?php
include "../footer.php";
?>