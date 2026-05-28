<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

/* =========================
   Validasi input
========================= */
$idboning = (int)($_GET['id'] ?? 0);
if ($idboning <= 0) {
  die("Jalankan dari modul Boning yang valid.");
}

/* =========================
   Helper back URL
========================= */
function back_target(string $default): string
{
  $ret = $_GET['ret'] ?? '';
  if (
    $ret !== '' &&
    strpos($ret, "\n") === false &&
    strpos($ret, "\r") === false &&
    !preg_match('~^(?:https?:)?//~i', $ret)
  ) {
    return $ret;
  }
  $ref = $_SERVER['HTTP_REFERER'] ?? '';
  if ($ref) {
    $hostRef = parse_url($ref, PHP_URL_HOST);
    $hostNow = $_SERVER['HTTP_HOST'] ?? '';
    if ($hostRef === $hostNow) return $ref;
  }
  return $default;
}
$backUrl = back_target('databoning.php');

$noBatch = 'BN' . str_pad($idboning, 4, '0', STR_PAD_LEFT);

/* =========================
   1. Ambil hasil produksi
========================= */
$q = $conn->prepare("
  SELECT b.idbarang, b.nmbarang, l.qty, l.pcs
  FROM labelboning l
  JOIN barang b ON b.idbarang = l.idbarang
  WHERE l.idboning = ? AND l.is_deleted = 0
");
$q->bind_param("i", $idboning);
$q->execute();
$res = $q->get_result();

/* =========================
   Hitung box & pcs per produk
========================= */
$produk = [];
while ($r = $res->fetch_assoc()) {
  $idb = (int)$r['idbarang'];
  if (!isset($produk[$idb])) {
    $produk[$idb] = [
      'box' => 1,
      'pcs' => (int)$r['pcs'],
    ];
  } else {
    $produk[$idb]['box']++;
    $produk[$idb]['pcs'] += (int)$r['pcs'];
  }
}
$q->close();

/* =========================
   2. Hitung pemakaian bahan
========================= */
$usage = []; // idrawmate => qty

foreach ($produk as $idbarang => $p) {

  $box = $p['box'];
  $pcs = $p['pcs'];

  $qbom = $conn->query("
    SELECT r.idrawmate, r.idrawcategory, r.nmrawmate
    FROM bom_rawmate b
    JOIN rawmate r ON r.idrawmate = b.idrawmate
    WHERE b.idbarang = $idbarang AND b.is_active = 1
  ");

  while ($rb = $qbom->fetch_assoc()) {
    $idraw = (int)$rb['idrawmate'];
    $cat   = (int)$rb['idrawcategory'];
    $nm    = strtoupper($rb['nmrawmate']);

    $qty = 0;

    // Karton
    if ($cat === 2) {
      $qty = $box;
    }
    // Plastik
    elseif ($cat === 3) {
      if (strpos($nm, 'LINIER') !== false) {
        $qty = $box;
      } else {
        $qty = $pcs; // vacuum
      }
    }
    // Karung
    elseif ($cat === 21) {
      $qty = $box;
    }
    // Tray
    elseif ($cat === 22 || strpos($nm, 'TRAY') !== false) {
      $qty = $pcs;
    }

    if ($qty > 0) {
      if (!isset($usage[$idraw])) {
        $usage[$idraw] = $qty;
      } else {
        $usage[$idraw] += $qty;
      }
    }
  }
}

/* =========================
   3. Ambil nama material
========================= */
$rows = [];
$grand = 0;

if ($usage) {
  $ids = implode(',', array_keys($usage));
  $qname = $conn->query("
    SELECT idrawmate, nmrawmate
    FROM rawmate
    WHERE idrawmate IN ($ids)
  ");
  $names = [];
  while ($n = $qname->fetch_assoc()) {
    $names[$n['idrawmate']] = $n['nmrawmate'];
  }

  foreach ($usage as $idraw => $qty) {
    $rows[] = [
      'nmrawmate' => $names[$idraw] ?? 'ID#' . $idraw,
      'qty' => $qty
    ];
    $grand += $qty;
  }
}
?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2 align-items-center">
        <div class="col-sm-6">
          <h4><i class="fas fa-clipboard-list"></i> Laporan Pemakaian Bahan — <?= htmlspecialchars($noBatch) ?></h4>
          <div class="small text-muted">Perhitungan otomatis (HPP), bukan transaksi stok.</div>
        </div>
        <div class="col-sm-6 text-right">
          <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-secondary btn-sm">
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
          <h3 class="card-title">Ringkasan Pemakaian Material</h3>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="usageReport" class="table table-bordered table-striped table-sm">
              <thead class="text-center">
                <tr>
                  <th style="width:48px">#</th>
                  <th>Material</th>
                  <th style="width:140px">Qty Terpakai</th>
                </tr>
              </thead>
              <tbody>
                <?php $no = 1;
                foreach ($rows as $r): ?>
                  <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($r['nmrawmate']) ?></td>
                    <td class="text-right"><?= number_format($r['qty'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="2" class="text-right">TOTAL PEMAKAIAN</th>
                  <th class="text-right"><?= number_format($grand, 2) ?></th>
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
  document.title = "Laporan Pemakaian Bahan <?= htmlspecialchars($noBatch) ?>";
  $(function() {
    $("#usageReport").DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      ordering: false,
      paging: false,
      searching: true,
      info: false,
      buttons: ["copy", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#usageReport_wrapper .col-md-6:eq(0)');
  });
</script>

<?php include "../footnote.php"; ?>
<?php include "../footer.php"; ?>