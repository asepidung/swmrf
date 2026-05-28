<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Query untuk mendapatkan data dari tabel tally yang stat-nya Approved dan join dengan tabel customers
$query = "SELECT t.idtally, c.nama_customer, t.deliverydate, t.po, t.sonumber, t.notally, t.stat
          FROM tally t
          JOIN customers c ON t.idcustomer = c.idcustomer
          WHERE t.stat = 'Approved' AND is_deleted = 0 ";

$result = mysqli_query($conn, $query);
if (!$result) {
   die("Query error: " . mysqli_error($conn));
}
?>
<div class="content-wrapper">
   <!-- Content Header (Page header) -->
   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12">
               <div class="card mt-3">
                  <!-- /.card-header -->
                  <div class="card-body">
                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>Customer</th>
                              <th>Tgl Kirim</th>
                              <th>PO</th>
                              <th>SO</th>
                              <th>Taly</th>
                              <th>Actions</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $counter = 1;
                           while ($row = mysqli_fetch_assoc($result)) {
                              echo '<tr>
                                       <td class="text-center">' . $counter++ . '</td>
                                       <td>' . htmlspecialchars($row['nama_customer']) . '</td>
                                       <td class="text-center">' . htmlspecialchars($row['deliverydate']) . '</td>
                                       <td class="text-center">' . htmlspecialchars($row['po']) . '</td>
                                       <td class="text-center">' . htmlspecialchars($row['sonumber']) . '</td>
                                       <td class="text-center">' . htmlspecialchars($row['notally']) . '</td>
                                       <td class="text-center">
                                          <a class="btn btn-primary btn-xs" data-toggle="tooltip" data-placement="bottom" title="Buat DO" onclick="window.location.href=\'doissue.php?id=' . $row['idtally'] . '\'">
                                          Proses DO <i class="fas fa-truck"></i>
                                          </a>
                                       </td>
                                    </tr>';
                           }
                           ?>
                        </tbody>
                     </table>
                  </div>
                  <!-- /.card-body -->
               </div>
               <!-- /.card -->
            </div>
            <!-- /.col -->
         </div>
         <!-- /.row -->
      </div>
      <!-- /.container-fluid -->
   </section>
   <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<script>
   // Mengubah judul halaman web
   document.title = "DRAFT DO";
</script>
<?php
// require "../footnote.php";
include "../footer.php";
?>