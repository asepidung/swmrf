<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

if (!isset($_GET['id'])) {
  die("Jalankan Dari Modul Produksi");
}

$idboning = (int)$_GET['id'];
$idboningWithPrefix = str_pad($idboning, 4, "0", STR_PAD_LEFT);

/* ===============================
   Ambil data label boning
=============================== */
$query = "
  SELECT b.idbarang, b.nmbarang, l.qty, l.pcs
  FROM labelboning l
  JOIN barang b ON l.idbarang = b.idbarang
  WHERE l.idboning = ? AND l.is_deleted = 0
  ORDER BY b.nmbarang
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idboning);
$stmt->execute();
$result = $stmt->get_result();

/* ===============================
   Grouping hasil produksi
=============================== */
$items = [];
while ($row = $result->fetch_assoc()) {
  $name = $row['nmbarang'] ?? 'Unknown';

  if (!isset($items[$name])) {
    $items[$name] = [
      'qty' => (float)$row['qty'],
      'pcs' => (int)$row['pcs'],
      'box' => 1
    ];
  } else {
    $items[$name]['qty'] += (float)$row['qty'];
    $items[$name]['pcs'] += (int)$row['pcs'];
    $items[$name]['box'] += 1;
  }
}
?>

<div class="content-wrapper">

  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2 align-items-center">
        <div class="col-sm-6">
          <h4>Detail Boning #<?= htmlspecialchars($idboningWithPrefix); ?></h4>
        </div>
        <div class="col-sm-6 text-right">
          <a href="databoning.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-undo-alt"></i> Kembali
          </a>
        </div>
      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-dark shadow-sm">
        <div class="card-header">
          <h3 class="card-title">Hasil Produksi</h3>
        </div>

        <div class="card-body">
          <div class="table-responsive">
            <table id="example1" class="table table-bordered table-striped table-sm">
              <thead class="text-center">
                <tr>
                  <th style="width:50px">#</th>
                  <th>Product</th>
                  <th style="width:90px">Box</th>
                  <th style="width:90px">Pcs</th>
                  <th style="width:120px">Qty (Kg)</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $no = 1;
                $grandBox = 0;
                $grandPcs = 0;
                $grandQty = 0.0;

                foreach ($items as $nmbarang => $item):
                  $grandBox += $item['box'];
                  $grandPcs += $item['pcs'];
                  $grandQty += $item['qty'];
                ?>
                  <tr class="text-center">
                    <td><?= $no++; ?></td>
                    <td class="text-left"><?= htmlspecialchars($nmbarang); ?></td>
                    <td><?= (int)$item['box']; ?></td>
                    <td><?= (int)$item['pcs']; ?></td>
                    <td class="text-right"><?= number_format($item['qty'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="2" class="text-right">GRAND TOTAL</th>
                  <th class="text-center"><?= $grandBox; ?></th>
                  <th class="text-center"><?= $grandPcs; ?></th>
                  <th class="text-right"><?= number_format($grandQty, 2); ?></th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>
    </div>
  </section>
</div>

<script>
  $(function() {
    $("#example1").DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      ordering: false,
      paging: false,
      searching: true,
      info: false,
      buttons: ["copy", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
  });

  document.title = "Detail Boning - <?= addslashes($idboningWithPrefix); ?>";
</script>

<?php include "../footnote.php"; ?>
<?php include "../footer.php"; ?>