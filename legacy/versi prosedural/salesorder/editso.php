<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Ambil data utama SO
if (isset($_GET['idso'])) {
   $idso = $_GET['idso'];
   $query = "SELECT * FROM salesorder WHERE idso = $idso";
   $result = mysqli_query($conn, $query);
   $data = mysqli_fetch_assoc($result);

   if (!$data) {
      echo "Data tidak ditemukan.";
      exit;
   }
} else {
   echo "ID salesorder tidak diberikan.";
   exit;
}
?>
<div class="content-wrapper">
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="updateso.php">
                  <input type="hidden" name="idso" value="<?php echo $data['idso']; ?>">
                  <div class="card">
                     <div class="card-body">
                        <div class="row">
                           <div class="col-4">
                              <div class="form-group">
                                 <label for="idcustomer">Customer <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <select class="form-control" name="idcustomer" id="idcustomer" required>
                                       <option value="">Pilih Customer</option>
                                       <?php
                                       $query = "SELECT * FROM customers ORDER BY nama_customer ASC";
                                       $result = mysqli_query($conn, $query);
                                       while ($row = mysqli_fetch_assoc($result)) {
                                          $idcustomer = $row['idcustomer'];
                                          $nama_customer = $row['nama_customer'];
                                          $selected = ($idcustomer == $data['idcustomer']) ? "selected" : "";
                                          echo "<option value=\"$idcustomer\" $selected>$nama_customer</option>";
                                       }
                                       ?>
                                    </select>
                                    <div class="input-group-append">
                                       <a href="../customer/newcustomer.php" class="btn btn-dark"><i class="fas fa-plus"></i></a>
                                    </div>
                                 </div>
                              </div>
                           </div>
                           <div class="col-2">
                              <div class="form-group">
                                 <label for="deliverydate">Tgl Kirim <span class="text-danger">*</span></label>
                                 <input type="date" class="form-control" name="deliverydate" id="deliverydate" required value="<?php echo $data['deliverydate']; ?>">
                              </div>
                           </div>
                           <div class="col-2">
                              <div class="form-group">
                                 <label for="po">Cust PO</label>
                                 <input type="text" class="form-control" name="po" id="po" value="<?php echo $data['po']; ?>">
                                 <input type="hidden" name="sonumber" id="sonumber" value="<?php echo $data['sonumber']; ?>">
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <label for="alamat">Alamat <span class="text-danger">*</span></label>
                                 <input type="text" class="form-control" name="alamat" id="alamat" value="<?php echo $data['alamat']; ?>">
                              </div>
                           </div>
                        </div>
                        <div class="row">
                           <div class="col">
                              <div class="form-group">
                                 <input type="text" class="form-control" name="note" id="note" placeholder="Catatan Untuk Penyiapan Pengiriman" value="<?php echo $data['note']; ?>">
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Detail Barang -->
                  <div class="card">
                     <div class="card-body">
                        <div id="items-container">
                           <div class="row mb-2">
                              <div class="col-2">Product</div>
                              <div class="col-2">Weight</div>
                              <div class="col-2">Price</div>
                              <div class="col-2">Discount</div>
                              <div class="col-3">Notes</div>
                              <div class="col-1"></div>
                           </div>

                           <?php
                           $query = "SELECT * FROM salesorderdetail WHERE idso = $idso";
                           $result = mysqli_query($conn, $query);
                           while ($row = mysqli_fetch_assoc($result)) {
                           ?>
                              <div class="row item-row mb-2">
                                 <div class="col-2">
                                    <select class="form-control" name="idbarang[]" required>
                                       <option value="">--Pilih--</option>
                                       <?php
                                       $barangQuery = "SELECT * FROM barang ORDER BY nmbarang ASC";
                                       $barangResult = mysqli_query($conn, $barangQuery);
                                       while ($barang = mysqli_fetch_assoc($barangResult)) {
                                          $selected = ($barang['idbarang'] == $row['idbarang']) ? "selected" : "";
                                          echo "<option value=\"{$barang['idbarang']}\" $selected>{$barang['nmbarang']}</option>";
                                       }
                                       ?>
                                    </select>
                                 </div>
                                 <div class="col-2">
                                    <input type="text" name="weight[]" class="form-control text-right" required value="<?php echo $row['weight']; ?>">
                                 </div>
                                 <div class="col-2">
                                    <input type="text" name="price[]" class="form-control text-right" required value="<?php echo $row['price']; ?>">
                                 </div>
                                 <div class="col-2">
                                    <input type="text" name="discount[]" class="form-control text-right" value="<?php echo $row['discount']; ?>">
                                 </div>
                                 <div class="col-3">
                                    <input type="text" name="notes[]" class="form-control" value="<?php echo $row['notes']; ?>">
                                 </div>
                                 <div class="col-1">
                                    <button type="button" class="btn btn-link text-danger btn-remove-item" onclick="removeItem(this)">
                                       <i class="fas fa-minus-circle"></i>
                                    </button>
                                 </div>
                              </div>
                           <?php } ?>
                        </div>

                        <div class="row mt-3">
                           <div class="col-1">
                              <button type="button" class="btn btn-link text-success" onclick="addItem()"><i class="fas fa-plus-circle"></i></button>
                           </div>
                           <div class="col-6"></div>
                           <div class="col-2">
                              <button type="button" class="btn btn-block bg-gradient-warning"><i class="fas fa-share-alt"></i> Submit & Share</button>
                           </div>
                           <div class="col-2">
                              <button type="submit" class="btn btn-block bg-gradient-primary" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')">Submit</button>
                           </div>
                           <div class="col-1"></div>
                        </div>
                     </div>
                  </div>
               </form>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   function addItem() {
      const container = document.getElementById('items-container');
      const newRow = document.createElement('div');
      newRow.className = 'row item-row mb-2';
      newRow.innerHTML = `
         <div class="col-2">
            <select class="form-control" name="idbarang[]" required>
               <option value="">--Pilih--</option>
               <?php
               $barangQuery = mysqli_query($conn, "SELECT * FROM barang ORDER BY nmbarang ASC");
               while ($barang = mysqli_fetch_assoc($barangQuery)) {
                  echo "<option value=\"{$barang['idbarang']}\">{$barang['nmbarang']}</option>";
               }
               ?>
            </select>
         </div>
         <div class="col-2">
            <input type="text" name="weight[]" class="form-control text-right" required>
         </div>
         <div class="col-2">
            <input type="text" name="price[]" class="form-control text-right" required>
         </div>
         <div class="col-2">
            <input type="text" name="discount[]" class="form-control text-right">
         </div>
         <div class="col-3">
            <input type="text" name="notes[]" class="form-control">
         </div>
         <div class="col-1">
            <button type="button" class="btn btn-link text-danger btn-remove-item" onclick="removeItem(this)">
               <i class="fas fa-minus-circle"></i>
            </button>
         </div>
      `;
      container.appendChild(newRow);
   }

   function removeItem(button) {
      const row = button.closest('.item-row');
      if (row) row.remove();
   }

   document.title = "Edit Sales Order";
</script>

<?php include "../footer.php"; ?>