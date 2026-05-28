<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function tgl($d)
{
    return $d ? date('d/M/Y', strtotime($d)) : '-';
}

// Ambil idweigh dari GET
$idweigh = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idweigh <= 0) {
    die("Data timbang tidak dikenali.");
}

// =========================
// AMBIL DATA HEADER
// =========================
$stmt = $conn->prepare("
    SELECT 
        w.idweigh,
        w.weigh_no,
        w.weigh_date,
        w.note,
        w.idreceive,
        r.receipt_date,
        p.nopo,
        s.nmsupplier,
        u.fullname AS weigher_name,
        COUNT(d.idweighdetail)     AS heads,
        COALESCE(SUM(d.weight), 0) AS total_weight
    FROM weight_cattle w
    JOIN cattle_receive r
          ON r.idreceive = w.idreceive
    JOIN pocattle p
          ON p.idpo = r.idpo
    JOIN supplier s
          ON s.idsupplier = p.idsupplier
    LEFT JOIN users u
          ON u.idusers = w.idweigher
    LEFT JOIN weight_cattle_detail d
          ON d.idweigh = w.idweigh
    WHERE w.idweigh = ? AND w.is_deleted = 0
    GROUP BY 
        w.idweigh,
        w.weigh_no,
        w.weigh_date,
        w.note,
        w.idreceive,
        r.receipt_date,
        p.nopo,
        s.nmsupplier,
        u.fullname
    LIMIT 1
");
$stmt->bind_param('i', $idweigh);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$header) {
    die("Data timbang tidak ditemukan.");
}

// =========================
// AMBIL DATA DETAIL
// =========================
$stmt = $conn->prepare("
    SELECT 
        d.idweighdetail,
        d.idreceivedetail,
        d.eartag,
        crd.weight AS receive_weight,
        d.weight   AS actual_weight,
        d.notes    AS detail_notes
    FROM weight_cattle_detail d
    JOIN cattle_receive_detail crd
          ON crd.idreceivedetail = d.idreceivedetail
    WHERE d.idweigh = ?
    ORDER BY d.eartag
");
$stmt->bind_param('i', $idweigh);
$stmt->execute();
$detail = $stmt->get_result();
$stmt->close();
?>

<div class="content-wrapper">

    <!-- Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Edit Penimbangan</h1>
                    <p class="mb-0 text-muted">
                        No Timbang: <?= e($header['weigh_no']); ?> |
                        PO: <?= e($header['nopo']); ?> |
                        Supplier: <?= e($header['nmsupplier']); ?><br>
                        Tgl Receive: <?= e(tgl($header['receipt_date'])); ?> |
                        Ekor: <?= number_format((int)$header['heads'], 0, ',', '.'); ?> |
                        Total Berat: <?= number_format((float)$header['total_weight'], 2, ',', '.'); ?> Kg
                    </p>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm mt-2">
                        <i class="fas fa-undo-alt"></i> Kembali ke List
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main -->
    <section class="content">
        <div class="container-fluid">

            <?php if (!empty($_GET['msg'])): ?>
                <div class="alert alert-info">
                    <?= e($_GET['msg']); ?>
                </div>
            <?php endif; ?>

            <form action="update.php" method="post">
                <input type="hidden" name="idweigh" value="<?= (int)$header['idweigh']; ?>">

                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="weigh_date">Tanggal Timbang</label>
                                <input type="date" name="weigh_date" id="weigh_date"
                                    class="form-control form-control-sm"
                                    value="<?= e($header['weigh_date']); ?>" required>
                            </div>
                            <div class="col-md-9">
                                <label for="note">Catatan</label>
                                <input type="text" name="note" id="note"
                                    class="form-control form-control-sm"
                                    value="<?= e($header['note'] ?? ''); ?>"
                                    placeholder="Catatan header (optional)">
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th style="width:5%;">No</th>
                                        <th>Eartag</th>
                                        <th>Berat Awal (Kg)</th>
                                        <th>Berat Actual (Kg)</th>
                                        <th>Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    while ($row = $detail->fetch_assoc()):
                                        $idweighdetail  = (int)$row['idweighdetail'];
                                        $eartag         = $row['eartag'];
                                        $receiveWeight  = (float)$row['receive_weight'];
                                        $actualWeight   = (float)$row['actual_weight'];
                                        $detailNotes    = $row['detail_notes'];
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no; ?></td>
                                            <td>
                                                <?= e($eartag); ?>
                                                <input type="hidden" name="idweighdetail[]"
                                                    value="<?= $idweighdetail; ?>">
                                            </td>
                                            <td class="text-right">
                                                <?= number_format($receiveWeight, 2, ',', '.'); ?>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0"
                                                    name="actual_weight[]"
                                                    class="form-control form-control-sm text-right"
                                                    value="<?= e(number_format($actualWeight, 2, '.', '')); ?>"
                                                    required>
                                            </td>
                                            <td>
                                                <input type="text" name="detail_notes[]"
                                                    class="form-control form-control-sm"
                                                    value="<?= e($detailNotes); ?>"
                                                    placeholder="Catatan per ekor (optional)">
                                            </td>
                                        </tr>
                                    <?php
                                        $no++;
                                    endwhile;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary btn-sm"
                            onclick="return confirm('Simpan perubahan penimbangan?')">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </section>
</div>

<script>
    document.title = "Edit Timbangan";
</script>
<?php include "../footer.php"; ?>