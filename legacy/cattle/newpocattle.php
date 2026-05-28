<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function flash($type, $msg)
{
    echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>" .
        e($msg) .
        "<button type='button' class='close' data-dismiss='alert'>
            <span aria-hidden='true'>&times;</span>
         </button>
        </div>";
}

/* ==========================
   SUPPLIER
========================== */
$suppliers = [];
$q = $conn->query("SELECT idsupplier, nmsupplier FROM supplier ORDER BY nmsupplier ASC");
if ($q) while ($row = $q->fetch_assoc()) $suppliers[] = $row;

/* ==========================
   CATTLE CLASS (DB)
========================== */
$cattleClasses = [];
$qc = $conn->query("SELECT class_name FROM cattle_class ORDER BY class_name ASC");
if ($qc) while ($r = $qc->fetch_assoc()) $cattleClasses[] = $r['class_name'];

/* ==========================
   OLD INPUT
========================== */
$old  = $_SESSION['form_old'] ?? [];
$errs = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['form_errors']);
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>New PO Cattle</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo-alt"></i> Kembali
                    </a>
                </div>
            </div>

            <?php if (!empty($errs)) foreach ($errs as $er) flash('danger', $er); ?>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <form method="post" action="create.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <!-- HEADER -->
                <div class="card">
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>PO Date</label>
                                <input type="date" name="podate" class="form-control"
                                    value="<?= e($old['podate'] ?? date('Y-m-d')) ?>" readonly>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Arrival Date (Plan)</label>
                                <input type="date" name="arrival_date" class="form-control" required
                                    value="<?= e($old['arrival_date'] ?? '') ?>">
                            </div>

                            <div class="form-group col-md-4">
                                <label>Supplier</label>
                                <select name="idsupplier" class="form-control" required>
                                    <option value="">-- pilih supplier --</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= (int)$s['idsupplier'] ?>"
                                            <?= ((int)($old['idsupplier'] ?? 0) === (int)$s['idsupplier']) ? 'selected' : '' ?>>
                                            <?= e($s['nmsupplier']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <input type="text" name="note" class="form-control"
                            placeholder="Catatan (opsional)"
                            value="<?= e($old['note'] ?? '') ?>">
                    </div>
                </div>

                <!-- DETAIL -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Detail (Cattle Class)</h3>
                        <button type="button" id="btnAddRow" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Tambah Baris
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light text-center">
                                    <tr>
                                        <th style="width:40px;">#</th>
                                        <th>Cattle Class</th>
                                        <th style="width:120px;">Qty</th>
                                        <th style="width:150px;">Price / Kg</th>
                                        <th>Notes</th>
                                        <th style="width:60px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="detailBody">
                                    <tr>
                                        <td class="text-center rownum"></td>
                                        <td>
                                            <select name="class[]" class="form-control form-control-sm class-select">
                                                <option value="">-- pilih --</option>
                                                <option value="__NEW__">➕ Tambah Class Baru</option>
                                                <?php foreach ($cattleClasses as $cc): ?>
                                                    <option value="<?= e($cc) ?>"><?= e($cc) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="qty[]" min="1" class="form-control form-control-sm text-right"></td>
                                        <td><input type="text" name="price[]" class="form-control form-control-sm text-right"></td>
                                        <td><input type="text" name="notes[]" class="form-control form-control-sm"></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-danger btn-sm btnDel">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-right">Total Qty</th>
                                        <th class="text-right" id="totalQty">0</th>
                                        <th colspan="3"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<!-- MODAL TAMBAH CLASS -->
<div class="modal fade" id="modalAddClass" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Cattle Class</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" id="newClassName" class="form-control" placeholder="Contoh: NONPREMIUM">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button class="btn btn-primary" id="btnSaveClass">Simpan</button>
            </div>
        </div>
    </div>
</div>

<?php include "../footer.php"; ?>

<script>
    document.title = "New Cattle PO";
    /* ======================
   UTIL
====================== */
    function renumber() {
        let total = 0;
        document.querySelectorAll('#detailBody tr').forEach((tr, i) => {
            tr.querySelector('.rownum').textContent = i + 1;
            const q = parseInt(tr.querySelector('input[name="qty[]"]').value || 0);
            if (!isNaN(q)) total += q;
        });
        document.getElementById('totalQty').textContent =
            new Intl.NumberFormat('id-ID').format(total);
    }

    /* ======================
       ADD ROW
    ====================== */
    document.getElementById('btnAddRow').addEventListener('click', () => {
        const tr = document.querySelector('#detailBody tr').cloneNode(true);
        tr.querySelectorAll('input').forEach(i => i.value = '');
        tr.querySelector('select').selectedIndex = 0;
        document.getElementById('detailBody').appendChild(tr);
        renumber();
    });

    /* ======================
       DELETE ROW
    ====================== */
    document.getElementById('detailBody').addEventListener('click', e => {
        if (e.target.closest('.btnDel')) {
            if (document.querySelectorAll('#detailBody tr').length <= 1) {
                alert('Minimal satu baris');
                return;
            }
            e.target.closest('tr').remove();
            renumber();
        }
    });

    /* ======================
       EVENT DELEGATION CLASS
    ====================== */
    document.getElementById('detailBody').addEventListener('change', e => {
        const sel = e.target;
        if (!sel.classList.contains('class-select')) return;

        if (sel.value === '__NEW__') {
            sel.value = '';
            document.getElementById('newClassName').value = '';
            $('#modalAddClass').modal('show');
        }
    });

    /* ======================
       SAVE CLASS (AJAX)
    ====================== */
    document.getElementById('btnSaveClass').addEventListener('click', async () => {
        const name = document.getElementById('newClassName').value.trim();
        if (!name) return alert('Nama class wajib');

        const fd = new FormData();
        fd.append('class_name', name);

        const res = await fetch('ajax_add_class.php', {
            method: 'POST',
            body: fd
        });
        const json = await res.json();
        if (!json.ok) return alert(json.msg || 'Gagal');

        // tambahkan ke semua dropdown
        document.querySelectorAll('.class-select').forEach(sel => {
            const opt = document.createElement('option');
            opt.value = json.class_name;
            opt.textContent = json.class_name;
            sel.appendChild(opt);
        });

        $('#modalAddClass').modal('hide');
    });

    renumber();
</script>