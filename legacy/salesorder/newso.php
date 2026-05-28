<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>
<div class="content-wrapper">

   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col mt-3">

               <form method="POST" action="inputso.php">

                  <!-- ================= HEADER ================= -->
                  <div class="card">
                     <div class="card-body">

                        <div class="row">
                           <div class="col-md-4">
                              <div class="form-group">
                                 <label>Customer <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <select class="form-control" name="idcustomer" id="idcustomer" required autofocus>
                                       <option value="">Pilih Customer</option>
                                       <?php
                                       $q = mysqli_query($conn, "SELECT * FROM customers ORDER BY nama_customer ASC");
                                       while ($r = mysqli_fetch_assoc($q)) {
                                          echo '<option value="' . $r['idcustomer'] . '">' . $r['nama_customer'] . '</option>';
                                       }
                                       ?>
                                    </select>
                                    <div class="input-group-append">
                                       <a href="../customer/newcustomer.php" class="btn btn-dark">
                                          <i class="fas fa-plus"></i>
                                       </a>
                                    </div>
                                 </div>
                              </div>
                           </div>

                           <div class="col-md-2">
                              <div class="form-group">
                                 <label>Tgl Kirim <span class="text-danger">*</span></label>
                                 <input type="date" class="form-control" name="deliverydate" id="deliverydate" required>
                              </div>
                           </div>

                           <div class="col-md-2">
                              <div class="form-group">
                                 <label>Cust PO</label>
                                 <input type="text" class="form-control" name="po" id="po">
                              </div>
                           </div>

                           <div class="col-md-4">
                              <div class="form-group">
                                 <label>Alamat <span class="text-danger">*</span></label>
                                 <select class="form-control" name="alamat" id="alamat" required>
                                    <option value="">Pilih Alamat</option>
                                 </select>
                              </div>
                           </div>
                        </div>

                        <div class="row">
                           <div class="col">
                              <input type="text" class="form-control" name="note" id="note" placeholder="Catatan Untuk Penyiapan">
                           </div>
                        </div>

                     </div>
                  </div>

                  <!-- ================= ITEMS ================= -->
                  <div class="card">
                     <div class="card-body">

                        <div id="items-container">

                           <!-- ITEM ROW -->
                           <div class="row item-row align-items-end mb-2">
                              <div class="col-md-3 col-12">
                                 <label class="small mb-1">Product</label>
                                 <select class="form-control product-select" name="idbarang[]" required>
                                    <option value="">--Pilih--</option>
                                    <?php
                                    $q = mysqli_query($conn, "SELECT * FROM barang ORDER BY nmbarang ASC");
                                    while ($r = mysqli_fetch_assoc($q)) {
                                       echo '<option value="' . $r['idbarang'] . '">' . $r['nmbarang'] . '</option>';
                                    }
                                    ?>
                                 </select>
                              </div>

                              <div class="col-md-2 col-6">
                                 <label class="small mb-1">Weight</label>
                                 <input type="text" name="weight[]" class="form-control text-right" value="0">
                              </div>

                              <div class="col-md-2 col-6">
                                 <label class="small mb-1">Price</label>
                                 <input type="text" name="price[]" class="form-control text-right" value="0">
                              </div>

                              <div class="col-md-2 col-6">
                                 <label class="small mb-1">Disc</label>
                                 <input type="number" name="discount[]" class="form-control text-right" value="0">
                              </div>

                              <div class="col-md-2 col-6">
                                 <label class="small mb-1">Notes</label>
                                 <input type="text" name="notes[]" class="form-control">
                              </div>

                              <div class="col-md-1 col-12 text-center"></div>
                           </div>

                        </div>

                        <hr class="my-3">

                        <div class="row align-items-center">
                           <div class="col-md-1 col-12 mb-2">
                              <button type="button" class="btn btn-link text-success" onclick="addItem()">
                                 <i class="fas fa-plus-circle fa-lg"></i>
                              </button>
                           </div>

                           <div class="col"></div>

                           <div class="col-md-2 col-12 mb-2">
                              <button type="button" class="btn btn-block bg-gradient-warning" disabled>
                                 <i class="fas fa-tags"></i> Add Price
                              </button>
                           </div>

                           <div class="col-md-2 col-12 mb-2">
                              <button type="submit" class="btn btn-block bg-gradient-primary"
                                 onclick="return confirm('Pastikan Data Yang Diisi Sudah Benar')" name="submit">
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

<!-- ================= SCRIPTS ================= -->
<script src="../dist/js/movefocus.js"></script>
<script src="../dist/js/fill_alamat_note.js"></script>

<script>
   // ===============================
   // DISABLE PRODUCT DUPLIKAT
   // ===============================
   function refreshProductOptions() {
      const selects = document.querySelectorAll('.product-select');
      const selected = [];

      selects.forEach(s => {
         if (s.value) selected.push(s.value);
      });

      selects.forEach(s => {
         const cur = s.value;
         s.querySelectorAll('option').forEach(o => {
            if (!o.value) return;
            o.disabled = selected.includes(o.value) && o.value !== cur;
         });
      });
   }

   // ===============================
   // FORMAT PRICE (RIBUAN)
   // ===============================
   function addDigitGrouping(number) {
      return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
   }

   function applyPriceFormatter(context = document) {
      context.querySelectorAll('input[name="price[]"]').forEach(input => {
         if (input.dataset.formatted) return; // cegah double listener

         input.addEventListener('focus', function() {
            if (this.value === '0') this.value = '';
         });

         input.addEventListener('input', function() {
            let val = this.value.replace(/,/g, '');
            if (val === '') return;
            let num = parseFloat(val);
            if (!isNaN(num)) {
               this.value = addDigitGrouping(num);
            }
         });

         input.dataset.formatted = '1';
      });
   }

   // ===============================
   // ADD ITEM ROW
   // ===============================
   function addItem() {
      const c = document.getElementById('items-container');
      const r = document.createElement('div');
      r.className = 'row item-row align-items-end mb-2';

      r.innerHTML = `
<div class="col-md-3 col-12">
<select class="form-control product-select" name="idbarang[]" required>
<option value="">--Pilih--</option>
<?php
$q = mysqli_query($conn, "SELECT * FROM barang ORDER BY nmbarang ASC");
while ($x = mysqli_fetch_assoc($q)) {
   echo '<option value="' . $x['idbarang'] . '">' . $x['nmbarang'] . '</option>';
}
?>
</select>
</div>
<div class="col-md-2 col-6">
<input type="text" name="weight[]" class="form-control text-right" value="0">
</div>
<div class="col-md-2 col-6">
<input type="text" name="price[]" class="form-control text-right" value="0">
</div>
<div class="col-md-2 col-6">
<input type="number" name="discount[]" class="form-control text-right" value="0">
</div>
<div class="col-md-2 col-6">
<input type="text" name="notes[]" class="form-control">
</div>
<div class="col-md-1 col-12 text-center">
<button type="button" class="btn btn-link text-danger"
onclick="this.closest('.item-row').remove();refreshProductOptions();">
<i class="fas fa-minus-circle"></i>
</button>
</div>
`;

      c.appendChild(r);

      // aktifkan ulang logic
      refreshProductOptions();
      applyPriceFormatter(r);
   }

   // ===============================
   // EVENTS
   // ===============================
   document.addEventListener('change', e => {
      if (e.target.classList.contains('product-select')) {
         refreshProductOptions();
      }
   });

   document.addEventListener('DOMContentLoaded', function() {
      refreshProductOptions();
      applyPriceFormatter(); // price baris pertama
   });
</script>


<?php include "../footer.php"; ?>