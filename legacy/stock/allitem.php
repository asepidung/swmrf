<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}
$sql = "SELECT s.*, b.nmbarang, g.nmgrade
        FROM stock s
        JOIN barang b ON s.idbarang = b.idbarang
        JOIN grade g ON s.idgrade = g.idgrade
        ORDER BY s.pod";

$result = $conn->query($sql);

?>

<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col mt-3">
               <a href="index.php" class="btn btn-primary mb-2">Summary</a>
               <div class="card">
                  <div class="card-body">
                     <div class="col">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>#</th>
                                 <th>Barcode</th>
                                 <th>Item</th>
                                 <th>Grade</th>
                                 <th>Qty</th>
                                 <th>Pcs</th>
                                 <th>P.O.D</th>
                                 <th>Days</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              if ($result->num_rows > 0) {
                                 while ($row = $result->fetch_assoc()) {
                                    $origin = $row['origin'];
                                    $podDate = date_create($row['pod']);
                                    $currentDate = date_create();
                                    $podDiff = date_diff($podDate, $currentDate);

                                    // 💡 Format jadi 3 digit angka + ' days'
                                    $days = (int)$podDiff->format('%a');
                                    $podInterval = sprintf('%03d days', $days);
                              ?>
                                    <tr>
                                       <td class="text-center"><?= $no; ?></td>
                                       <td class="text-center"><?= $row['kdbarcode']; ?></td>
                                       <td><?= $row['nmbarang']; ?></td>
                                       <td class="text-center"><?= $row['nmgrade']; ?></td>
                                       <td class="text-right"><?= $row['qty']; ?></td>
                                       <td class="text-center"><?= $row['pcs']; ?></td>
                                       <td class="text-center"><?= date('d-M-Y', strtotime($row['pod'])); ?></td>
                                       <td class="text-center"><?= $podInterval; ?></td>
                                    </tr>
                              <?php
                                    $no++;
                                 }
                              }
                              ?>
                           </tbody>

                        </table>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   // Mengubah judul halaman web
   document.title = "All Item";
</script>

<?php
include "../footer.php";
?>