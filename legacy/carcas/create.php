<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('tgl')) {
    function tgl($d)
    {
        return $d ? date('Y-m-d', strtotime($d)) : '';
    }
}

// ================================
// Validasi & ambil idweight
// ================================
$idweight = isset($_GET['idweight']) ? (int)$_GET['idweight'] : 0;
if ($idweight <= 0) {
    die("ID weight tidak valid.");
}

// ================================
// Ambil HEADER timbang
// ================================
$stmtH = $conn->prepare("
    SELECT 
        w.idweigh,
        w.weigh_no,
        w.weigh_date,
        w.note        AS weigh_note,
        r.receipt_date,
        p.nopo,
        s.idsupplier,
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
    WHERE w.idweigh = ? AND w.is_deleted = 0
    LIMIT 1
");
$stmtH->bind_param("i", $idweight);
$stmtH->execute();
$header = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$header) {
    die("Data timbang tidak ditemukan atau sudah dihapus.");
}

// ================================
// Ambil DETAIL (belum ada carcas)
// ================================
$stmtD = $conn->prepare("
    SELECT 
        d.idweighdetail,
        d.eartag,
        crd.weight AS receive_weight,
        crd.class  AS cattle_class
    FROM weight_cattle_detail d
    JOIN cattle_receive_detail crd
          ON crd.idreceivedetail = d.idreceivedetail
    LEFT JOIN carcasedetail cd
          ON cd.idweightdetail = d.idweighdetail
    LEFT JOIN carcase c
          ON c.idcarcase = cd.idcarcase
         AND c.is_deleted = 0
    WHERE d.idweigh = ?
      AND c.idcarcase IS NULL
    ORDER BY d.eartag
");
$stmtD->bind_param("i", $idweight);
$stmtD->execute();
$resD = $stmtD->get_result();

$details = [];
while ($row = $resD->fetch_assoc()) {
    $details[] = $row;
}
$stmtD->close();

if (empty($details)) {
    die("Tidak ada sapi yang tersisa untuk diproses carcas.");
}

// ================================
// AMBIL CLASS DARI DATABASE
// ================================
$breedOptions = [];
$qClass = $conn->query("
    SELECT class_name
    FROM cattle_class
    ORDER BY idclass ASC
");
while ($r = $qClass->fetch_assoc()) {
    $breedOptions[] = strtoupper(trim($r['class_name']));
}
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-md-6">
                    <h1>Input Carcas</h1>
                    <small class="text-muted">
                        Berdasarkan hasil timbang: <?= e($header['weigh_no']); ?>
                    </small>
                </div>
                <div class="col-md-6 text-right">
                    <a href="draft.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-body">

                    <!-- Info Header -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>No Timbang</label>
                            <input class="form-control form-control-sm" value="<?= e($header['weigh_no']); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label>Tgl Timbang</label>
                            <input class="form-control form-control-sm"
                                value="<?= e(date('d-M-Y', strtotime($header['weigh_date']))); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label>Supplier</label>
                            <input class="form-control form-control-sm" value="<?= e($header['nmsupplier']); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label>No PO</label>
                            <input class="form-control form-control-sm" value="<?= e($header['nopo']); ?>" readonly>
                        </div>
                    </div>

                    <!-- FORM -->
                    <form action="store.php" method="post">
                        <input type="hidden" name="idweight" value="<?= (int)$header['idweigh']; ?>">
                        <input type="hidden" name="idsupplier" value="<?= (int)$header['idsupplier']; ?>">

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label>Tanggal Killing</label>
                                <input type="date" name="killdate" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-9">
                                <label>Catatan</label>
                                <input type="text" name="note" class="form-control form-control-sm">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>No</th>
                                        <th>Eartag</th>
                                        <th>Class</th>
                                        <th>Carcase A</th>
                                        <th>Carcase B</th>
                                        <th>Hides</th>
                                        <th>Tail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    foreach ($details as $d):
                                        $class = strtoupper(trim($d['cattle_class'] ?? ''));
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td class="text-center">
                                                <?= e($d['eartag']); ?>
                                                <input type="hidden" name="idweighdetail[]" value="<?= (int)$d['idweighdetail']; ?>">
                                                <input type="hidden" name="eartag[]" value="<?= e($d['eartag']); ?>">
                                                <input type="hidden" name="live_weight[]" value="<?= e($d['receive_weight']); ?>">
                                            </td>
                                            <td>
                                                <select name="breed[]" class="form-control form-control-sm">
                                                    <option value="">- Pilih Class -</option>
                                                    <?php foreach ($breedOptions as $opt): ?>
                                                        <option value="<?= e($opt); ?>"
                                                            <?= ($opt === $class) ? 'selected' : ''; ?>>
                                                            <?= e($opt); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="number" step="0.01" name="carcase1[]" class="form-control form-control-sm text-right" max="300"></td>
                                            <td><input type="number" step="0.01" name="carcase2[]" class="form-control form-control-sm text-right" max="300"></td>
                                            <td><input type="number" step="0.01" name="hides[]" class="form-control form-control-sm text-right" max="70"></td>
                                            <td><input type="number" step="0.01" name="tails[]" class="form-control form-control-sm text-right" max="150"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-4">
                                <a href="draft.php" class="btn btn-secondary btn-block btn-sm">Batal</a>
                            </div>
                            <div class="col-md-8">
                                <button type="submit" class="btn btn-success btn-block btn-sm">
                                    <i class="fas fa-save"></i> Simpan Carcas
                                </button>
                            </div>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </section>
</div>

<script>
    document.title = "Input Carcas";

    function enableEnterDown(selector) {
        const inputs = Array.from(document.querySelectorAll(selector));

        inputs.forEach((input, index) => {
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();

                    // cari input berikutnya dengan name yang sama
                    const name = this.getAttribute('name');
                    const sameColumn = inputs.filter(i => i.getAttribute('name') === name);

                    const pos = sameColumn.indexOf(this);
                    if (sameColumn[pos + 1]) {
                        sameColumn[pos + 1].focus();
                        sameColumn[pos + 1].select();
                    }
                }
            });
        });
    }

    // aktifkan ENTER turun per kolom
    enableEnterDown('input[name="carcase1[]"]');
    enableEnterDown('input[name="carcase2[]"]');
    enableEnterDown('input[name="hides[]"]');
    enableEnterDown('input[name="tails[]"]');
</script>


<?php include "../footer.php"; ?>