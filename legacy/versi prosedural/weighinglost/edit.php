<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helpers
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function tgl($d)
{
    return $d ? date('d/M/Y', strtotime($d)) : '-';
}
function n2($n)
{
    return number_format((float)$n, 2, ',', '.');
}
function rupiah($n)
{
    return number_format((float)$n, 0, ',', '.');
}

// =======================================
// AMBIL ID LOSS
// =======================================
$idloss = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idloss <= 0) {
    die("Parameter idloss tidak valid.");
}

// =======================================
// HEADER LOSS + KONTEKS WEIGH/RECEIVE/PO
// =======================================
$sqlHead = "
SELECT 
    l.idloss,
    l.idreceive,
    l.idweigh,
    l.loss_no,
    l.loss_date,
    l.note,
    l.total_receive_weight,
    l.total_actual_weight,
    l.total_loss_weight,
    l.total_loss_cost,
    l.creatime,
    u.fullname AS createuser,

    w.weigh_no,
    w.weigh_date,

    r.receipt_date,
    r.doc_no,
    p.nopo,
    s.nmsupplier
FROM cattle_loss_receive l
JOIN weight_cattle w
      ON w.idweigh = l.idweigh
     AND w.is_deleted = 0
JOIN cattle_receive r
      ON r.idreceive = l.idreceive
     AND r.is_deleted = 0
JOIN pocattle p
      ON p.idpo = r.idpo
     AND p.is_deleted = 0
JOIN supplier s
      ON s.idsupplier = p.idsupplier
LEFT JOIN users u
      ON u.idusers = l.createby
WHERE l.idloss = ?
  AND l.is_deleted = 0
LIMIT 1
";
$stmtH = $conn->prepare($sqlHead);
$stmtH->bind_param('i', $idloss);
$stmtH->execute();
$headRes = $stmtH->get_result();
$header  = $headRes->fetch_assoc();
$stmtH->close();

if (!$header) {
    die("Data loss tidak ditemukan.");
}

$idreceive = (int)$header['idreceive'];
$idweigh   = (int)$header['idweigh'];

// =======================================
// DETAIL LOSS
// =======================================
$sqlDet = "
SELECT
    d.idlossdetail,
    d.idreceivedetail,
    d.idweighdetail,
    d.eartag,
    d.cattle_class,
    d.receive_weight,
    d.actual_weight,
    d.loss_weight,
    d.price_perkg,
    d.loss_cost,
    d.notes
FROM cattle_loss_receive_detail d
WHERE d.idloss = ?
ORDER BY d.eartag
";
$stmtD = $conn->prepare($sqlDet);
$stmtD->bind_param('i', $idloss);
$stmtD->execute();
$detRes = $stmtD->get_result();
$details = [];
while ($row = $detRes->fetch_assoc()) {
    $details[] = $row;
}
$stmtD->close();

if (empty($details)) {
    die("Tidak ada detail loss untuk dokumen ini.");
}

$totalReceive = (float)$header['total_receive_weight'];
$totalActual  = (float)$header['total_actual_weight'];
$totalLoss    = (float)$header['total_loss_weight'];
$totalCost    = (float)$header['total_loss_cost'];
?>

<div class="content-wrapper">

    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Edit Cattle Weight Loss (Receiving)</h1>
                    <p class="mb-0 text-muted">Dokumen: <?= e($header['loss_no']); ?></p>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali ke List
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main -->
    <section class="content">
        <div class="container-fluid">
            <form method="post" action="update.php">

                <input type="hidden" name="idloss" value="<?= (int)$header['idloss']; ?>">
                <input type="hidden" name="idweigh" value="<?= (int)$header['idweigh']; ?>">
                <input type="hidden" name="idreceive" value="<?= (int)$header['idreceive']; ?>">

                <!-- CARD HEADER -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Header</h3>
                    </div>

                    <div class="card-body">

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Loss No</label>
                                <input type="text" class="form-control" value="<?= e($header['loss_no']); ?>" readonly>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Loss Date</label>
                                <input type="date" name="loss_date" class="form-control" value="<?= e($header['loss_date']); ?>" required>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Weigh No</label>
                                <input type="text" class="form-control" value="<?= e($header['weigh_no']); ?>" readonly>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Weigh Date</label>
                                <input type="text" class="form-control" value="<?= tgl($header['weigh_date']); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Receive Date</label>
                                <input type="text" class="form-control" value="<?= tgl($header['receipt_date']); ?>" readonly>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Doc Receive</label>
                                <input type="text" class="form-control" value="<?= e($header['doc_no']); ?>" readonly>
                            </div>

                            <div class="form-group col-md-3">
                                <label>No PO</label>
                                <input type="text" class="form-control" value="<?= e($header['nopo']); ?>" readonly>
                            </div>

                            <div class="form-group col-md-3">
                                <label>Supplier</label>
                                <input type="text" class="form-control" value="<?= e($header['nmsupplier']); ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Note</label>
                            <textarea name="note" class="form-control" rows="2"><?= e($header['note']); ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3"><label>Total Receive Weight (Kg)</label><input type="text" class="form-control" value="<?= n2($totalReceive); ?>" readonly></div>
                            <div class="form-group col-md-3"><label>Total Actual Weight (Kg)</label><input type="text" class="form-control" value="<?= n2($totalActual); ?>" readonly></div>
                            <div class="form-group col-md-3"><label>Total Loss Weight (Kg)</label><input type="text" class="form-control" value="<?= n2($totalLoss); ?>" readonly></div>
                            <div class="form-group col-md-3"><label>Total Loss Cost (Rp)</label><input type="text" class="form-control" value="<?= rupiah($totalCost); ?>" readonly></div>
                        </div>

                    </div>
                </div>

                <!-- CARD DETAIL -->
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

                                    $recv  = (float)$d['receive_weight'];
                                    $act   = (float)$d['actual_weight'];
                                    $lossW = (float)$d['loss_weight'];   // sudah dari store → actual - receive (bisa negatif)
                                    $price = $d['price_perkg'];
                                    $lossCost = $d['loss_cost'];
                                    $priceVal = ($price === null ? '' : (float)$price);
                                ?>
                                    <tr class="text-center">

                                        <td><?= $no; ?></td>

                                        <td class="text-left">
                                            <?= e($d['eartag']); ?>
                                            <input type="hidden" name="idlossdetail[]" value="<?= (int)$d['idlossdetail']; ?>">
                                            <input type="hidden" name="idreceivedetail[]" value="<?= (int)$d['idreceivedetail']; ?>">
                                            <input type="hidden" name="idweighdetail[]" value="<?= (int)$d['idweighdetail']; ?>">
                                            <input type="hidden" name="eartag[]" value="<?= e($d['eartag']); ?>">
                                        </td>

                                        <td>
                                            <?= e($d['cattle_class']); ?>
                                            <input type="hidden" name="class[]" value="<?= e($d['cattle_class']); ?>">
                                        </td>

                                        <td class="text-right">
                                            <?= n2($recv); ?>
                                            <input type="hidden" name="receive_weight[]" value="<?= $recv; ?>">
                                        </td>

                                        <td class="text-right">
                                            <?= n2($act); ?>
                                            <input type="hidden" name="actual_weight[]" value="<?= $act; ?>">
                                        </td>

                                        <td class="text-right">
                                            <?= n2($lossW); ?>
                                            <input type="hidden" name="loss_weight[]" value="<?= $lossW; ?>">
                                        </td>

                                        <td>
                                            <input type="number"
                                                step="1"
                                                min="0"
                                                name="price_perkg[]"
                                                class="form-control form-control-sm text-right price-input"
                                                value="<?= $priceVal !== '' ? $priceVal : ''; ?>"
                                                data-loss="<?= $lossW; ?>"
                                                data-row="<?= $no; ?>">
                                        </td>

                                        <td class="text-right">
                                            <span id="loss_cost_row_<?= $no; ?>">
                                                <?php
                                                if ($priceVal === '' || $lossCost === null) {
                                                    echo '-';
                                                } else {
                                                    echo rupiah($lossCost);
                                                }
                                                ?>
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
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Update Loss
                        </button>
                    </div>

                </div>

            </form>
        </div>
    </section>
</div>

<script>
    // Hitung ulang loss cost saat harga per kg diubah
    document.querySelectorAll('.price-input').forEach(function(el) {
        el.addEventListener('input', function() {

            let loss = parseFloat(this.dataset.loss) || 0; // bisa negatif
            let price = parseFloat(this.value) || 0;
            let row = this.dataset.row;

            let cost = loss * price; // negatif × positif → tetap NEGATIF jika loss negatif

            let span = document.getElementById("loss_cost_row_" + row);
            if (!span) return;

            if (this.value === "") {
                span.textContent = "-";
            } else {
                span.textContent = new Intl.NumberFormat("id-ID", {
                    maximumFractionDigits: 0
                }).format(cost);
            }
        });
    });

    document.title = "Edit Cattle Weight Loss (Receiving)";
</script>

<?php include "../footer.php"; ?>