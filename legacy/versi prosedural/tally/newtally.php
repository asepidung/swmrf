<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";


$idso = $_GET['idso'];

// Mengambil data dari tabel so
$queryso = "SELECT salesorder.*, customers.nama_customer
            FROM salesorder
            INNER JOIN customers ON salesorder.idcustomer = customers.idcustomer
            WHERE salesorder.idso = $idso";
$resultso = mysqli_query($conn, $queryso);
$rowso = mysqli_fetch_assoc($resultso);
$sonumber = $rowso['sonumber'];
?>
<div class="content-wrapper">
   <section class="content">
      <div class="container">
         <div class="row">
            <div class="col mt-3">
               <form method="POST" action="prosestally.php">
                  <input type="hidden" value="<?= $idso ?>" name="idso" id="idso">
                  <div class="card">
                     <div class="card-body">
                        <div class="col">
                           <div class="form-group">
                              <label for="deliverydate">Delivery Date <span class="text-danger">*</span></label>
                              <input type="date" class="form-control" name="deliverydate" id="deliverydate" value="<?= $rowso['deliverydate'] ?>">
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="idcustomer">Nama Customer</label>
                              <input type="hidden" name="idcustomer" id="idcustomer" value="<?= $rowso['idcustomer'] ?>">
                              <input type="text" class="form-control" value="<?= $rowso['nama_customer'] ?>" readonly>
                           </div>
                        </div>
                        <div class="col">
                           <div class="form-group">
                              <label for="pocustomer">PO Number</label>
                              <input type="text" class="form-control" name="po" id="po" value="<?= $rowso['po'] ?>" readonly>
                           </div>
                        </div>
                        <!-- <div class="col">
                           <div class="form-group">
                              <label for="notally">Taly Number</label>
                              <input type="text" class="form-control" name="notally" id="notally" value="<?= $kodeauto ?>" readonly>
                           </div>
                        </div> -->
                        <div class="col">
                           <div class="form-group">
                              <label for="sonumber">SO Number</label>
                              <input type="text" class="form-control" name="sonumber" id="sonumber" value="<?= $sonumber ?>" readonly>
                           </div>
                        </div>
                        <div class="col">
                           <button type="submit" name="submit" class="btn bg-gradient-success">Start Taly</button>
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
   document.title = "<?= 'Taly' . " " . $rowso['nama_customer'] ?>";
</script>
<?php
// require "../footnotes.php";
include "../footer.php";
?>