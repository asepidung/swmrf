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

// Validasi & ambil idpo
if (empty($_GET['idpo']) || !ctype_digit($_GET['idpo'])) die("PO tidak valid.");
$idpo = (int)$_GET['idpo'];

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

// Cek: sudah pernah di-receive aktif?
$cek = $conn->prepare("SELECT idreceive FROM cattle_receive WHERE idpo=? AND is_deleted=0 LIMIT 1");
$cek->bind_param("i", $idpo);
$cek->execute();
if ($cek->get_result()->fetch_row()) {
    header("Location: view.php?idpo=" . $idpo);
    exit;
}

// Ambil info PO + supplier + ringkasan qty
$stmt = $conn->prepare("
  SELECT p.idpo, p.nopo, p.podate, p.arrival_date, s.nmsupplier,
         COALESCE(SUM(d.qty),0) AS heads
  FROM pocattle p
  JOIN supplier s ON s.idsupplier = p.idsupplier
  LEFT JOIN pocattledetail d ON d.idpo=p.idpo AND d.is_deleted=0
  WHERE p.idpo=? AND p.is_deleted=0
  GROUP BY p.idpo, p.nopo, p.podate, p.arrival_date, s.nmsupplier
  LIMIT 1
");
$stmt->bind_param("i", $idpo);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();
if (!$po) die("PO tidak ditemukan.");

// flash (jika store.php mengembalikan error)
$errs = $_SESSION['form_errors'] ?? [];
$old  = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_old']);

// data lama detail (jika ada error dari store.php)
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
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Create Receive <small class="text-muted">(PO: <?= e($po['nopo']) ?>)</small></h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="draft.php" class="btn btn-secondary btn-sm"><i class="fas fa-undo-alt"></i> Kembali</a>
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

            <!-- Ringkasan PO -->
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-2">PO Number</dt>
                        <dd class="col-sm-4"><?= e($po['nopo']) ?></dd>
                        <dt class="col-sm-2">Supplier</dt>
                        <dd class="col-sm-4"><?= e($po['nmsupplier']) ?></dd>

                        <dt class="col-sm-2">PO Date</dt>
                        <dd class="col-sm-4"><?= tgl($po['podate']) ?></dd>
                        <dt class="col-sm-2">Plan Arrival</dt>
                        <dd class="col-sm-4"><?= tgl($po['arrival_date']) ?></dd>

                        <dt class="col-sm-2">Cattle Qty</dt>
                        <dd class="col-sm-4"><?= number_format((int)$po['heads'], 0, ',', '.') ?> head</dd>
                    </dl>
                </div>
            </div>

            <!-- Form Create Receive -->
            <form method="post" action="store.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="idpo" value="<?= (int)$po['idpo'] ?>">

                <!-- Header Receive (1 baris: 3-3-1-1-4) -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Header Receive</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3">
                                <label>Receipt Date</label>
                                <input type="date" name="receipt_date" class="form-control" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Doc No (Surat Jalan)</label>
                                <input type="text" name="doc_no" class="form-control" maxlength="50"
                                    value="<?= e($old['doc_no'] ?? '') ?>">
                            </div>

                            <!-- Fallback hidden agar OFF -> 0 -->
                            <input type="hidden" name="sv_ok" value="0">
                            <div class="form-group col-md-1">
                                <label>SV</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="sv_ok" name="sv_ok" value="1"
                                        <?= ((string)($old['sv_ok'] ?? '0') === '1' ? 'checked' : '') ?>>
                                    <label class="custom-control-label" for="sv_ok">Y/T</label>
                                </div>
                            </div>

                            <input type="hidden" name="skkh_ok" value="0">
                            <div class="form-group col-md-1">
                                <label>SKKH</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="skkh_ok" name="skkh_ok" value="1"
                                        <?= ((string)($old['skkh_ok'] ?? '0') === '1' ? 'checked' : '') ?>>
                                    <label class="custom-control-label" for="skkh_ok">Y/T</label>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Note</label>
                                <input type="text" name="note" class="form-control" maxlength="255"
                                    value="<?= e($old['note'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DETAIL PER EKOR (tanpa RFID) -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
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
                                        <th>Weight (Kg)</th>
                                        <th>Notes</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="detailBody">
                                    <?php
                                    // ambil class dari tabel cattle_class
                                    $classes = [];
                                    $qClass = mysqli_query($conn, "SELECT class_name FROM cattle_class ORDER BY idclass ASC");
                                    while ($r = mysqli_fetch_assoc($qClass)) {
                                        $classes[] = $r['class_name'];
                                    }

                                    $rows = $old_rows;
                                    if (empty($rows)) {
                                        $rows[] = ['class' => '', 'eartag' => '', 'weight' => '', 'notes' => ''];
                                    }

                                    foreach ($rows as $rw): ?>
                                        <tr>
                                            <td class="text-center rownum"></td>
                                            <td>
                                                <select name="class[]" class="form-control form-control-sm" required>
                                                    <option value="">-- pilih --</option>
                                                    <?php foreach ($classes as $c): ?>
                                                        <option value="<?= e($c) ?>"
                                                            <?= (strtoupper($rw['class']) === strtoupper($c) ? 'selected' : '') ?>>
                                                            <?= e($c) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="eartag[]" class="form-control form-control-sm"
                                                    maxlength="50" value="<?= e($rw['eartag']) ?>" required>
                                            </td>
                                            <td>
                                                <input type="number" name="weight[]" class="form-control form-control-sm text-right"
                                                    min="0" value="<?= e($rw['weight']) ?>" required>
                                            </td>
                                            <td>
                                                <input type="text" name="notes[]" class="form-control form-control-sm"
                                                    value="<?= e($rw['notes']) ?>">
                                            </td>
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
                                        <th class="text-right" id="totalHead">0</th>
                                        <th class="text-right" id="totalWeight">0,00</th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Simpan Receive
                        </button>
                        <a href="draft.php" class="btn btn-secondary">Batal</a>
                    </div>
                </div>
            </form>

        </div>
    </section>
</div>


<script>
    document.title = "Input Receive";
</script>

<?php include "../footer.php"; ?>

<script>
    // ======= numbering & total =======
    function renumber() {
        const rows = document.querySelectorAll('#detailBody tr');
        let head = 0,
            w = 0;
        rows.forEach((tr, i) => {
            const c = tr.querySelector('.rownum');
            if (c) c.textContent = i + 1;
            const weight = tr.querySelector('input[name="weight[]"]');
            const wt = parseFloat(weight && weight.value ? weight.value : '0');
            if (!isNaN(wt) && wt > 0) {
                head += 1;
                w += wt;
            }
        });
        document.getElementById('totalHead').textContent = new Intl.NumberFormat('id-ID').format(head);
        document.getElementById('totalWeight').textContent = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(w);
    }


    function addRow() {
        const body = document.getElementById('detailBody');
        const firstRow = body.querySelector('tr');
        const newRow = firstRow.cloneNode(true);

        // reset value input
        newRow.querySelectorAll('input').forEach(inp => inp.value = '');
        newRow.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);

        body.appendChild(newRow);
        attachLiveCheck();
        renumber();
    }

    document.getElementById('btnAddRow').addEventListener('click', addRow);
    document.getElementById('detailBody').addEventListener('click', function(e) {
        if (e.target.closest('.btnDel')) {
            const rows = document.querySelectorAll('#detailBody tr');
            if (rows.length <= 1) {
                alert('Minimal satu baris.');
                return;
            }
            e.target.closest('tr').remove();
            markDuplicateInForm();
            renumber();
        }
    });
    document.getElementById('detailBody').addEventListener('input', function(e) {
        if (e.target.name === 'weight[]' || e.target.name === 'eartag[]') {
            renumber();
            if (e.target.name === 'eartag[]') markDuplicateInForm();
        }
    });
    renumber();
</script>

<script>
    // ======= Live check eartag aktif + duplikat dalam form =======

    // util
    function normalizeTag(v) {
        return (v || '').trim().toUpperCase();
    }

    // cache hasil cek biar hemat request
    const tagCache = new Map(); // key: eartag, val: {active:boolean, ts:number}
    const DEBOUNCE_MS = 300;

    function setInputState(input, {
        busy = false,
        error = false,
        msg = ''
    }) {
        input.classList.remove('is-invalid', 'is-valid');
        if (busy) {
            input.dataset.busy = '1';
        } else {
            delete input.dataset.busy;
        }
        if (error) {
            input.classList.add('is-invalid');
            input.title = msg || 'Eartag sudah aktif di sistem.';
        } else if (input.value.trim() !== '') {
            input.classList.add('is-valid');
            input.title = '';
        } else {
            input.title = '';
        }
    }

    async function checkTagActive(tag) {
        if (!tag) return {
            active: false
        };
        const cached = tagCache.get(tag);
        const now = Date.now();
        if (cached && (now - cached.ts) < 10000) return {
            active: cached.active
        };

        const url = `ajax_check_eartag.php?eartag=${encodeURIComponent(tag)}`;
        try {
            const res = await fetch(url, {
                credentials: 'same-origin'
            });
            const json = await res.json();
            const active = !!(json && json.active);
            tagCache.set(tag, {
                active,
                ts: now
            });
            return {
                active
            };
        } catch (e) {
            return {
                active: false
            };
        }
    }

    // duplikat di dalam form
    function markDuplicateInForm() {
        const inputs = Array.from(document.querySelectorAll('input[name="eartag[]"]'));
        const seen = {};
        inputs.forEach(inp => {
            inp.classList.remove('is-invalid');
            inp.title = '';
        });
        inputs.forEach(inp => {
            const tag = normalizeTag(inp.value);
            if (!tag) return;
            if (seen[tag]) {
                seen[tag].classList.add('is-invalid');
                seen[tag].title = 'Eartag duplikat di form.';
                inp.classList.add('is-invalid');
                inp.title = 'Eartag duplikat di form.';
            } else {
                seen[tag] = inp;
            }
        });
    }

    // debouncer per-input
    const debouncers = new WeakMap();

    function debounceInput(el, fn) {
        if (debouncers.has(el)) clearTimeout(debouncers.get(el));
        const t = setTimeout(fn, DEBOUNCE_MS);
        debouncers.set(el, t);
    }

    // attach ke semua input eartag
    function attachLiveCheck() {
        document.querySelectorAll('input[name="eartag[]"]').forEach((inp) => {
            // normalisasi saat blur + cek aktif
            inp.addEventListener('blur', async () => {
                const tag = normalizeTag(inp.value);
                inp.value = tag;
                markDuplicateInForm();
                if (!tag) {
                    setInputState(inp, {
                        error: false
                    });
                    return;
                }
                setInputState(inp, {
                    busy: true
                });
                const {
                    active
                } = await checkTagActive(tag);
                setInputState(inp, {
                    busy: false,
                    error: active,
                    msg: 'Eartag sudah aktif di sistem.'
                });
            });
            // debounce saat ketik
            inp.addEventListener('input', () => {
                debounceInput(inp, async () => {
                    const tag = normalizeTag(inp.value);
                    markDuplicateInForm();
                    if (!tag) {
                        setInputState(inp, {
                            error: false
                        });
                        return;
                    }
                    const {
                        active
                    } = await checkTagActive(tag);
                    setInputState(inp, {
                        busy: false,
                        error: active,
                        msg: 'Eartag sudah aktif di sistem.'
                    });
                });
            });
        });
    }
    attachLiveCheck();

    // blokir submit kalau ada masalah
    document.querySelector('form[action="store.php"]').addEventListener('submit', function(e) {
        markDuplicateInForm();
        let ok = true;
        const inputs = Array.from(document.querySelectorAll('input[name="eartag[]"]'));
        for (const inp of inputs) {
            const tag = normalizeTag(inp.value);
            inp.value = tag;
            if (!tag || inp.classList.contains('is-invalid')) {
                ok = false;
                break;
            }
        }
        if (!ok) {
            e.preventDefault();
            alert('Periksa eartag: tidak boleh kosong, duplikat di form, atau sudah aktif di sistem.');
        }
    });
</script>