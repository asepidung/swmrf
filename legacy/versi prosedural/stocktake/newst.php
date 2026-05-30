<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
// include "nost.php";
?>
<div class="content-wrapper">
   <section class="content">
      <div class="container">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="prosestaking.php">
                  <div class="card">
                     <div class="card-body">
                        <div class="col">
                           <div class="form-group">
                              <label for="tglst">Taking Date <span class="text-danger">*</span></label>
                              <input type="date" class="form-control" name="tglst" id="tglst">
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="note">Catatan</label>
                              <input type="text" class="form-control" name="note">
                           </div>
                        </div>
                        <div class="col">
                           <button type="submit" name="submit" class="btn bg-gradient-success">Start Taking</button>
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
   document.title = "Stock Taking";
</script>
<?php
// require "../footnotes.php";
include "../footer.php";
?>