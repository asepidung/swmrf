<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
$idtally = $_GET['id'];
if (isset($_SESSION['barcode'])) {
   $barcodeValue = $_SESSION['barcode'];
} else {
   $barcodeValue = ""; // Nilai default jika tidak ada dalam sesi
}

$origin = substr($barcodeValue, 0, 1);
?>
<!-- Main content -->
<section class="content">
   <div class="container-fluid">
      <div class="row mt-3">
         <div class="col-xs">
            <form method="POST" action="inputtallymanual.php">
               <div class="card">
                  <div class="card-body">
                     <div id="items-container">
                        <!-- <div class="row"> -->
                        <div class="col">
                           <div class="form-group">
                              <input type="text" class="form-control text-center" name="barcode" id="barcode" readonly value="<?= $barcodeValue ?>">
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <div class="input-group">
                                 <select class="form-control" name="idbarang[]" id="idbarang" required>
                                    <option value="">--Pilih Barang--</option>
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
                        <div class="col">
                           <div class="form-group">
                              <div class="input-group">
                                 <select class="form-control" name="idgrade[]" id="idgrade" required>
                                    <option value="">--Pilih Grade--</option>
                                    <?php
                                    $querygrade = "SELECT * FROM grade ORDER BY nmgrade ASC";
                                    $resultgrade = mysqli_query($conn, $querygrade);
                                    while ($row = mysqli_fetch_assoc($resultgrade)) {
                                       $idgrade = $row['idgrade'];
                                       $nmgrade = $row['nmgrade'];
                                       echo '<option value="' . $idgrade . '">' . $nmgrade . '</option>';
                                    }
                                    ?>
                                 </select>
                              </div>
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <input type="text" placeholder="Kg" class="form-control text-center" name="qty" id="qty" required>
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <input type="text" placeholder="Pcs" class="form-control text-center" name="pcs" id="pcs">
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <input type="date" placeholder="pod" class="form-control text-center" name="pod" id="pod" required>
                           </div>
                        </div>
                        <input type="hidden" name="origin" value="<?= $origin ?>">
                        <input type="hidden" name="idtally" value="<?= $idtally ?>">
                        <div class="col">
                           <div class="form-group">
                              <button type="submit" class="btn btn-block btn-primary">Submit</button>
                           </div>
                        </div>
                        <!-- </div> -->
                     </div>
                  </div>
               </div>
            </form>
         </div>
      </div>
   </div>
</section>
<script>
   document.title = "Manual Tally";
</script>
<?php
include "../footer.php" ?>