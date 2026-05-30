<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

?>
<div class="content-wrapper">
   <section class="content">
      <div class="container">
         <div class="row">
            <div class="col-8 mt-3">
               <form method="POST" action="prosesmutasi.php">
                  <div class="card">
                     <div class="card-body">
                        <div class="col">
                           <div class="form-group">
                              <label for="tglst">Mutasi Date <span class="text-danger">*</span></label>
                              <input type="date" class="form-control" name="tglmutasi" id="tglmutasi" required>
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="gudang">Gudang</label>
                              <select class="form-control" name="gudang" id="gudang">
                                 <option value="">Gudang Tujuan</option>
                                 <option value="PERUM">PERUM</option>
                                 <option value="JONGGOL">JONGGOL</option>
                              </select>
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="driver">Driver </label>
                              <input type="text" class="form-control" name="driver" id="driver">
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="nopol">No Polisi </label>
                              <input type="nopol" class="form-control" name="nopol" id="nopol">
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="note">Catatan</label>
                              <input type="text" class="form-control" name="note">
                           </div>
                        </div>
                        <div class="col">
                           <button type="submit" name="submit" class="btn bg-gradient-success">Start Mutasi</button>
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
   document.title = "New Mutasi";
</script>
<?php
// require "../footnotes.php";
include "../footer.php";
?>