<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

if (!isset($_GET['id'])) {
    die("Jalankan Dari Modul Repack");
}
$idrepack = (int)$_GET['id'];

/* ==========================================================
   1) Ambil data repack & hasil produksi
   ========================================================== */
$sql = "SELECT dh.idbarang, b.nmbarang, dh.qty, dh.pcs
        FROM detailhasil dh
        JOIN barang b ON b.idbarang = dh.idbarang
        WHERE dh.idrepack = ? AND dh.is_deleted = 0
        ORDER BY b.nmbarang";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idrepack);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($r = $res->fetch_assoc()) {
    $idb = (int)$r['idbarang'];
    if (!isset($items[$idb])) {
        $items[$idb] = [
            'nmbarang' => $r['nmbarang'],
            'box' => 1,
            'pcs' => (int)$r['pcs'],
            'qty' => (float)$r['qty'],
        ];
    } else {
        $items[$idb]['box'] += 1;
        $items[$idb]['pcs'] += (int)$r['pcs'];
        $items[$idb]['qty'] += (float)$r['qty'];
    }
}

/* ==========================================================
   2) Ambil daftar rawmate untuk dropdown Material Tambahan
   ========================================================== */
$rawOptions = "";
$qraw = $conn->query("
  SELECT idrawmate, nmrawmate 
  FROM rawmate 
  WHERE nmrawmate <> '' 
  ORDER BY LOWER(nmrawmate) ASC
");
while ($r = $qraw->fetch_assoc()) {
    $idraw = (int)$r['idrawmate'];
    $nmraw = ucwords(strtolower(trim($r['nmrawmate'])));
    $rawOptions .= "<option value='$idraw'>$nmraw</option>";
}

/* ==========================================================
   3) Deteksi bahan BOM aktif per barang
   ========================================================== */
function detect_materials_ru($conn, $idbarang)
{
    $flags = [
        'has_top' => false,
        'has_bottom' => false,
        'has_linier' => false,
        'has_vacuum' => false,
        'has_karung' => false,
        'has_tray' => false,
    ];
    $q = $conn->query("
        SELECT r.idrawcategory, r.nmrawmate
        FROM bom_rawmate b
        JOIN rawmate r ON r.idrawmate = b.idrawmate
        WHERE b.idbarang = {$idbarang} AND b.is_active = 1
    ");
    while ($rb = $q->fetch_assoc()) {
        $cat = (int)$rb['idrawcategory'];
        $nmU = strtoupper($rb['nmrawmate']);
        if ($cat === 2) {
            if (strpos($nmU, 'TOP') !== false) $flags['has_top'] = true;
            else $flags['has_bottom'] = true;
        } elseif ($cat === 3) {
            if (strpos($nmU, 'LINIER') !== false) $flags['has_linier'] = true;
            else $flags['has_vacuum'] = true;
        } elseif ($cat === 21) {
            $flags['has_karung'] = true;
        } elseif ($cat === 22 || strpos($nmU, 'TRAY') !== false) {
            $flags['has_tray'] = true;
        }
    }
    return $flags;
}

/* ==========================================================
   4) Siapkan data tabel utama
   ========================================================== */
$rows = [];
foreach ($items as $idbarang => $v) {
    $f = detect_materials_ru($conn, $idbarang);
    $box = (int)$v['box'];
    $pcs = (int)$v['pcs'];
    $rows[] = [
        'idbarang' => $idbarang,
        'nmbarang' => $v['nmbarang'],
        'top'    => $f['has_top']    ? $box : 0,
        'bottom' => $f['has_bottom'] ? $box : 0,
        'karung' => $f['has_karung'] ? $box : 0,
        'linier' => $f['has_linier'] ? $box : 0,
        'vacuum' => $f['has_vacuum'] ? $pcs : 0,
        'tray'   => $f['has_tray']   ? $pcs : 0,
    ];
}

/* ==========================================================
   Fungsi render input
   ========================================================== */
if (!function_exists('render_input')) {
    function render_input($name, $val)
    {
        $v = (float)$val;
        $v_str = ($v == (int)$v) ? (int)$v : rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.');
        return '<input type="number" step="1" min="0" class="form-control form-control-sm text-center" name="' .
            htmlspecialchars($name, ENT_QUOTES) . '" value="' . $v_str . '">';
    }
}

/* ==========================================================
   Ambil nomor repack
   ========================================================== */
$qrepack = $conn->query("SELECT norepack FROM repack WHERE idrepack = $idrepack LIMIT 1");
$drep = $qrepack->fetch_assoc();
$norepack = $drep ? $drep['norepack'] : "RPC-???";
?>

<!-- ==========================================================
     TAMPILAN HALAMAN
========================================================== -->
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h4>Pemakaian Bahan (Editable) — <?= htmlspecialchars($norepack) ?></h4>
                    <div class="text-muted small">
                        Acuan: Top/Bottom/Linier/Karung = Box, Vacuum/Tray = Pcs
                    </div>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="detailhasil.php?id=<?= $idrepack ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <form method="post" action="save_rawusage_repack.php" id="usageForm">
                <input type="hidden" name="idrepack" value="<?= $idrepack ?>">

                <div class="card card-dark shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title">Penyesuaian Pemakaian Material</h3>
                    </div>
                    <div class="card-body">

                        <!-- ======= TABEL PRODUK ======= -->
                        <div class="table-responsive">
                            <table id="usageTable" class="table table-bordered table-striped table-sm align-middle">
                                <thead class="text-center">
                                    <tr>
                                        <th style="width:45px">#</th>
                                        <th>Product</th>
                                        <th>Top</th>
                                        <th>Bottom</th>
                                        <th>Karung</th>
                                        <th>Linier</th>
                                        <th>Vacuum</th>
                                        <th>Tray</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    foreach ($rows as $r): $idb = $r['idbarang']; ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td class="text-left"><?= htmlspecialchars($r['nmbarang']) ?></td>
                                            <td><?= render_input("rows[$idb][top]", $r['top']) ?></td>
                                            <td><?= render_input("rows[$idb][bottom]", $r['bottom']) ?></td>
                                            <td><?= render_input("rows[$idb][karung]", $r['karung']) ?></td>
                                            <td><?= render_input("rows[$idb][linier]", $r['linier']) ?></td>
                                            <td><?= render_input("rows[$idb][vacuum]", $r['vacuum']) ?></td>
                                            <td><?= render_input("rows[$idb][tray]", $r['tray']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- ======= MATERIAL TAMBAHAN ======= -->
                        <div class="mt-4">
                            <h6 class="text-dark font-weight-bold mb-2">Material Tambahan (jika ada)</h6>
                            <div id="extra-list"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addMaterial()">
                                <i class="fas fa-plus-circle"></i> Tambah Material Lain
                            </button>
                        </div>

                        <div class="mt-4 text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<!-- ==========================================================
     SCRIPT
========================================================== -->
<script>
    function addMaterial() {
        const list = document.getElementById('extra-list');
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2 align-items-center extra-item';

        newRow.innerHTML = `
    <div class="col-12 col-md-6 mb-1">
      <div class="form-group mb-0">
        <select name="extra_idrawmate[]" class="form-control form-control-sm" required>
          <option value="">-- Pilih Material --</option>
          <?= $rawOptions ?>
        </select>
      </div>
    </div>
    <div class="col-6 col-md-3 mb-1">
      <div class="form-group mb-0">
        <input type="number" name="extra_qty[]" step="1" min="0" 
               class="form-control form-control-sm text-center" placeholder="Qty" required>
      </div>
    </div>
    <div class="col-6 col-md-3 mb-1 text-right">
      <button type="button" class="btn btn-link text-danger" onclick="removeMaterial(this)">
        <i class="fas fa-minus-circle"></i>
      </button>
    </div>
  `;
        list.appendChild(newRow);
    }

    function removeMaterial(btn) {
        btn.closest('.extra-item').remove();
    }

    // Init DataTables
    document.addEventListener("DOMContentLoaded", function() {
        $("#usageTable").DataTable({
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            ordering: false,
            paging: false,
            searching: true,
            info: false,
            buttons: ["copy", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#usageTable_wrapper .col-md-6:eq(0)');
    });
</script>

<?php include "../footnote.php"; ?>
<?php include "../footer.php"; ?>