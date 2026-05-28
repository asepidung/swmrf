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
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="prosesrequest.php">
                  <div class="card">
                     <div class="card-body">
                        <div class="row">
                           <div class="col-12 col-sm-4">
                              <div class="form-group">
                                 <label for="duedate">Tgl Kedatangan <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <input type="date" class="form-control" name="duedate" id="duedate" required autofocus>
                                 </div>
                              </div>
                           </div>

                           <div class="col-12 col-sm-4">
                              <div class="form-group">
                                 <label for="idsupplier">Buy To <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <select class="form-control" name="idsupplier" id="idsupplier" required>
                                       <option value="">Pilih Supplier</option>
                                       <?php
                                       $query = "SELECT * FROM supplier ORDER BY nmsupplier ASC";
                                       $result = mysqli_query($conn, $query);
                                       while ($row = mysqli_fetch_assoc($result)) {
                                          $idsupplier = $row['idsupplier'];
                                          $nmsupplier = $row['nmsupplier'];
                                          echo "<option value=\"$idsupplier\">$nmsupplier</option>";
                                       }
                                       ?>
                                    </select>
                                    <div class="input-group-append">
                                       <a href="../supplier/newsupplier.php" class="btn btn-dark">
                                          <i class="fas fa-plus"></i>
                                       </a>
                                    </div>
                                 </div>
                              </div>
                           </div>
                           <div class="col-12 col-sm-4">
                              <div class="form-group">
                                 <label for="note">Note</label>
                                 <div class="input-group">
                                    <input type="text" class="form-control" name="note" id="note" placeholder="Summary Describe">
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
                              <div class="col-12 col-md-3">
                                 <div class="form-group">
                                    <div class="input-group">
                                       <select class="form-control" name="idbarang[]" required>
                                          <option value="">--Product--</option>
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
                              <div class="col-6 col-md-1">
                                 <div class="form-group">
                                    <div class="input-group">
                                       <input type="text" name="weight[]" placeholder="Qty" class="form-control text-right" required>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-6 col-md-2">
                                 <div class="form-group">
                                    <div class="input-group">
                                       <input type="text" name="price[]" placeholder="Price" class="form-control text-right" required>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-6 col-md-2">
                                 <div class="form-group">
                                    <div class="input-group">
                                       <input type="text" name="amount[]" placeholder="amount" class="form-control text-right" readonly>
                                    </div>
                                 </div>
                              </div>
                              <div class="col-6 col-md-3">
                                 <div class="form-group">
                                    <div class="input-group">
                                       <input type="text" placeholder="note" name="notes[]" class="form-control">
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>

                        <!-- Total dan Button -->
                        <div class="row align-items-center">
                           <div class="col-12 col-md-2 text-center mb-3 mb-md-0">
                              <button type="button" class="btn btn-link text-success" onclick="addItem()">
                                 <i class="fas fa-plus-circle"></i> Add Item
                              </button>
                           </div>

                           <div class="col-6 col-md-2 mb-3 mb-md-0">
                              <input type="text" name="xweight" id="xweight" class="form-control text-right" readonly placeholder="Total Qty">
                           </div>
                           <div class="col-6 col-md-2 mb-3 mb-md-0">

                           </div>

                           <div class="col-6 col-md-2 mb-3 mb-md-0">
                              <input type="text" name="xamount" id="xamount" class="form-control text-right" readonly placeholder="Total Amount">
                           </div>

                           <div class="col-6 col-md-3">
                              <button type="submit" class="btn btn-primary btn-block" name="submit"
                                 onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')" id="submit-btn">
                                 Submit
                              </button>
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

<script>
   // Format angka dengan digit grouping
   function formatNumber(num) {
      return num.toLocaleString('en-US', {
         minimumFractionDigits: 2,
         maximumFractionDigits: 2
      });
   }

   // Hapus format digit grouping untuk perhitungan
   function unformatNumber(num) {
      return parseFloat(num.replace(/,/g, '')) || 0;
   }

   function formatInput(input) {
      const start = input.selectionStart;
      const end = input.selectionEnd;
      const value = unformatNumber(input.value);
      input.value = formatNumber(value);
      input.setSelectionRange(start, end);
   }

   function calculateTotals() {
      const weightInputs = document.querySelectorAll('[name="weight[]"]');
      const priceInputs = document.querySelectorAll('[name="price[]"]');
      const amountInputs = document.querySelectorAll('[name="amount[]"]');
      const xweight = document.getElementById('xweight');
      const xamount = document.getElementById('xamount');

      let totalWeight = 0;
      let totalAmount = 0;

      weightInputs.forEach((weightInput, index) => {
         const weight = unformatNumber(weightInput.value);
         const price = unformatNumber(priceInputs[index].value);
         const amount = weight * price;

         amountInputs[index].value = formatNumber(amount);
         totalWeight += weight;
         totalAmount += amount;
      });

      // Update nilai-nilai yang ditampilkan di form
      xweight.value = formatNumber(totalWeight);
      xamount.value = formatNumber(totalAmount);
   }

   // Format input saat mengetik dan perbarui total
   document.addEventListener('input', function(e) {
      if (e.target.name === 'weight[]' || e.target.name === 'price[]') {
         formatInput(e.target);
         calculateTotals();
      }
   });

   function addItem() {
      const itemsContainer = document.getElementById('items-container');
      const newItemRow = document.createElement('div');
      newItemRow.className = 'row';

      newItemRow.innerHTML = `
            <div class="col-12 col-md-3">
                <div class="form-group">
                    <select class="form-control" name="idbarang[]" required>
                        <option value="">--Product--</option>
                        <?php
                        $query = "SELECT * FROM barang ORDER BY nmbarang ASC";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                           echo '<option value="' . $row['idbarang'] . '">' . $row['nmbarang'] . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-1">
                <div class="form-group">
                    <input type="text" name="weight[]" placeholder="Qty" class="form-control text-right" required>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-group">
                    <input type="text" name="price[]" placeholder="Price" class="form-control text-right" required>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-group">
                    <input type="text" name="amount[]" placeholder="amount" class="form-control text-right" readonly>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="form-group">
                    <input type="text" placeholder="note" name="notes[]" class="form-control">
                </div>
            </div>
            <div class="col">
                <button type="button" class="btn btn-link text-danger" onclick="removeItem(this)">
                    <i class="fas fa-minus-circle"></i>
                </button>
            </div>
        `;

      itemsContainer.appendChild(newItemRow);
   }

   function removeItem(button) {
      button.closest('.row').remove();
      calculateTotals();
   }

   document.title = "New Request";
</script>

<?php include "../footer.php"; ?>