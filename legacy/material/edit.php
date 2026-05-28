<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idstockout = (int)($_GET['id'] ?? 0);
if ($idstockout <= 0) {
    die("ID dokumen tidak valid.");
}

/* ================================
   Ambil HEADER
================================ */
$stmtH = $conn->prepare("
    SELECT tgl, kegiatan, ref_no, kegiatan_note
    FROM raw_stock_out
    WHERE idstockout = ? AND is_deleted = 0
    LIMIT 1
");
$stmtH->bind_param("i", $idstockout);
$stmtH->execute();
$header = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$header) {
    die("Dokumen tidak ditemukan atau sudah dihapus.");
}

/* ================================
   Ambil DETAIL YANG SUDAH ADA
   key = idrawmate
================================ */
$detail = [];
$stmtD = $conn->prepare("
    SELECT idrawmate, qty, note
    FROM raw_stock_out_detail
    WHERE idstockout = ?
");
$stmtD->bind_param("i", $idstockout);
$stmtD->execute();
$resD = $stmtD->get_result();
while ($r = $resD->fetch_assoc()) {
    $detail[(int)$r['idrawmate']] = $r;
}
$stmtD->close();

/* ================================
   Ambil SEMUA material stock=1
================================ */
$materials = [];
$qraw = mysqli_query($conn, "
    SELECT idrawmate, nmrawmate, unit
    FROM rawmate
    WHERE stock = 1
    ORDER BY nmrawmate ASC
");
while ($r = mysqli_fetch_assoc($qraw)) {
    $materials[] = $r;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h4><i class="fas fa-edit"></i> Edit Pengeluaran Material</h4>
                    <div class="small text-muted">
                        Perbaiki qty jika ada kesalahan atau penambahan material.
                    </div>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <form method="post" action="update.php">
                <input type="hidden" name="idstockout" value="<?= (int)$idstockout ?>">

                <div class="card card-dark shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title">Header Dokumen</h3>
                    </div>

                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Tanggal</label>
                                <input type="date" name="tgl" class="form-control"
                                    value="<?= htmlspecialchars($header['tgl']) ?>" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Kegiatan</label>
                                <select name="kegiatan" id="kegiatan" class="form-control" required>
                                    <option value="">-- pilih kegiatan --</option>
                                    <option value="BONING" <?= $header['kegiatan'] == 'BONING' ? 'selected' : '' ?>>BONING</option>
                                    <option value="REPACK" <?= $header['kegiatan'] == 'REPACK' ? 'selected' : '' ?>>REPACK</option>
                                    <option value="LAINNYA" <?= $header['kegiatan'] == 'LAINNYA' ? 'selected' : '' ?>>LAINNYA</option>
                                </select>
                            </div>

                            <div class="form-group col-md-4 d-none" id="wrapRefNo">
                                <label id="labelRefNo">Referensi</label>
                                <input type="text" name="ref_no" id="ref_no" class="form-control"
                                    value="<?= htmlspecialchars($header['ref_no'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row d-none" id="wrapKegiatanNote">
                            <div class="form-group col-md-12">
                                <label>Kegiatan Lainnya (WAJIB)</label>
                                <input type="text" name="kegiatan_note" id="kegiatan_note"
                                    class="form-control"
                                    value="<?= htmlspecialchars($header['kegiatan_note'] ?? '') ?>">
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-2">Detail Material</h6>

                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>Material</th>
                                        <th width="15%">Qty</th>
                                        <th width="10%">Unit</th>
                                        <th width="25%">Catatan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    foreach ($materials as $m):
                                        $idr = (int)$m['idrawmate'];
                                        $qty = $detail[$idr]['qty'] ?? '';
                                        $note = $detail[$idr]['note'] ?? '';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no++ ?></td>
                                            <td>
                                                <?= htmlspecialchars($m['nmrawmate']) ?>
                                                <input type="hidden" name="idrawmate[]" value="<?= $idr ?>">
                                            </td>
                                            <td>
                                                <input type="number" name="qty[]"
                                                    class="form-control form-control-sm text-right"
                                                    step="0.01" min="0"
                                                    value="<?= htmlspecialchars($qty) ?>">
                                            </td>
                                            <td class="text-center">
                                                <?= htmlspecialchars($m['unit'] ?? '') ?>
                                            </td>
                                            <td>
                                                <input type="text" name="row_note[]"
                                                    class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($note) ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {

        const kegiatan = document.getElementById('kegiatan');
        const wrapRef = document.getElementById('wrapRefNo');
        const wrapNote = document.getElementById('wrapKegiatanNote');
        const labelRef = document.getElementById('labelRefNo');
        const refNo = document.getElementById('ref_no');
        const kegNote = document.getElementById('kegiatan_note');

        function toggleKegiatan(val) {
            wrapRef.classList.add('d-none');
            wrapNote.classList.add('d-none');
            refNo.required = false;
            if (kegNote) kegNote.required = false;

            if (val === 'BONING') {
                labelRef.innerText = 'No Boning (WAJIB)';
                wrapRef.classList.remove('d-none');
                refNo.required = true;
            } else if (val === 'REPACK') {
                labelRef.innerText = 'No Repack (WAJIB)';
                wrapRef.classList.remove('d-none');
                refNo.required = true;
            } else if (val === 'LAINNYA') {
                wrapNote.classList.remove('d-none');
                if (kegNote) kegNote.required = true;
            }
        }

        toggleKegiatan(kegiatan.value);
        kegiatan.addEventListener('change', e => toggleKegiatan(e.target.value));

        /* ENTER di qty → pindah ke bawah */
        const qtyInputs = document.querySelectorAll('input[name="qty[]"]');
        qtyInputs.forEach((input, index) => {
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const next = qtyInputs[index + 1];
                    if (next) {
                        next.focus();
                        next.select();
                    }
                }
            });
        });

        document.title = "Edit Pengeluaran Material";
    });
</script>

<?php include "../footnote.php"; ?>
<?php include "../footer.php"; ?>