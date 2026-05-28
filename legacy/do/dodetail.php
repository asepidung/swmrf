<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$awal  = isset($_GET['awal'])  ? $_GET['awal']  : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>

<div class="content-wrapper">
   <div class="content-header">
      <div class="container-fluid">
         <div class="row">
            <div class="col-2">
               <form method="GET">
                  <input type="date" class="form-control form-control-sm" name="awal" value="<?= $awal ?>">
            </div>
            <div class="col-2">
               <input type="date" class="form-control form-control-sm" name="akhir" value="<?= $akhir ?>">
            </div>
            <div class="col">
               <button type="submit" class="btn btn-sm btn-primary">
                  <i class="fas fa-search"></i>
               </button>
               </form>
            </div>
            <div class="col-2">
               <a href="do.php" class="btn btn-sm btn-outline-success float-right">
                  <i class="fas fa-eye"></i> Kembali
               </a>
            </div>
         </div>
      </div>
   </div>

   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12 mt-3">
               <div class="card">
                  <div class="card-body">

                     <?php
                     $query = "
SELECT
    i.iddo,
    c.nama_customer,
    s.sonumber,
    i.deliverydate,
    i.donumber,
    i.po,
    b.nmbarang,

    /* SO (order) */
    (
        SELECT sod.weight
        FROM salesorderdetail sod
        WHERE sod.idso = s.idso
          AND sod.idbarang = id.idbarang
        LIMIT 1
    ) AS so_weight,

    /* DO (kirim) */
    id.weight AS do_weight,

    /* REAL (diterima) */
    (
        SELECT SUM(drd.weight)
        FROM doreceipt dr
        JOIN doreceiptdetail drd
            ON dr.iddoreceipt = drd.iddoreceipt
        WHERE dr.iddo = i.iddo
          AND drd.idbarang = id.idbarang
          AND dr.is_deleted = 0
    ) AS real_weight,

    id.notes

FROM do i
JOIN customers c ON i.idcustomer = c.idcustomer
LEFT JOIN salesorder s ON i.idso = s.idso
LEFT JOIN dodetail id ON i.iddo = id.iddo
LEFT JOIN barang b ON id.idbarang = b.idbarang

WHERE i.deliverydate BETWEEN ? AND ?
  AND i.is_deleted = 0

ORDER BY i.iddo DESC
";

                     $stmt = $conn->prepare($query);
                     $stmt->bind_param("ss", $awal, $akhir);
                     $stmt->execute();
                     $result = $stmt->get_result();
                     ?>

                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>Customer</th>
                              <th>No SO</th>
                              <th>Tgl Kirim</th>
                              <th>No DO</th>
                              <th>PO</th>
                              <th>Barang</th>
                              <th>SO</th>
                              <th>DO</th>
                              <th>BTB</th>
                              <th>Notes</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           while ($row = $result->fetch_assoc()):
                           ?>
                              <tr>
                                 <td class="text-center"><?= $no++ ?></td>
                                 <td><?= htmlspecialchars($row['nama_customer']) ?></td>
                                 <td class="text-center"><?= htmlspecialchars($row['sonumber']) ?></td>
                                 <td class="text-center"><?= date('d-M-Y', strtotime($row['deliverydate'])) ?></td>
                                 <td class="text-center"><?= htmlspecialchars($row['donumber']) ?></td>
                                 <td><?= htmlspecialchars($row['po']) ?></td>
                                 <td><?= htmlspecialchars($row['nmbarang']) ?></td>

                                 <td class="text-right">
                                    <?= number_format((float)$row['so_weight'], 2) ?>
                                 </td>

                                 <td class="text-right">
                                    <?= number_format((float)$row['do_weight'], 2) ?>
                                 </td>

                                 <td class="text-right">
                                    <?= is_null($row['real_weight'])
                                       ? '-'
                                       : number_format((float)$row['real_weight'], 2)
                                    ?>
                                 </td>

                                 <td><?= htmlspecialchars($row['notes']) ?></td>
                              </tr>
                           <?php endwhile; ?>
                        </tbody>
                     </table>

                  </div>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   document.title = "DO vs REAL Receipt";
</script>

<?php include "../footer.php"; ?>