<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Helpers
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function tgl($d)
{
    return $d ? date('d/M/Y', strtotime($d)) : '-';
}

// Ambil idreceive dari GET atau POST
$idreceive = isset($_GET['idreceive']) ? (int)$_GET['idreceive'] : (int)($_POST['idreceive'] ?? 0);
if ($idreceive <= 0) {
    die("Penerimaan tidak dikenali.");
}

$errors = [];

// =========================
// PROSES SIMPAN (POST)
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $weigh_date = $_POST['weigh_date'] ?? date('Y-m-d');
    $note       = trim($_POST['note'] ?? '');
    $iduser     = (int)($_SESSION['idusers'] ?? 0);

    $idreceivedetail = $_POST['idreceivedetail'] ?? [];
    $actual_weight   = $_POST['actual_weight'] ?? [];
    $detail_notes    = $_POST['detail_notes'] ?? [];

    if ($iduser <= 0) {
        $errors[] = "Session user tidak valid.";
    }

    if (empty($idreceivedetail)) {
        $errors[] = "Tidak ada data detail untuk disimpan.";
    }

    // Normalisasi & validasi berat actual
    $rows = [];
    if (empty($errors)) {
        foreach ($idreceivedetail as $i => $idr) {
            $idr = (int)$idr;
            $w   = $actual_weight[$i] ?? '';
            $w   = str_replace(['.', ','], ['', '.'], $w); // misal 1.000,50 → 1000.50 (kasar)
            $w   = trim($w);
            $noteDetail = trim($detail_notes[$i] ?? '');

            if ($w === '') {
                $errors[] = "Berat actual baris " . ($i + 1) . " belum diisi.";
                continue;
            }
            if (!is_numeric($w) || (float)$w <= 0) {
                $errors[] = "Berat actual baris " . ($i + 1) . " tidak valid.";
                continue;
            }

            $rows[] = [
                'idreceivedetail' => $idr,
                'weight'          => (float)$w,
                'notes'           => $noteDetail
            ];
        }
    }

    if (empty($rows) && empty($errors) === false) {
        // sudah diisi errors di atas
    } elseif (empty($rows)) {
        $errors[] = "Tidak ada baris valid untuk disimpan.";
    }

    if (empty($errors)) {
        // Mulai transaksi
        $conn->begin_transaction();
        try {
            // Pastikan belum pernah ditimbang
            $stmt = $conn->prepare("
                SELECT idweigh 
                FROM weight_cattle 
                WHERE idreceive = ? AND is_deleted = 0 
                LIMIT 1
            ");
            $stmt->bind_param('i', $idreceive);
            $stmt->execute();
            $stmt->bind_result($existing);
            if ($stmt->fetch()) {
                $stmt->close();
                throw new Exception("Penerimaan ini sudah pernah diproses penimbangan.");
            }
            $stmt->close();

            // Ambil map eartag untuk idreceivedetail
            $mapEartag = [];
            $stmt = $conn->prepare("
                SELECT idreceivedetail, eartag
                FROM cattle_receive_detail
                WHERE idreceive = ?
            ");
            $stmt->bind_param('i', $idreceive);
            $stmt->execute();
            $resMap = $stmt->get_result();
            while ($row = $resMap->fetch_assoc()) {
                $mapEartag[(int)$row['idreceivedetail']] = $row['eartag'];
            }
            $stmt->close();

            if (empty($mapEartag)) {
                throw new Exception("Detail penerimaan tidak ditemukan.");
            }

            // Insert header ke weight_cattle
            $weigh_no = 'WT-' . date('YmdHis');

            $stmt = $conn->prepare("
                INSERT INTO weight_cattle
                    (idreceive, weigh_no, weigh_date, idweigher, note, createby)
                VALUES (?,?,?,?,?,?)
            ");
            $stmt->bind_param(
                'issisi',
                $idreceive,
                $weigh_no,
                $weigh_date,
                $iduser,
                $note,
                $iduser
            );
            $stmt->execute();
            if ($stmt->affected_rows <= 0) {
                throw new Exception("Gagal menyimpan header penimbangan.");
            }
            $idweigh = $stmt->insert_id;
            $stmt->close();

            // Insert detail ke weight_cattle_detail
            $stmt = $conn->prepare("
                INSERT INTO weight_cattle_detail
                    (idweigh, idreceivedetail, eartag, weight, notes, createby)
                VALUES (?,?,?,?,?,?)
            ");

            foreach ($rows as $r) {
                $idr   = $r['idreceivedetail'];
                if (!isset($mapEartag[$idr])) {
                    throw new Exception("Eartag untuk detail ID $idr tidak ditemukan.");
                }
                $etag  = $mapEartag[$idr];
                $w     = $r['weight'];
                $nd    = $r['notes'];

                $stmt->bind_param(
                    'iisdsi',
                    $idweigh,
                    $idr,
                    $etag,
                    $w,
                    $nd,
                    $iduser
                );
                $stmt->execute();
            }
            $stmt->close();

            $conn->commit();

            // Redirect ke index timbang
            header("Location: index.php");
            exit;
        } catch (Exception $ex) {
            $conn->rollback();
            $errors[] = $ex->getMessage();
        }
    }
}

// =========================
// AMBIL DATA HEADER & DETAIL UNTUK FORM
// =========================

// Header penerimaan
$stmt = $conn->prepare("
    SELECT 
        r.idreceive,
        r.receipt_date,
        p.nopo,
        s.nmsupplier,
        COUNT(d.idreceivedetail) AS heads
    FROM cattle_receive r
    JOIN pocattle p ON p.idpo = r.idpo
    JOIN supplier s ON s.idsupplier = p.idsupplier
    LEFT JOIN cattle_receive_detail d ON d.idreceive = r.idreceive
    WHERE r.idreceive = ? AND r.is_deleted = 0
    GROUP BY r.idreceive, r.receipt_date, p.nopo, s.nmsupplier
    LIMIT 1
");
$stmt->bind_param('i', $idreceive);
$stmt->execute();
$header = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$header) {
    die("Data penerimaan tidak ditemukan.");
}

// Detail per eartag
$stmt = $conn->prepare("
    SELECT 
        idreceivedetail,
        eartag,
        weight AS receive_weight,
        class,
        rfid
    FROM cattle_receive_detail
    WHERE idreceive = ?
    ORDER BY eartag
");
$stmt->bind_param('i', $idreceive);
$stmt->execute();
$detail = $stmt->get_result();
?>

<div class="content-wrapper">

    <!-- Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Proses Penimbangan</h1>
                    <p class="mb-0 text-muted">
                        PO: <?= e($header['nopo']); ?> |
                        Supplier: <?= e($header['nmsupplier']); ?> |
                        Tgl Receive: <?= e(tgl($header['receipt_date'])); ?> |
                        Ekor: <?= number_format((int)$header['heads'], 0, ',', '.'); ?>
                    </p>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="draft.php" class="btn btn-secondary btn-sm mt-2">
                        <i class="fas fa-undo-alt"></i> Kembali ke Draft
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Main -->
    <section class="content">
        <div class="container-fluid">

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="store.php" method="post">
                <input type="hidden" name="idreceive" value="<?= (int)$idreceive; ?>">

                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="weigh_date">Tanggal Timbang</label>
                                <input type="date" name="weigh_date" id="weigh_date"
                                    class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-9">
                                <label for="note">Catatan</label>
                                <input type="text" name="note" id="note"
                                    class="form-control form-control-sm"
                                    value="<?= e($_POST['note'] ?? ''); ?>"
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
                                        $idr    = (int)$row['idreceivedetail'];
                                        $eartag = $row['eartag'];
                                        $receiveWeight = (float)$row['receive_weight'];

                                        // untuk repopulasi jika submit error
                                        $postedWeight = $_POST['actual_weight'][$no - 1] ?? '';
                                        $postedNote   = $_POST['detail_notes'][$no - 1] ?? '';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no; ?></td>
                                            <td>
                                                <?= e($eartag); ?>
                                                <input type="hidden" name="idreceivedetail[]"
                                                    value="<?= $idr; ?>">
                                            </td>
                                            <td class="text-right">
                                                <?= number_format($receiveWeight, 2, ',', '.'); ?>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0"
                                                    name="actual_weight[]"
                                                    class="form-control form-control-sm text-right"
                                                    value="<?= e($postedWeight); ?>" required>
                                            </td>
                                            <td>
                                                <input type="text" name="detail_notes[]"
                                                    class="form-control form-control-sm"
                                                    value="<?= e($postedNote); ?>"
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
                            onclick="return confirm('Simpan hasil timbang?')">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </section>
</div>

<script>
    document.title = "Input Hasil Timbang";

    // ENTER di Berat Actual → turun ke Berat Actual baris berikutnya
    const weightInputs = document.querySelectorAll('input[name="actual_weight[]"]');
    weightInputs.forEach((input, index) => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (weightInputs[index + 1]) {
                    weightInputs[index + 1].focus();
                    weightInputs[index + 1].select();
                }
            }
        });
    });

    // ENTER di Catatan → turun ke Catatan baris berikutnya
    const noteInputs = document.querySelectorAll('input[name="detail_notes[]"]');
    noteInputs.forEach((input, index) => {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (noteInputs[index + 1]) {
                    noteInputs[index + 1].focus();
                    noteInputs[index + 1].select();
                }
            }
        });
    });
</script>



<?php include "../footer.php"; ?>