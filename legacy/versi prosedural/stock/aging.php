<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

if ($conn->connect_error) {
   die("Connection failed: " . $conn->connect_error);
}

?>

<div class="content-wrapper">
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-8 mt-3">
               <a href="index.php" class="btn btn-sm btn-primary mb-2">Summary</a>
               <a href="allitem.php" class="btn btn-sm btn-warning mb-2">All Item</a>
               <div class="card">
                  <div class="card-body">
                     <div class="col">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>#</th>
                                 <th>Pack On Date</th>
                                 <th>Days</th>
                                 <th>Qty</th>
                                 <th>Pilih</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              // Query untuk mengambil data stock GROUP BY pod dan menghitung jumlah hari dari pod hingga hari ini
                              $query = "SELECT pod, SUM(qty) AS total_qty, DATEDIFF(CURRENT_DATE, pod) AS days_since_pod FROM stock GROUP BY pod ORDER BY pod";
                              $result = $conn->query($query);

                              if ($result->num_rows > 0) {
                                 $count = 1;
                                 while ($row = $result->fetch_assoc()) {
                                    echo "<tr class='text-center'>";
                                    echo "<td>$count</td>";
                                    echo "<td>" . date('d-M-Y', strtotime($row['pod'])) . "</td>";
                                    echo "<td>" . $row['days_since_pod'] . " " . "Days" . "</td>";
                                    echo "<td class='text-right'>" . number_format($row['total_qty'], 2) . "</td>";
                                    echo "<td>
                                    <a href='lihat.php?pod=" . urlencode($row['pod']) . "' class='btn btn-xs btn-success'><i class='fas fa-eye'></i></a>
                                    <a href='lihatsatuan.php?pod=" . urlencode($row['pod']) . "' class='btn btn-xs btn-primary'><i class='fas fa-meh-rolling-eyes'></i></a>
                                    </td>";
                                    echo "</tr>";
                                    $count++;
                                 }
                              } else {
                                 echo "<tr><td colspan='3'>No data available</td></tr>";
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
   document.title = "Stock By Age";
</script>

<?php
include "../footer.php";
?>