<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>
<div class="content-wrapper">
   <!-- Content Header (Page header) -->
   <div class="content-header">
      <div class="container-fluid">
         <div class="row align-items-center mb-2">

            <!-- FILTER PERIODE -->
            <div class="col-sm-9 col-12">
               <form method="GET" class="form-inline flex-wrap">

                  <label class="mr-2 font-weight-bold d-none d-sm-inline">Periode</label>

                  <input type="date"
                     name="awal"
                     value="<?= htmlspecialchars($awal, ENT_QUOTES); ?>"
                     class="form-control form-control-sm mr-sm-2 mb-1">

                  <span class="mr-sm-2 mb-1">s/d</span>

                  <input type="date"
                     name="akhir"
                     value="<?= htmlspecialchars($akhir, ENT_QUOTES); ?>"
                     class="form-control form-control-sm mr-sm-2 mb-1">

                  <button type="submit"
                     class="btn btn-sm btn-primary mb-1"
                     name="search">
                     <i class="fas fa-search"></i> Cari
                  </button>

               </form>
            </div>

            <!-- BACK -->
            <div class="col-sm-3 col-12 text-sm-right mt-2 mt-sm-0">
               <a href="javascript:history.back()"
                  class="btn btn-sm btn-outline-primary btn-block btn-sm-inline">
                  <i class="fas fa-arrow-left"></i> Back
               </a>
            </div>

         </div>
      </div>
   </div>

   <!-- /.content-header -->

   <!-- Main content -->
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12 mt-3">
               <div class="card">
                  <!-- /.card-header -->
                  <div class="card-body">
                     <?php
                     // Query untuk mengambil data SO, DO (Shipped), dan DO Receipt (Received)
                     // Memastikan data yang dihitung hanya yang is_deleted = 0
                     $query = "SELECT 
                                 s.idso, 
                                 c.nama_customer, 
                                 s.deliverydate, 
                                 s.po, 
                                 sd.weight AS qty_order, 
                                 sd.price, 
                                 sd.notes, 
                                 s.sonumber, 
                                 b.nmbarang,
                                 IFNULL(kirim.qty_kirim, 0) AS qty_kirim,
                                 IFNULL(terima.qty_terima, 0) AS qty_terima
                               FROM salesorder s
                               INNER JOIN customers c ON s.idcustomer = c.idcustomer
                               INNER JOIN salesorderdetail sd ON s.idso = sd.idso
                               INNER JOIN barang b ON sd.idbarang = b.idbarang
                               
                               -- Subquery 1: Hitung total barang dikirim (DO) yang aktif (is_deleted = 0)
                               LEFT JOIN (
                                  SELECT d.idso, dd.idbarang, SUM(dd.weight) AS qty_kirim
                                  FROM do d
                                  INNER JOIN dodetail dd ON d.iddo = dd.iddo
                                  WHERE d.is_deleted = 0
                                  GROUP BY d.idso, dd.idbarang
                               ) kirim ON s.idso = kirim.idso AND sd.idbarang = kirim.idbarang

                               -- Subquery 2: Hitung total barang diterima (DO Receipt) yang aktif (is_deleted = 0)
                               LEFT JOIN (
                                  SELECT dr.idso, drd.idbarang, SUM(drd.weight) AS qty_terima
                                  FROM doreceipt dr
                                  INNER JOIN doreceiptdetail drd ON dr.iddoreceipt = drd.iddoreceipt
                                  WHERE dr.is_deleted = 0
                                  GROUP BY dr.idso, drd.idbarang
                               ) terima ON s.idso = terima.idso AND sd.idbarang = terima.idbarang

                               WHERE s.is_deleted = 0 AND s.deliverydate BETWEEN '$awal' AND '$akhir'
                               ORDER BY s.idso DESC, b.nmbarang ASC";

                     $result = $conn->query($query);
                     ?>
                     <!-- Bagian HTML -->
                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>Customer</th>
                              <th>Nomor SO</th>
                              <th>Tgl Kirim</th>
                              <th>Products</th>
                              <th>Price (Rp)</th>
                              <th>Ordered</th>
                              <th>Shipped</th>
                              <th>Received</th>
                              <th>Notes</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           if ($result && $result->num_rows > 0) {
                              $row_number = 1;
                              while ($row = $result->fetch_assoc()) { ?>
                                 <tr>
                                    <td class="text-center"> <?= $row_number; ?> </td>
                                    <td class="text-left"> <?= htmlspecialchars($row["nama_customer"]); ?> </td>
                                    <td class="text-center"> <?= htmlspecialchars($row["sonumber"]); ?> </td>
                                    <td class="text-center"> <?= date("d-M-y", strtotime($row["deliverydate"])); ?> </td>
                                    <td class="text-left"> <?= htmlspecialchars($row["nmbarang"]); ?> </td>
                                    <td class="text-right"><?= number_format($row["price"]) ?></td>

                                    <!-- Qty Order -->
                                    <td class="text-right font-weight-bold"> <?= number_format($row["qty_order"], 2); ?> </td>

                                    <!-- Qty Kirim (DO Shipped) -->
                                    <td class="text-right text-primary"> <?= number_format($row["qty_kirim"], 2); ?> </td>

                                    <!-- Qty Terima (DO Received) -->
                                    <td class="text-right text-success"> <?= number_format($row["qty_terima"], 2); ?> </td>

                                    <td class="text-left"> <?= htmlspecialchars($row["notes"] ?? ''); ?> </td>
                                 </tr>
                           <?php
                                 $row_number++;
                              }
                           } else {
                              echo "<tr><td colspan='10' class='text-center text-muted'>Tidak ada data ditemukan</td></tr>";
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
   document.title = "Detail Sales Order List";
</script>
<?php include "../footer.php" ?>