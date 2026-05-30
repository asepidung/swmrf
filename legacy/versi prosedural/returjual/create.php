<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idusers = $_SESSION['idusers'] ?? 0;
?>
<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mt-3">
                    <form method="POST" action="store.php">
                        <div class="card">
                            <div class="card-body">

                                <!-- CUSTOMER -->
                                <div class="form-group">
                                    <label>Customer <span class="text-danger">*</span></label>
                                    <select class="form-control" name="idcustomer" id="idcustomer" required>
                                        <option value="">-- Pilih Customer --</option>
                                        <?php
                                        $q = mysqli_query($conn, "SELECT idcustomer,nama_customer FROM customers ORDER BY nama_customer ASC");
                                        while ($r = mysqli_fetch_assoc($q)) {
                                            echo "<option value='{$r['idcustomer']}'>{$r['nama_customer']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- DO NUMBER -->
                                <div class="form-group">
                                    <label>No DO</label>
                                    <select class="form-control" name="donumber" id="donumber">
                                        <option value="">Unidentified</option>
                                    </select>
                                    <small class="text-muted">Kosongkan jika tidak diketahui</small>
                                </div>

                                <!-- RETUR DATE -->
                                <div class="form-group">
                                    <label>Tanggal Retur <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="returdate" required value="<?= date('Y-m-d'); ?>">
                                </div>

                                <!-- NOTE -->
                                <div class="form-group">
                                    <label>Catatan</label>
                                    <input type="text" class="form-control" name="note" placeholder="Keterangan retur">
                                </div>

                                <input type="hidden" name="idusers" value="<?= $idusers ?>">

                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-save"></i> Process
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
    document.title = "New Retur Jual";

    document.getElementById('idcustomer').addEventListener('change', function() {
        const idcustomer = this.value;
        const doSelect = document.getElementById('donumber');

        doSelect.innerHTML = '<option value="">Loading...</option>';

        if (!idcustomer) {
            doSelect.innerHTML = '<option value="">Unidentified</option>';
            return;
        }

        fetch('load_do_by_customer.php?idcustomer=' + idcustomer)
            .then(response => response.json())
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