<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

?>

<section class="content">
   <div class="container">
      <div class="row mt-3">
         <table id="example1" class="table table-sm table-bordered table-striped">
            <thead>
               <tr>
                  <th>Prod</th>
                  <th>Chill</th>
                  <th>Frozen</th>
                  <th>Price</th>
                  <th></th>
               </tr>
            </thead>
            <tbody>
               <?php
               $ambildata = mysqli_query($conn, "SELECT * FROM barang ORDER BY nmbarang");
               while ($tampil = mysqli_fetch_array($ambildata)) {
               ?>
                  <tr class="text-right">
                     <td class="text-left"><?= $tampil['nmbarang']; ?></td>
                     <td>Later</td>
                     <td>Later</td>
                     <td class="text-center">Later</td>
                     <td>
                        <div class="form-check">
                           <input class="form-check-input" type="checkbox" value="" id="terpilih" name="terpilih">
                        </div>
                     </td>
                  </tr>
               <?php } ?>
            </tbody>

         </table>
      </div>
   </div>
</section>

<script>
   document.title = "Sales Order";
</script>
<?php
include "../footer.php";
?>