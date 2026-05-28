<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
// include "norepack.php";
?>
<div class="content-wrapper">
   <section class="content">
      <div class="container">
         <div class="row">
            <div class="col-md mt-3">
               <form method="POST" action="prosesrepack.php">
                  <div class="card">
                     <div class="card-body">
                        <!-- <div class="col">
                           <div class="form-group">
                              <label for="norepack">NO Repack</label>
                              <input type="text" class="form-control" name="norepack" id="norepack" value="<?= $norepack ?>" readonly>
                           </div>
                        </div> -->
                        <div class="col">
                           <div class="form-group">
                              <label for="tglrepack">Tanggal Repack</label>
                              <input type="date" class="form-control" name="tglrepack" required>
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="note">Keterangan</label>
                              <input type="text" class="form-control" name="note" required>
                           </div>
                        </div>
                        <div class="col">
                           <button type="submit" name="submit" class="btn bg-gradient-success">Submit</button>
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
   document.title = "New Repack";
</script>
<?php
// require "../footnotes.php";
include "../footer.php";
?>