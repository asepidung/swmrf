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
if (!function_exists('tglinput')) {
    function tglinput($d)
    {
        return $d ? date('Y-m-d', strtotime($d)) : '';
    }
}

/* ================================
 * Validasi ID
 * ================================ */
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("Invalid carcase id.");
}
$idcarcase = (int)$_GET['id'];

/* ================================
 * Ambil HEADER carcase
 * ================================ */
$stmtH = $conn->prepare("
    SELECT 
        c.idcarcase,
        c.killdate,
        c.note,
        c.idweight,
        c.idsupplier,
        s.nmsupplier,
        w.weigh_no,
        w.weigh_date
    FROM carcase c
    JOIN supplier s      ON s.idsupplier = c.idsupplier
    JOIN weight_cattle w ON w.idweigh    = c.idweight
    WHERE c.idcarcase = ? AND c.is_deleted = 0
    LIMIT 1
");
$stmtH->bind_param("i", $idcarcase);
$stmtH->execute();
$header = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$header) {
    die("Carcas tidak ditemukan.");
}

$idweight = (int)$header['idweight'];

/* ================================
 * Ambil sapi:
 *  - milik carcase ini
 *  - ATAU belum dipakai carcase lain
 * ================================ */
$stmtD = $conn->prepare("
    SELECT
        d.idweighdetail,
        d.eartag,
        crd.weight AS live_weight,

        cd_this.iddetail,
        cd_this.breed,
        cd_this.carcase1,
        cd_this.carcase2,
        cd_this.hides,
        cd_this.tail

    FROM weight_cattle_detail d
    JOIN cattle_receive_detail crd
        ON crd.idreceivedetail = d.idreceivedetail

    /* carcasedetail untuk carcase yang sedang diedit */
    LEFT JOIN carcasedetail cd_this
        ON cd_this.idweightdetail = d.idweighdetail
       AND cd_this.idcarcase = ?

    /* cek apakah sapi sudah dipakai carcase LAIN */
    LEFT JOIN carcasedetail cd_other
        ON cd_other.idweightdetail = d.idweighdetail
       AND cd_other.idcarcase <> ?

    WHERE d.idweigh = ?
      AND (
            cd_this.iddetail IS NOT NULL
         OR cd_other.iddetail IS NULL
      )

    ORDER BY d.eartag
");
$stmtD->bind_param("iii", $idcarcase, $idcarcase, $idweight);
$stmtD->execute();
$resD = $stmtD->get_result();

$rows = [];
while ($r = $resD->fetch_assoc()) {
    $rows[] = $r;
}
$stmtD->close();

$breedOptions = ['STEER', 'HEIFER', 'COW', 'BULL', 'PREMIUM', 'NONPREMIUM'];
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Edit Carcas</h1>
            <small class="text-muted">
                Supplier: <?= e($header['nmsupplier']); ?> |
                No Timbang: <?= e($header['weigh_no']); ?>
            </small>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">

                    <form action="update.php" method="post">
                        <input type="hidden" name="idcarcase" value="<?= $idcarcase; ?>">

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label>Tanggal Killing</label>
                                <input type="date" name="killdate" class="form-control form-control-sm"
                                    value="<?= e(tglinput($header['killdate'])); ?>" required>
                            </div>
                            <div class="col-md-9">
                                <label>Catatan</label>
                                <input type="text" name="note" class="form-control form-control-sm"
                                    value="<?= e($header['note']); ?>">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>No</th>
                                        <th>Eartag</th>
                                        <th>Class</th>
                                        <th>Live Wt</th>
                                        <th>Carcase A</th>
                                        <th>Carcase B</th>
                                        <th>Hides</th>
                                        <th>Tail</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php $no = 1;
                                    foreach ($rows as $r): ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>

                                            <td class="text-center">
                                                <?= e($r['eartag']); ?>
                                                <input type="hidden" name="idweighdetail[]" value="<?= (int)$r['idweighdetail']; ?>">
                                                <input type="hidden" name="iddetail[]" value="<?= (int)($r['iddetail'] ?? 0); ?>">
                                                <input type="hidden" name="eartag[]" value="<?= e($r['eartag']); ?>">
                                                <input type="hidden" name="live_weight[]" value="<?= e($r['live_weight']); ?>">
                                            </td>

                                            <td>
                                                <select name="breed[]" class="form-control form-control-sm">
                                                    <option value="">- Pilih -</option>
                                                    <?php foreach ($breedOptions as $opt): ?>
                                                        <option value="<?= $opt; ?>" <?= ($opt === $r['breed']) ? 'selected' : ''; ?>>
                                                            <?= $opt; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>

                                            <td class="text-right">
                                                <?= number_format((float)$r['live_weight'], 2, ',', '.'); ?>
                                            </td>

                                            <td><input type="number" step="0.01" name="carcase1[]" class="form-control form-control-sm text-right"
                                                    value="<?= e($r['carcase1']); ?>"></td>

                                            <td><input type="number" step="0.01" name="carcase2[]" class="form-control form-control-sm text-right"
                                                    value="<?= e($r['carcase2']); ?>"></td>

                                            <td><input type="number" step="0.01" name="hides[]" class="form-control form-control-sm text-right"
                                                    value="<?= e($r['hides']); ?>"></td>

                                            <td><input type="number" step="0.01" name="tails[]" class="form-control form-control-sm text-right"
                                                    value="<?= e($r['tail']); ?>"></td>
                                        </tr>
                                    <?php endforeach; ?>

                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-success btn-sm btn-block">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>

                    </form>

                </div>
            </div>
        </div>
    </section>
</div>

<?php include "../footer.php"; ?>