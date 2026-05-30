<?php
require "../verifications/auth.php";
$idusers = $_SESSION['idusers'] ?? 0;
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Tangkap idboning dari parameter URL
$idboning = isset($_GET['idboning']) ? (int)$_GET['idboning'] : 0;

if ($idboning == 0) {
    echo "<script>alert('ID Boning tidak valid!'); window.location='index.php';</script>";
    exit;
}

// Ambil info Batch Boning buat ditampilin di Header
$qBoning = mysqli_query($conn, "SELECT batchboning, tglboning FROM boning WHERE idboning = $idboning");
$dataBoning = mysqli_fetch_assoc($qBoning);

if (!$dataBoning) {
    echo "<script>alert('Data Boning tidak ditemukan!'); window.location='index.php';</script>";
    exit;
}
?>
<div class="content-wrapper">

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col mt-3">

                    <form method="POST" action="request_boning_input.php">
                        <input type="hidden" name="idboning" value="<?= $idboning ?>">
                        <input type="hidden" name="idusers" value="<?= $idusers ?>">

                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h3 class="card-title"><i class="fas fa-clipboard-list"></i> Create Request Marketing</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Batch Boning</label>
                                            <input type="text" class="form-control" value="<?= $dataBoning['batchboning'] ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Tgl Boning</label>
                                            <input type="text" class="form-control" value="<?= date('d-M-Y', strtotime($dataBoning['tglboning'])) ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Spesifikasi / Instruksi Umum <small class="text-muted">(Opsional)</small></label>
                                            <textarea class="form-control" name="spesifications" rows="2" placeholder="Contoh: Boning besok speck LION"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h3 class="card-title"><i class="fas fa-list-ol"></i> Daftar Item Request</h3>
                            </div>
                            <div class="card-body">

                                <div id="items-container">
                                    <div class="row item-row align-items-end mb-2">
                                        <div class="col-md-4 col-12">
                                            <label class="small mb-1">Product Request <span class="text-danger">*</span></label>
                                            <select class="form-control product-select" name="idbarang[]" required>
                                                <option value="">--Pilih Item--</option>
                                                <?php
                                                $q = mysqli_query($conn, "SELECT * FROM barang ORDER BY nmbarang ASC");
                                                while ($r = mysqli_fetch_assoc($q)) {
                                                    echo '<option value="' . $r['idbarang'] . '">' . $r['nmbarang'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="col-md-2 col-4">
                                            <label class="small mb-1">Qty</label>
                                            <input type="number" step="0.01" name="qty[]" class="form-control text-right" value="0.00">
                                        </div>

                                        <div class="col-md-2 col-4">
                                            <label class="small mb-1">Satuan</label>
                                            <select class="form-control" name="satuan[]">
                                                <option value="Kg">Kg</option>
                                                <option value="Karkas">Karkas</option>
                                                <option value="Sapi">Sapi</option>
                                                <option value="Lainnya">Lainnya</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 col-4">
                                            <label class="small mb-1">Catatan Tambahan</label>
                                            <input type="text" name="notes[]" class="form-control" placeholder="Cth: speck black owl">
                                        </div>

                                        <div class="col-md-1 col-12 text-center">
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-3">

                                <div class="row align-items-center">
                                    <div class="col-md-1 col-12 mb-2">
                                        <button type="button" class="btn btn-link text-success" onclick="addItem()" title="Tambah Item">
                                            <i class="fas fa-plus-circle fa-lg"></i>
                                        </button>
                                    </div>

                                    <div class="col"></div>

                                    <div class="col-md-2 col-12 mb-2">
                                        <a href="index.php" class="btn btn-block btn-default">
                                            <i class="fas fa-arrow-left"></i> Batal
                                        </a>
                                    </div>

                                    <div class="col-md-2 col-12 mb-2">
                                        <button type="submit" class="btn btn-block bg-gradient-primary"
                                            onclick="return confirm('Pastikan Request Marketing Sudah Benar!')" name="submit">
                                            <i class="fas fa-save"></i> Submit
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </section>
</div>

<script src="../dist/js/movefocus.js"></script>

<script>
    // ===============================
    // DISABLE PRODUCT DUPLIKAT
    // ===============================
    function refreshProductOptions() {
        const selects = document.querySelectorAll('.product-select');
        const selected = [];

        selects.forEach(s => {
            if (s.value) selected.push(s.value);
        });

        selects.forEach(s => {
            const cur = s.value;
            s.querySelectorAll('option').forEach(o => {
                if (!o.value) return;
                o.disabled = selected.includes(o.value) && o.value !== cur;
            });
        });
    }

    // ===============================
    // ADD ITEM ROW
    // ===============================
    function addItem() {
        const c = document.getElementById('items-container');
        const r = document.createElement('div');
        r.className = 'row item-row align-items-end mb-2';

        r.innerHTML = `
      <div class="col-md-4 col-12">
         <select class="form-control product-select" name="idbarang[]" required>
         <option value="">--Pilih Item--</option>
         <?php
            $q = mysqli_query($conn, "SELECT * FROM barang ORDER BY nmbarang ASC");
            while ($x = mysqli_fetch_assoc($q)) {
                echo '<option value="' . $x['idbarang'] . '">' . $x['nmbarang'] . '</option>';
            }
            ?>
         </select>
      </div>
      <div class="col-md-2 col-4">
         <input type="number" step="0.01" name="qty[]" class="form-control text-right" value="0.00">
      </div>
      <div class="col-md-2 col-4">
         <select class="form-control" name="satuan[]">
            <option value="Kg">Kg</option>
            <option value="Karkas">Karkas</option>
            <option value="Sapi">Sapi</option>
            <option value="Lainnya">Lainnya</option>
         </select>
      </div>
      <div class="col-md-3 col-4">
         <input type="text" name="notes[]" class="form-control" placeholder="Cth: speck black owl">
      </div>
      <div class="col-md-1 col-12 text-center">
         <button type="button" class="btn btn-link text-danger"
         onclick="this.closest('.item-row').remove();refreshProductOptions();">
         <i class="fas fa-minus-circle fa-lg"></i>
         </button>
      </div>
      `;

        c.appendChild(r);

        // aktifkan ulang logic duplikat
        refreshProductOptions();
    }

    // ===============================
    // EVENTS
    // ===============================
    document.addEventListener('change', e => {
        if (e.target.classList.contains('product-select')) {
            refreshProductOptions();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        refreshProductOptions();
    });
</script>

<?php include "../footer.php"; ?>