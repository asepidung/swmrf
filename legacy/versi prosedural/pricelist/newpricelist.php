<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
// include "donumber.php";
?>
<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="inputpricelist.php">
                  <div class="card">
                     <div class="card-body">
                        <div class="row">
                           <div class="col-4">
                              <div class="form-group">
                                 <label for="idcustomer">Customer <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <select class="form-control" name="idgroup" id="idgroup" required>
                                       <option value="">Pilih Group</option>
                                       <?php
                                       $query = "SELECT * FROM groupcs ORDER BY nmgroup ASC";
                                       $result = mysqli_query($conn, $query);
                                       // Generate options based on the retrieved data
                                       while ($row = mysqli_fetch_assoc($result)) {
                                          $idgroup = $row['idgroup'];
                                          $nmgroup = $row['nmgroup'];
                                          echo "<option value=\"$idgroup\">$nmgroup</option>";
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
                                 <Label for="up">U.P</Label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="up" id="up">
                                 </div>
                              </div>
                           </div>
                           <div class="col">
                              <div class="form-group">
                                 <Label for="note">Note For Customer</Label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="note" id="note" placeholder="ditampilkan saat membuat quotasi">
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="card">
                     <div class="card-body">
                        <div id="items-container">
                           <!-- Baris item pertama -->
                           <div class="row">
                              <div class="col-4">
                                 <div class="form-group">
                                    <label for="idbarang"><span class="text-success"><a href="../barang/newbarang.php"> <i class="fas fa-plus"></i> </a></span> Product </label>
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
                              <div class="col-2">
                                 <div class="form-group">
                                    <label for="price">Price</label>
                                    <div class="input-group">
                                       <input type="text" name="price[]" class="form-control text-right" required onkeydown="moveFocusToNextInput(event, this, 'price[]')">
                                    </div>
                                 </div>
                              </div>
                              <div class="col">
                                 <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <div class="input-group">
                                       <input type="text" name="notes[]" class="form-control" onkeydown="moveFocusToNextInput(event, this, 'notes[]')">
                                    </div>
                                 </div>
                              </div>
                              <div class="col-1"></div>
                           </div>
                        </div>
                        <div class="row">
                           <div class="col-4">
                              <button type="button" class="btn btn-link text-success" onclick="addItem()"><i class="fas fa-plus-circle"></i></button>
                           </div>
                           <div class="col-2">
                              <button type="submit" class="btn btn-block bg-gradient-success" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')">Submit</button>
                           </div>
                           <div class="col ml-1">
                              <button type="submit" class="btn btn-block bg-gradient-primary" name="submit" onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')">Submit & Quotations</button>
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
<script src="../dist/js/movefocus.js"></script>
<script>
   function addItem() {
      var itemsContainer = document.getElementById('items-container');

      // Baris item baru
      var newItemRow = document.createElement('div');
      newItemRow.className = 'item-row';

      // Konten baris item baru
      newItemRow.innerHTML = `
<div class="row mt-n2">
<div class="col-4">
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
                                       <input type="text" name="notes[]" class="form-control" onkeydown="moveFocusToNextInput(event, this, 'notes[]')">
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
      var itemRow = button.closest('.item-row');

      // Hapus baris item
      itemRow.remove();
   }

   // Mengubah judul halaman web
   document.title = "NEW Pricelist";
</script>

<?php
// require "../footnotes.php";
include "../footer.php";
?>