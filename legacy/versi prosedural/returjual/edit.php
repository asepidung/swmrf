<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idusers = $_SESSION['idusers'] ?? 0;
$idreturjual = intval($_GET['idreturjual'] ?? 0);

if ($idreturjual <= 0) {
    die("ID retur tidak valid");
}

// ambil data retur
$q = mysqli_query($conn, "
    SELECT *
    FROM returjual
    WHERE idreturjual = $idreturjual
      AND is_deleted = 0
");

$data = mysqli_fetch_assoc($q);
if (!$data) {
    die("Data retur tidak ditemukan");
}
?>
<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mt-3">
                    <form method="POST" action="update.php">
                        <input type="hidden" name="idreturjual" value="<?= $idreturjual ?>">
                        <input type="hidden" name="idusers" value="<?= $idusers ?>">

                        <div class="card">
                            <div class="card-body">

                                <!-- CUSTOMER -->
                                <div class="form-group">
                                    <label>Customer <span class="text-danger">*</span></label>
                                    <select class="form-control" name="idcustomer" id="idcustomer" required>
                                        <option value="">-- Pilih Customer --</option>
                                        <?php
                                        $qc = mysqli_query($conn, "SELECT idcustomer,nama_customer FROM customers ORDER BY nama_customer ASC");
                                        while ($c = mysqli_fetch_assoc($qc)) {
                                            $selected = ($c['idcustomer'] == $data['idcustomer']) ? 'selected' : '';
                                            echo "<option value='{$c['idcustomer']}' $selected>{$c['nama_customer']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- DO NUMBER -->
                                <div class="form-group">
                                    <label>No DO</label>
                                    <select class="form-control" name="donumber" id="donumber">
                                        <option value="">Unidentified</option>
                                        <?php if (!empty($data['donumber'])): ?>
                                            <option value="<?= htmlspecialchars($data['donumber']) ?>" selected>
                                                <?= htmlspecialchars($data['donumber']) ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted">Kosongkan jika tidak diketahui</small>
                                </div>

                                <!-- RETUR DATE -->
                                <div class="form-group">
                                    <label>Tanggal Retur <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control"
                                        name="returdate"
                                        required
                                        value="<?= htmlspecialchars($data['returdate']) ?>">
                                </div>

                                <!-- NOTE -->
                                <div class="form-group">
                                    <label>Catatan</label>
                                    <input type="text" class="form-control"
                                        name="note"
                                        value="<?= htmlspecialchars($data['note'] ?? '') ?>">
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update
                                </button>

                                <a href="index.php" class="btn btn-secondary">
                                    Kembali
                                </a>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.title = "Edit Retur Jual";

    document.getElementById('idcustomer').addEventListener('change', function() {
        const idcustomer = this.value;
        const doSelect = document.getElementById('donumber');

        doSelect.innerHTML = '<option value="">Loading...</option>';

        if (!idcustomer) {
            doSelect.innerHTML = '<option value="">Unidentified</option>';
            return;
        }

        fetch('load_do_by_customer.php?idcustomer=' + idcustomer)
            .then(res => res.json())
            .then(data => {
                let html = '<option value="">Unidentified</option>';
                data.forEach(row => {
                    const tgl = row.deliverydate ? row.deliverydate : '-';
                    html += `<option value="${row.donumber}">
                    ${row.donumber} | ${tgl}
                </option>`;
                });
                doSelect.innerHTML = html;
            })
            .catch(() => {
                doSelect.innerHTML = '<option value="">Unidentified</option>';
            });
    });
</script>

<?php include "../footer.php"; ?>