<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

$iddo = $_GET['iddo'];
$query = "SELECT do.*, customers.nama_customer, customers.alamat1, salesorder.sonumber
FROM do 
INNER JOIN customers ON do.idcustomer = customers.idcustomer
INNER JOIN salesorder ON do.idso = salesorder.idso
WHERE iddo = $iddo";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
?>
<style>
   body {
      font-size: 16px;
   }

   @media (max-width: 768px) {
      body {
         font-size: 14px;
      }

      .container {
         padding-left: 5px;
         padding-right: 5px;
      }
   }
</style>

<div class="container mt-4">
   <div class="col text-center">
      <strong>DELIVERY ORDER </strong>
      <h5 class="mb-n1">PT. SANTI WIJAYA MEAT</h5>
      <p>
         RPHR Jonggol Jl. SMPN 1 Jonggol Kp. Menan Rt 04/01 Ds. Sukamaju Kec. Jonggol Kab. Bogor
      </p>
   </div>
   <hr>
   <div class="row mt-2">
      <div class="col-sm-6">
         <table class="table table-borderless table-sm">
            <tr>
               <td>DO Number</td>
               <td>:</td>
               <th><?= $row['donumber']; ?></th>
            </tr>
            <tr>
               <td>SO Number</td>
               <td>:</td>
               <th><?= $row['sonumber']; ?></th>
            </tr>
            <tr>
               <td>Sales Ref</td>
               <td>:</td>
               <th>Muryani</th>
            </tr>
            <tr>
               <td>Driver</td>
               <td>:</td>
               <th><?= $row['driver']; ?></th>
            </tr>
            <tr>
               <td>No POL</td>
               <td>:</td>
               <th><?= $row['plat']; ?></th>
            </tr>
            <tr>
               <td>Seal Number</td>
               <td>:</td>
               <th><?= $row['sealnumb']; ?></th>
            </tr>
         </table>
      </div>
      <div class="col-sm-6">
         <table class="table table-borderless table-sm">
            <tr>
               <td>Delivery Date</td>
               <td>:</td>
               <th><?= date('d-M-Y', strtotime($row['deliverydate'])); ?></th>
            </tr>
            <tr>
               <td>PO Number</td>
               <td>:</td>
               <th><?= $row['po']; ?></th>
            </tr>
            <tr>
               <td>Customer</td>
               <td>:</td>
               <th><?= $row['nama_customer']; ?></th>
            </tr>
            <tr>
               <td>Ship To</td>
               <td>:</td>
               <th><?= $row['alamat1']; ?></th>
            </tr>
         </table>
      </div>
   </div>
   <div class="table-responsive">
      <table class="table table-sm table-striped table-bordered">
         <thead class="thead-dark">
            <tr class="text-center">
               <th>#</th>
               <th>Product Desc</th>
               <th>Box</th>
               <th>Qty</th>
               <th>Notes</th>
            </tr>
         </thead>
         <tbody>
            <?php
            $no = 1;
            $total_qty = 0;
            $total_box = 0;
            $query_dodetail = "SELECT dodetail.*, barang.nmbarang
                        FROM dodetail
                        INNER JOIN barang ON dodetail.idbarang = barang.idbarang
                        WHERE iddo = '$iddo'";
            $result_dodetail = mysqli_query($conn, $query_dodetail);

            if ($result_dodetail) {
               while ($row_dodetail = mysqli_fetch_assoc($result_dodetail)) {
                  $total_qty += $row_dodetail['weight'];
                  $total_box += $row_dodetail['box'];
            ?>
                  <tr>
                     <td class="text-center"><?= $no; ?></td>
                     <td><?= $row_dodetail['nmbarang']; ?></td>
                     <td class="text-center"><?= $row_dodetail['box']; ?></td>
                     <td class="text-right"><?= number_format($row_dodetail['weight'], 2); ?></td>
                     <td><?= $row_dodetail['notes']; ?></td>
                  </tr>
            <?php $no++;
               }
            } else {
               echo "Error in query: " . mysqli_error($conn);
            }
            ?>
         </tbody>
         <tfoot>
            <tr>
               <th colspan="2" class="text-right">Weight Total</th>
               <th class="text-center"><?= $total_box; ?></th>
               <th class="text-right"><?= number_format($total_qty, 2); ?></th>
               <th></th>
            </tr>
         </tfoot>
      </table>
   </div>

   <p class="mb-n1">
      <?php
      if ($row['note'] !== "") { ?>
         <strong>Catatan :</strong>
      <?php } else {
         echo "-";
      } ?>
   </p>
   <p>
      <i><?= $row['note']; ?></i>
   </p>
   <div class="row mt-3 justify-content-center">
      <div class="col-6 col-sm-4 col-md-3 mb-2">
         <a href="javascript:history.back()">
            <button type="button" class="btn btn-block btn-success"><i class="fas fa-undo"></i></button>
         </a>
      </div>
      <div class="col-6 col-sm-4 col-md-3">
         <a href="cetakdo.php?iddo=<?= $row['iddo']; ?>">
            <button type="button" class="btn btn-block btn-warning"><i class="fas fa-print"></i></button>
         </a>
      </div>
   </div>

</div>
</body>
<script>
   document.title = "<?= $row['nama_customer'] . " " . $row['donumber'] ?>"
</script>
<?php

include "../footer.php"
?>