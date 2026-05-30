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
    return $d ? date('Y-m-d', strtotime($d)) : '';
}
function tglv($d)
{
    return $d ? date('d-M-Y', strtotime($d)) : '-';
}

// idreceive
if (empty($_GET['id']) || !ctype_digit($_GET['id'])) die("Invalid receive id.");
$idreceive = (int)$_GET['id'];

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

// ================= HEADER =================
$stmtH = $conn->prepare("
  SELECT r.idreceive, r.idpo, r.receipt_date, r.doc_no, r.sv_ok, r.skkh_ok, r.note,
         p.nopo, p.podate, p.arrival_date, s.nmsupplier
  FROM cattle_receive r
  JOIN pocattle p ON p.idpo = r.idpo
  JOIN supplier s ON s.idsupplier = p.idsupplier
  WHERE r.idreceive=? AND r.is_deleted=0
  LIMIT 1
");
$stmtH->bind_param("i", $idreceive);
$stmtH->execute();
$rcv = $stmtH->get_result()->fetch_assoc();
if (!$rcv) die("Receive data not found.");

// ================= DETAIL DB =================
$rowsDB = [];
$stmtD = $conn->prepare("
  SELECT class, eartag, weight, notes
  FROM cattle_receive_detail
  WHERE idreceive=?
  ORDER BY idreceivedetail
");
$stmtD->bind_param("i", $idreceive);
$stmtD->execute();
$resD = $stmtD->get_result();
while ($r = $resD->fetch_assoc()) {
    $rowsDB[] = $r;
}

// ================= FLASH =================
$errs = $_SESSION['form_errors'] ?? [];
$old  = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

// old rows (jika error update)
$old_rows = [];
if (!empty($old['class'])) {
    $cnt = count($old['class']);
    for ($i = 0; $i < $cnt; $i++) {
        $old_rows[] = [
            'class'  => $old['class'][$i]  ?? '',
            'eartag' => $old['eartag'][$i] ?? '',
            'weight' => $old['weight'][$i] ?? '',
            'notes'  => $old['notes'][$i]  ?? '',
        ];
    }
}

// ================= MASTER CLASS =================
$classes = [];
$qClass = mysqli_query($conn, "SELECT class_name FROM cattle_class ORDER BY idclass ASC");
while ($r = mysqli_fetch_assoc($qClass)) {
    $classes[] = $r['class_name'];
}

// data ditampilkan
$rows = $old_rows ?: $rowsDB;
if (empty($rows)) {
    $rows[] = ['class' => '', 'eartag' => '', 'weight' => '', 'notes' => ''];
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Edit Receive <small class="text-muted">(PO: <?= e($rcv['nopo']) ?>)</small></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="view.php?id=<?= (int)$idreceive ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali
                    </a>
                </div>
            </div>

            <?php foreach ($errs as $er): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= e($er) ?><button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- FORM -->
            <form method="post" action="update.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="idreceive" value="<?= (int)$idreceive ?>">
                <input type="hidden" name="idpo" value="<?= (int)$rcv['idpo'] ?>">

                <!-- HEADER RECEIVE -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Header Receive</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label>Receipt Date</label>
                                <input type="date" name="receipt_date" class="form-control" required
                                    value="<?= e($old['receipt_date'] ?? tgl($rcv['receipt_date'])) ?>">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Doc No</label>
                                <input type="text" name="doc_no" class="form-control" maxlength="50"
                                    value="<?= e($old['doc_no'] ?? $rcv['doc_no']) ?>">
                            </div>

                            <input type="hidden" name="sv_ok" value="0">
                            <div class="form-group col-md-1">
                                <label>SV</label>
                                <input type="checkbox" name="sv_ok" value="1" <?= ($rcv['sv_ok'] ? 'checked' : '') ?>>
                            </div>

                            <input type="hidden" name="skkh_ok" value="0">
                            <div class="form-group col-md-1">
                                <label>SKKH</label>
                                <input type="checkbox" name="skkh_ok" value="1" <?= ($rcv['skkh_ok'] ? 'checked' : '') ?>>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Note</label>
                                <input type="text" name="note" class="form-control" maxlength="255"
                                    value="<?= e($old['note'] ?? $rcv['note']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DETAIL -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h3 class="card-title">Detail (Per Ekor)</h3>
                        <button type="button" id="btnAddRow" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Tambah Baris
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Class</th>
                                        <th>Eartag</th>
                                        <th>Weight</th>
                                        <th>Notes</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="detailBody">
                                    <?php foreach ($rows as $rw): ?>
                                        <tr>
                                            <td class="text-center rownum"></td>
                                            <td>
                                                <select name="class[]" class="form-control form-control-sm" required>
                                                    <option value="">-- pilih --</option>
                                                    <?php foreach ($classes as $c): ?>
                                                        <option value="<?= e($c) ?>" <?= ($rw['class'] === $c ? 'selected' : '') ?>>
                                                            <?= e($c) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="text" name="eartag[]" class="form-control form-control-sm"
                                                    value="<?= e($rw['eartag']) ?>" required></td>
                                            <td><input type="number" name="weight[]" class="form-control form-control-sm text-right"
                                                    min="0" value="<?= e($rw['weight']) ?>" required></td>
                                            <td><input type="text" name="notes[]" class="form-control form-control-sm"
                                                    value="<?= e($rw['notes']) ?>"></td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-sm btnDel">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-right">Total Head</th>
                                        <th id="totalHead" class="text-right">0</th>
                                        <th id="totalWeight" class="text-right">0,00</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Update Receive
                        </button>
                        <a href="view.php?id=<?= (int)$idreceive ?>" class="btn btn-secondary">Batal</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<?php include "../footer.php"; ?>

<script>
    function renumber() {
        let head = 0,
            w = 0;
        document.querySelectorAll('#detailBody tr').forEach((tr, i) => {
            tr.querySelector('.rownum').textContent = i + 1;
            const wt = parseFloat(tr.querySelector('input[name="weight[]"]').value || 0);
            if (wt > 0) {
                head++;
                w += wt;
            }
        });
        document.getElementById('totalHead').textContent = head;
        document.getElementById('totalWeight').textContent = w.toFixed(2);
    }

    function addRow() {
        const body = document.getElementById('detailBody');
        const newRow = body.querySelector('tr').cloneNode(true);
        newRow.querySelectorAll('input').forEach(i => i.value = '');
        newRow.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
        body.appendChild(newRow);
        attachLiveCheck();
        renumber();
    }

    document.getElementById('btnAddRow').addEventListener('click', addRow);
    document.getElementById('detailBody').addEventListener('click', e => {
        if (e.target.closest('.btnDel')) {
            if (document.querySelectorAll('#detailBody tr').length <= 1) {
                alert('Minimal satu baris.');
                return;
            }
            e.target.closest('tr').remove();
            markDuplicateInForm();
            renumber();
        }
    });
    document.getElementById('detailBody').addEventListener('input', e => {
        if (e.target.name === 'weight[]' || e.target.name === 'eartag[]') {
            renumber();
            if (e.target.name === 'eartag[]') markDuplicateInForm();
        }
    });
    renumber();
</script>