<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

// Validasi ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
   echo "<script>alert('ID tidak ditemukan'); window.location='index.php';</script>";
   exit;
}
$idst = intval($_GET['id']);
?>

<div class="content-header">
   <div class="container-fluid">
      <div class="row">
         <div class="col">
            <a href="index.php" class="btn btn-outline-primary">
               <i class="fas fa-arrow-alt-circle-left"></i> Summary
            </a>
            <a href="lihatst.php?id=<?= $idst ?>" class="btn btn-outline-success">
               <i class="fas fa-arrow-alt-circle-right"></i> Lihat
            </a>
         </div>
      </div>
   </div>
</div>

<section class="content">
   <div class="container-fluid">
      <div class="row">
         <div class="col">
            <form method="POST" action="inputstdetail.php">
               <div class="card">
                  <div class="card-body">
                     <div class="row mb-2">
                        <div class="col-xs-2">
                           <input type="text" placeholder="Scan Here" class="form-control text-center" name="kdbarcode" id="kdbarcode" autofocus required>
                        </div>
                        <input type="hidden" name="idst" value="<?= $idst ?>">
                        <div class="col-1">
                           <button type="submit" class="btn btn-primary btn-block">Submit</button>
                        </div>
                        <div class="col">
                           <?php if (isset($_GET['stat'])): ?>
                              <?php if ($_GET['stat'] == "success"): ?>
                                 <h3 class="text-success"><i class="fas fa-check-circle"></i> Success</h3>
                              <?php elseif ($_GET['stat'] == "ready"): ?>
                                 <h3 class="text-secondary"> Ready To Scan</h3>
                              <?php elseif ($_GET['stat'] == "deleted"): ?>
                                 <h3 class="text-success"> Data berhasil dihapus</h3>
                              <?php elseif ($_GET['stat'] == "duplicate"): ?>
                                 <h3 class="text-warning"><i class="fas fa-exclamation-triangle"></i> Barang Sudah Terinput</h3>
                              <?php elseif ($_GET['stat'] == "unlisted"): ?>
                                 <h3 class="text-danger"><i class="fas fa-times-circle"></i> Barang Tidak ada di PO</h3>
                              <?php elseif ($_GET['stat'] == "unknown"): ?>
                                 <a href="inputmanual.php?id=<?= $idst ?>">
                                    <span class="text-danger">BARANG TIDAK TERDAFTAR <br>
                                       Manual ADD <i class="fas fa-arrow-circle-right"></i>
                                    </span>
                                 </a>
                              <?php endif; ?>
                           <?php endif; ?>
                        </div>
                     </div>
                  </div>
               </div>
            </form>

            <!-- Tabel Data -->
            <div class="row">
               <div class="col-lg-9">
                  <div class="card">
                     <div class="card-body">
                        <table id="stockTable" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr class="text-center">
                                 <th>#</th>
                                 <th>Barcode</th>
                                 <th>Item</th>
                                 <th>Grade</th>
                                 <th>Weight</th>
                                 <th>Pcs</th>
                                 <th>pH</th>
                                 <th>POD</th>
                                 <th>Origin</th>
                                 <th>Hapus</th>
                              </tr>
                           </thead>
                        </table>
                     </div>
                  </div>
               </div>

               <div class="col-lg-3">
                  <div class="card">
                     <div class="card-body">
                        <table id="example2" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>No</th>
                                 <th>Prod</th>
                                 <th>Qty</th>
                                 <th>Box</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $stmt = $conn->prepare("
                                            SELECT b.nmbarang, SUM(s.qty) AS total_qty, COUNT(s.idbarang) AS total_box
                                            FROM stocktakedetail s
                                            INNER JOIN barang b ON s.idbarang = b.idbarang
                                            WHERE s.idst = ?
                                            GROUP BY s.idbarang
                                        ");
                              $stmt->bind_param("i", $idst);
                              $stmt->execute();
                              $result = $stmt->get_result();

                              while ($row = $result->fetch_assoc()) {
                              ?>
                                 <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['nmbarang']); ?></td>
                                    <td class="text-right"><?= number_format($row['total_qty'], 2); ?></td>
                                    <td class="text-center"><?= $row['total_box']; ?></td>
                                 </tr>
                              <?php
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
   </div>
</section>

<?php include "../footer.php"; ?>

<!-- DataTables AJAX -->
<script>
   $(document).ready(function() {
      $('#stockTable').DataTable({
         "processing": true,
         "serverSide": true,
         "ajax": {
            "url": "fetch_data.php?id=<?= $idst ?>",
            "type": "POST"
         },
         "columns": [{
               "data": null,
               "render": function(data, type, row, meta) {
                  return meta.row + meta.settings._iDisplayStart + 1;
               },
               "className": "text-center",
               "orderable": false
            },
            {
               "data": 1,
               "className": "text-center" // Tambahkan class text-center untuk barcode
            },
            {
               "data": 2
            },
            {
               "data": 3,
               "className": "text-center"
            },
            {
               "data": 4,
               "className": "text-right"
            },
            {
               "data": 5,
               "className": "text-center"
            },
            {
               "data": 6,
               "className": "text-center"
            },
            {
               "data": 7,
               "className": "text-center"
            },
            {
               "data": 8,
               "className": "text-center",
               "orderable": false
            },
            {
               "data": 9,
               "className": "text-center",
               "orderable": false
            }
         ],
         "order": [
            [0, "desc"]
         ]
      });

      // Event listener untuk refresh DataTable setelah scan
      $('form').on('submit', function() {
         setTimeout(function() {
            $('#stockTable').DataTable().ajax.reload(null, false);
         }, 500);
      });
   });

   document.title = "Scanning Proses";
</script>