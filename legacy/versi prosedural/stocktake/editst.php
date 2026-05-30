<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
$idst = $_GET['id'];

?>
<div class="content-wrapper">
   <section class="content">
      <div class="container">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="updatetaking.php">
                  <div class="card">
                     <div class="card-body">
                        <?php
                        $query = "SELECT * FROM stocktake WHERE idst = $idst";
                        $result = mysqli_query($conn, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                           $tglst = $row['tglst'];
                           $note = $row['note'];
                        }
                        ?>
                        <div class="col">
                           <div class="form-group">
                              <label for="tglst">Taking Date <span class="text-danger">*</span></label>
                              <input type="date" class="form-control" name="tglst" id="tglst" value="<?= $tglst ?>">
                           </div>
                        </div>
                        <input type="hidden" name="idst" value="<?= $idst ?>">
                        <div class="col">
                           <div class="form-group">
                              <label for="note">Catatan</label>
                              <input type="text" class="form-control" name="note" value="<?= $note ?>">
                           </div>
                        </div>
                        <div class="col">
                           <button type="submit" name="submit" class="btn bg-gradient-success">Update</button>
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
   document.title = "Edit Stock Taking";
</script>
<?php
// require "../footnotes.php";
include "../footer.php";
?>