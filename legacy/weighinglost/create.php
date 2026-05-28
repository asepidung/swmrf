<?php
// create.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('tgl')) {
    function tgl($d)
    {
        return $d ? date('d/M/Y', strtotime($d)) : '-';
    }
}

// =======================================
// MODE FORM (GET)
// =======================================
$idweigh = isset($_GET['idweigh']) ? (int)$_GET['idweigh'] : 0;
if ($idweigh <= 0) {
    die("Parameter idweigh tidak valid.");
}

$sqlHead = "
SELECT
    w.idweigh,
    w.weigh_no,
    w.weigh_date,
    w.idreceive,
    r.receipt_date,
    r.doc_no,
    p.nopo,
    s.nmsupplier
FROM weight_cattle w
JOIN cattle_receive r
    ON r.idreceive = w.idreceive
    AND r.is_deleted = 0
JOIN pocattle p
    ON p.idpo = r.idpo
    AND p.is_deleted = 0
JOIN supplier s
    ON s.idsupplier = p.idsupplier
WHERE w.idweigh = ?
  AND w.is_deleted = 0
LIMIT 1
";
$stmt = $conn->prepare($sqlHead);
$stmt->bind_param('i', $idweigh);
$stmt->execute();
$headRes = $stmt->get_result();
$header  = $headRes->fetch_assoc();
$stmt->close();

if (!$header) {
    die("Data weighing tidak ditemukan.");
}
$idreceive = (int)$header['idreceive'];

$sqlDet = "
SELECT
    wd.idweighdetail,
    rd.idreceivedetail,
    rd.eartag,
    rd.class,
    rd.weight AS receive_weight,
    wd.weight AS actual_weight,
    (wd.weight - rd.weight) AS loss_weight,
    pd.price AS default_price
FROM weight_cattle_detail wd
JOIN cattle_receive_detail rd
    ON rd.idreceivedetail = wd.idreceivedetail
JOIN cattle_receive r
    ON r.idreceive = rd.idreceive
LEFT JOIN pocattledetail pd
    ON pd.idpo = r.idpo
    AND TRIM(LOWER(pd.class)) = TRIM(LOWER(rd.class))
    AND pd.is_deleted = 0
WHERE wd.idweigh = ?
ORDER BY rd.eartag
";

$stmt2 = $conn->prepare($sqlDet);
$stmt2->bind_param('i', $idweigh);
$stmt2->execute();
$detailRes = $stmt2->get_result();
$details   = [];
while ($row = $detailRes->fetch_assoc()) {
    $details[] = $row;
}
$stmt2->close();

if (empty($details)) {
    die("Tidak ada detail timbang untuk weighing ini.");
}

$totalReceive = 0;
$totalActual  = 0;
$totalLoss    = 0;
foreach ($details as $d) {
    $totalReceive += (float)$d['receive_weight'];
    $totalActual  += (float)$d['actual_weight'];
    $totalLoss    += (float)$d['loss_weight'];
}
?>

<div class="content-wrapper">

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Cattle Weight Loss (Receiving) - Create</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="draft.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali ke Draft
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- PERBAIKAN DI SINI -->
            <form method="post" action="store.php">

                <input type="hidden" name="idweigh" value="<?= (int)$header['idweigh']; ?>">
                <input type="hidden" name="idreceive" value="<?= (int)$header['idreceive']; ?>">

                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Header</h3>
                    </div>
                    <div class="card-body">

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Weigh No</label>
                                <input type="text" class="form-control" value="<?= e($header['weigh_no']); ?>" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Weigh Date</label>
                                <input type="text" class="form-control" value="<?= tgl($header['weigh_date']); ?>" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Receive Date</label>
                                <input type="text" class="form-control" value="<?= tgl($header['receipt_date']); ?>" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Doc Receive</label>
                                <input type="text" class="form-control" value="<?= e($header['doc_no']); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>No PO</label>
                                <input type="text" class="form-control" value="<?= e($header['nopo']); ?>" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Supplier</label>
                                <input type="text" class="form-control" value="<?= e($header['nmsupplier']); ?>" readonly>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Loss Date</label>
                                <input type="date" name="loss_date" class="form-control"
                                    value="<?= date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Note</label>
                            <textarea name="note" class="form-control" rows="2"></textarea>
                        </div>

                    </div>
                </div>

                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title mb-0">Detail per Ekor</h3>
                    </div>

                    <div class="card-body table-responsive p-0">
                        <table class="table table-bordered table-hover table-sm mb-0">
                            <thead class="text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Eartag</th>
                                    <th>Class</th>
                                    <th>Receive Wt (Kg)</th>
                                    <th>Actual Wt (Kg)</th>
                                    <th>Loss Wt (Kg)</th>
                                    <th>Price / Kg</th>
                                    <th>Loss Cost (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                foreach ($details as $d):
                                    $recv = (float)$d['receive_weight'];
                                    $act  = (float)$d['actual_weight'];
                                    $loss = (float)$d['loss_weight'];
                                    $price = $d['default_price'] !== null ? (float)$d['default_price'] : null;
                                    $lossCost = ($price !== null) ? $loss * $price : 0;
                                ?>
                                    <tr class="text-center">
                                        <td><?= $no; ?></td>

                                        <td class="text-left">
                                            <?= e($d['eartag']); ?>
                                            <input type="hidden" name="eartag[]" value="<?= e($d['eartag']); ?>">
                                            <input type="hidden" name="idreceivedetail[]" value="<?= (int)$d['idreceivedetail']; ?>">
                                            <input type="hidden" name="idweighdetail[]" value="<?= (int)$d['idweighdetail']; ?>">
                                        </td>

                                        <td>
                                            <?= e($d['class']); ?>
                                            <input type="hidden" name="class[]" value="<?= e($d['class']); ?>">
                                        </td>

                                        <td class="text-right">
                                            <?= number_format($recv, 2, ',', '.'); ?>
                                            <input type="hidden" name="receive_weight[]" value="<?= $recv; ?>">
                                        </td>

                                        <td class="text-right">
                                            <?= number_format($act, 2, ',', '.'); ?>
                                            <input type="hidden" name="actual_weight[]" value="<?= $act; ?>">
                                        </td>

                                        <td class="text-right">
                                            <?= number_format($loss, 2, ',', '.'); ?>
                                        </td>

                                        <td>
                                            <input type="number"
                                                step="1"
                                                min="0"
                                                name="price_perkg[]"
                                                class="form-control form-control-sm text-right price-input"
                                                value="<?= $price !== null ? $price : ''; ?>"
                                                data-loss="<?= $loss; ?>"
                                                data-row="<?= $no; ?>">
                                        </td>

                                        <td class="text-right">
                                            <span id="loss_cost_row_<?= $no; ?>">
                                                <?= $price !== null ? number_format($lossCost, 0, ',', '.') : '-'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php
                                    $no++;
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Loss
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </section>
</div>

<script>
    document.querySelectorAll('.price-input').forEach(function(el) {
        el.addEventListener('input', function() {
            var loss = parseFloat(this.getAttribute('data-loss')) || 0;
            var row = this.getAttribute('data-row');
            var price = parseFloat(this.value) || 0;
            var cost = loss * price;

            var span = document.getElementById('loss_cost_row_' + row);
            if (!span) return;

            if (this.value === '') {
                span.textContent = '-';
            } else {
                span.textContent = new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 0
                }).format(cost);
            }
        });
    });
</script>

<?php include "../footer.php"; ?>