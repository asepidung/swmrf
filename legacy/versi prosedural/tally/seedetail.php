<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
$idtally = $_GET['id'];

$querytally = "SELECT tally.*, customers.nama_customer
            FROM tally
            INNER JOIN customers ON tally.idcustomer = customers.idcustomer
            WHERE tally.idtally = $idtally";
$resulttally = mysqli_query($conn, $querytally);
$rowtally = mysqli_fetch_assoc($resulttally);
$idso = $rowtally['idso'];

// Periksa apakah session 'limit' telah di-set
$defaultLimit = 14; // Nilai default untuk limit jika session belum di-set
if (!isset($_SESSION['limit'])) {
   $_SESSION['limit'] = $defaultLimit;
}
$limit = $_SESSION['limit'];
?>
<div class="content-header">
   <div class="container-fluid">
      <div class="row">
         <div class="col">
            <a href="index.php"><button type="button" class="btn btn-outline-primary"><i class="fas fa-arrow-alt-circle-left"></i> Back To List</button></a>
            <a href="kiloan.php?id=<?= $idtally ?>"><button type="button" class="btn btn-outline-warning"><i class="fas fa-arrow-alt-circle-right"></i> Kiloan</button></a>
            <span class="text-info float-right">
               <?php if ($rowtally['nama_customer'] == "ASEP OFFAL") { ?>
                  <a href="istimewa.php?idso=<?= $idso ?>&idtally=<?= $idtally ?>">
                     <h4><?= $rowtally['nama_customer']; ?></h4>
                  </a>
               <?php  } else { ?>
                  <h4><?= $rowtally['nama_customer']; ?></h4>
               <?php } ?>
            </span>
         </div>
      </div>
   </div>
</div>
<!-- Main content -->
<section class="content">
   <div class="container-fluid">
      <div class="row">
         <div class="col">
            <div class="row">
               <div class="col-lg-7">
                  <div class="card">
                     <div class="card-body">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>#</th>
                                 <th>Barcode</th>
                                 <th>Item</th>
                                 <th>Code</th>
                                 <th>Weight</th>
                                 <th>Pcs</th>
                                 <th>POD</th>
                                 <th>Origin</th>
                                 <th>TimeScan</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $ambildata = mysqli_query($conn, "SELECT tallydetail.*, barang.nmbarang, grade.nmgrade
                              FROM tallydetail
                              INNER JOIN barang ON tallydetail.idbarang = barang.idbarang
                              INNER JOIN grade ON tallydetail.idgrade = grade.idgrade
                              WHERE idtally = $idtally ORDER BY idtallydetail DESC");
                              while ($tampil = mysqli_fetch_array($ambildata)) {
                                 $origin = $tampil['origin'];
                                 $nmbarang = $tampil['nmbarang'];
                                 $nmgrade = $tampil['nmgrade'];
                                 $barcode = $tampil['barcode'];
                                 $pod = $tampil['pod'];
                                 $creatime = $tampil['creatime'];
                                 $podDate = new DateTime($pod);
                                 $today = new DateTime();
                                 $interval = $today->diff($podDate);
                                 $daysDiff = $interval->days;
                              ?>
                                 <tr class="text-center">
                                    <td><?= $no; ?></td>
                                    <td><?= $barcode; ?></td>
                                    <td class="text-left"><?= $nmbarang; ?></td>
                                    <td><?= $nmgrade; ?></td>
                                    <td><?= number_format($tampil['weight'], 2); ?></td>
                                    <?php
                                    if ($tampil['pcs'] < 1) {
                                       $pcs = "";
                                    } else {
                                       $pcs = $tampil['pcs'];
                                    }
                                    ?>
                                    <td><?= $pcs; ?></td>
                                    <?php
                                    if ($daysDiff >= $_SESSION['limit']) { ?>
                                       <td>
                                          <?= date('d-M-Y', strtotime($pod)); ?>
                                       </td>
                                    <?php } else { ?>
                                       <td>
                                          <?= date('d-M-Y', strtotime($pod)); ?>
                                       </td>
                                    <?php  } ?>
                                    <td>
                                       <?php
                                       if ($origin == 1) {
                                          echo "BONING";
                                       } elseif ($origin == 2) {
                                          echo "TRADING";
                                       } elseif ($origin == 3) {
                                          echo "REPACK";
                                       } elseif ($origin == 4) {
                                          echo "RELABEL";
                                       } elseif ($origin == 5) {
                                          echo "IMPORT";
                                       } elseif ($origin == 6) {
                                          echo "RTN";
                                       } else {
                                          echo "Unindentified";
                                       }
                                       ?>
                                    </td>
                                    <td>
                                       <?= date("H:i:s", strtotime($creatime)); ?>
                                    </td>
                                 </tr>
                              <?php
                                 $no++;
                              }
                              ?>
                           </tbody>
                        </table>
                     </div>
                  </div>
               </div>
               <div class="col-lg-5">
                  <div class="card">
                     <div class="card-body">
                        <table class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>Prod</th>
                                 <th>PO</th>
                                 <th>Qty</th>
                                 <th>Box</th>
                                 <th>Balance</th>
                                 <th>Notes</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $idso_query = "SELECT idso FROM tally WHERE idtally = $idtally";
                              $idso_result = mysqli_query($conn, $idso_query);

                              if ($idso_result && $idso_row = mysqli_fetch_assoc($idso_result)) {
                                 $idso = $idso_row['idso'];
                                 $query = "SELECT sodetail.idbarang, barang.nmbarang, sodetail.weight, sodetail.notes
                                 FROM salesorderdetail AS sodetail
                                 INNER JOIN barang ON sodetail.idbarang = barang.idbarang
                                 WHERE sodetail.idso = $idso";
                                 $result = mysqli_query($conn, $query);
                                 while ($row = mysqli_fetch_assoc($result)) { ?>
                                    <tr>
                                       <td class="ml-1"> <?= $row['nmbarang'] ?></td>
                                       <td class="text-center"><?= number_format($row['weight'], 2) ?></td>
                                       <td class="text-right">
                                          <?php
                                          $totalWeightQuery = "SELECT SUM(weight) AS total_weight
                                          FROM tallydetail
                                          WHERE idtally = $idtally AND idbarang = " . $row['idbarang'];
                                          $totalWeightResult = mysqli_query($conn, $totalWeightQuery);
                                          if ($totalWeightResult && $totalWeightRow = mysqli_fetch_assoc($totalWeightResult)) {
                                             echo number_format($totalWeightRow['total_weight'], 2);
                                          } else {
                                             echo "0"; // Jika tidak ada data, tampilkan 0
                                          }
                                          ?>
                                       </td>
                                       <td class="text-center">
                                          <?php
                                          $totalCountQuery = "SELECT COUNT(weight) AS total_weight
                                          FROM tallydetail
                                          WHERE idtally = $idtally AND idbarang = " . $row['idbarang'];
                                          $totalCountResult = mysqli_query($conn, $totalCountQuery);
                                          if ($totalCountResult && $totalCountRow = mysqli_fetch_assoc($totalCountResult)) {
                                             echo $totalCountRow['total_weight'];
                                          } else {
                                             echo "0"; // Jika tidak ada data, tampilkan 0
                                          }
                                          ?>
                                       </td>
                                       <?php
                                       $po = $row['weight'];
                                       $scan = $totalWeightRow['total_weight'];
                                       $sisa = $totalWeightRow['total_weight'] - $row['weight'];
                                       ?>
                                       <td class="text-right">
                                          <?php if ($sisa > 0) { ?>
                                             <span class="text-danger"><?= number_format($sisa, 2); ?></span>
                                          <?php } else { ?>
                                             <?= number_format($sisa, 2); ?>
                                          <?php } ?>
                                       </td>
                                       <td>
                                          <?= $row['notes']; ?>
                                       </td>
                                    </tr>
                              <?php }
                              }
                              ?>
                           </tbody>
                           <?php
                           $totalPOQuery = "SELECT SUM(weight) AS total_weight FROM salesorderdetail WHERE idso = $idso";
                           $totalPOResult = mysqli_query($conn, $totalPOQuery);
                           if ($totalPOResult && $totalPORow = mysqli_fetch_assoc($totalPOResult)) {
                              $totalPO = $totalPORow['total_weight'];
                           } else {
                              $totalPO = 0; // Atur ke 0 jika tidak ada hasil
                           }

                           $totalQtyQuery = "SELECT SUM(weight) AS total_qty FROM tallydetail WHERE idtally = $idtally";
                           $totalQtyResult = mysqli_query($conn, $totalQtyQuery);
                           if ($totalQtyResult && $totalQtyRow = mysqli_fetch_assoc($totalQtyResult)) {
                              $totalQty = $totalQtyRow['total_qty'];
                           } else {
                              $totalQty = 0; // Atur ke 0 jika tidak ada hasil
                           }

                           $totalBoxQuery = "SELECT COUNT(weight) AS total_box FROM tallydetail WHERE idtally = $idtally";
                           $totalBoxResult = mysqli_query($conn, $totalBoxQuery);
                           if ($totalBoxResult && $totalBoxRow = mysqli_fetch_assoc($totalBoxResult)) {
                              $totalBox = $totalBoxRow['total_box'];
                           } else {
                              $totalBox = 0; // Atur ke 0 jika tidak ada hasil
                           }
                           $totalBalance = $totalQty - $totalPO;
                           ?>
                           <tfoot>
                              <tr class="text-right">
                                 <th>Total</th>
                                 <th class="text-center"><?= number_format($totalPO); ?></th>
                                 <th><?= number_format($totalQty, 2); ?></th>
                                 <th class="text-center"><?= number_format($totalBox); ?></th>
                                 <th><?= number_format($totalBalance, 2); ?></th>
                              </tr>
                           </tfoot>
                        </table>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
<script>
   document.title = "<?= 'Taly' . ' ' . $rowtally['nama_customer']; ?> ";
</script>
<?php
include "../footer.php" ?>