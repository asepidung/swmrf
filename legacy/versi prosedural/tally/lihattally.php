<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
$idtally = $_GET['id'];
require "hitungantally.php";
?>

<div class=" container">
   <div class="col text-center">
      <h4 class="mb-n1">TALY SHEET</h4>
      <span><strong> Taly Number : <?= $row_tally['notally']; ?></strong></span>
   </div>
   <hr>
   <div class="row mt-2">
      <div class="col-6">
         <table class="table table-responsive table-borderless table-sm">
            <tr>
               <td>Customer</td>
               <td>:</td>
               <th><?= $row_tally['nama_customer']; ?></th>
            </tr>
            <tr>
               <td>PO Numb</td>
               <td>:</td>
               <th><?= $row_tally['po']; ?></th>
            </tr>
         </table>
      </div>
      <div class="col-6">
         <table class="table table-responsive table-borderless table-sm">
            <tr>
               <td>Delivery Date</td>
               <td>:</td>
               <th><?= date('d-M-Y', strtotime($row_tally['deliverydate'])); ?></th>
            </tr>
            <tr>
               <td>SO Numb</td>
               <td>:</td>
               <th><?= $row_tally['sonumber']; ?></th>
            </tr>
         </table>
      </div>
   </div>
   <table class="table table-sm table-bordered">
      <thead class="thead-dark">
         <tr class="text-center">
            <th>Product</th>
            <th>01</th>
            <th>02</th>
            <th>03</th>
            <th>04</th>
            <th>05</th>
            <th>06</th>
            <th>07</th>
            <th>08</th>
            <th>09</th>
            <th>10</th>
            <th>TOTAL</th>
         </tr>
      </thead>
      <tbody class="text-center">
         <?php
         foreach ($productData as $productName => $data) {
            $weightArray = $data['weights'];
            $count = count($weightArray);
            $rowsNeeded = ceil($count / 10);

            for ($rowIndex = 0; $rowIndex < $rowsNeeded; $rowIndex++) {
               echo '<tr>';
               if ($rowIndex === 0) {
                  echo '<td class="text-left">' . $productName . '</td>';
               } else {
                  echo '<td></td>';
               }

               for ($i = 0; $i < 10; $i++) {
                  $weightIndex = ($rowIndex * 10) + $i;
                  if (isset($weightArray[$weightIndex])) {
                     echo '<td>' . $weightArray[$weightIndex] . '</td>';
                  } else {
                     echo '<td></td>';
                  }
               }

               if ($rowIndex == $rowsNeeded - 1) {
                  echo '<td class="text-right"> <strong>' . number_format($data['total'], 2) . '</strong></td>';
               } else {
                  echo '<td></td>';
               }

               echo '</tr>';
            }
         }
         ?>
      </tbody>
      <tfoot>
         <tr class="text-right">
            <th colspan="9">Box</th>
            <th class="text-center"><?= $totalBox ?></th>
            <th>Kg</th>
            <th><?= number_format($totalQty, 2) ?></th>
         </tr>
      </tfoot>
   </table>
   <div class="row">
      <div class="col"></div>
      <div class="col-1">
         <a href="index.php" class="btn bg-gradient-warning btn-block">Kembali</a>
      </div>
      <div class="col-1">
         <a href="seedetail.php?id=<?= $idtally ?>" class="btn bg-gradient-success btn-block">Detail</a>
      </div>
      <div class="col-1">
         <a href="printtally.php?id=<?= $idtally ?>" class="btn btn-secondary btn-block">Print</a>
      </div>
   </div>
</div>
<script>
   document.title = "Taly <?= $row_tally['nama_customer']; ?>";
</script>
<?php
// include "../footnote.php";
include "../footer.php";
?>