<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

if (!isset($_GET['id'])) {
    die("Jalankan dari halaman Data Barang");
}

$idbarang = intval($_GET['id']);

// Ambil nama produk
$qbarang = mysqli_query($conn, "SELECT nmbarang FROM barang WHERE idbarang = $idbarang");
$dbarang = mysqli_fetch_assoc($qbarang);
$nmbarang = $dbarang['nmbarang'] ?? 'Produk Tidak Diketahui';

// ===============================
// AMBIL DATA DROPDOWN (DIFILTER)
// ===============================

// Karton Top (hanya yang mengandung 'TOP')
$karton_top = mysqli_query($conn, "
    SELECT * FROM rawmate 
    WHERE idrawcategory = 2 AND stock = 1 AND nmrawmate LIKE '%TOP%' 
    ORDER BY nmrawmate ASC
");

// Karton Bottom (hanya yang mengandung 'BOTTOM')
$karton_bottom = mysqli_query($conn, "
    SELECT * FROM rawmate 
    WHERE idrawcategory = 2 AND stock = 1 AND nmrawmate LIKE '%BOTTOM%' 
    ORDER BY nmrawmate ASC
");

// Plastik Cryovac (semua plastik kecuali Linier)
$plastik = mysqli_query($conn, "
    SELECT * FROM rawmate 
    WHERE idrawcategory = 3 AND stock = 1 AND nmrawmate NOT LIKE '%LINIER%' 
    ORDER BY nmrawmate ASC
");

// ===============================
// AMBIL DATA BOM
// ===============================
$bom = [];
$qbom = mysqli_query($conn, "
    SELECT b.idrawmate, b.qty, b.is_active, r.idrawcategory, r.nmrawmate
    FROM bom_rawmate b
    JOIN rawmate r ON b.idrawmate = r.idrawmate
    WHERE b.idbarang = $idbarang AND b.is_active = 1
");
while ($row = mysqli_fetch_assoc($qbom)) {
    $bom[] = $row;
}

// ===============================
// HELPER FUNCTIONS
// ===============================
function has_material($bom, $idrawmate)
{
    foreach ($bom as $b) {
        if ($b['idrawmate'] == $idrawmate && $b['is_active'] == 1) return true;
    }
    return false;
}

function get_qty($bom, $idrawmate)
{
    foreach ($bom as $b) {
        if ($b['idrawmate'] == $idrawmate && $b['is_active'] == 1) return $b['qty'];
    }
    return 0;
}

function get_selected($bom, $idrawmate)
{
    foreach ($bom as $b) {
        if ($b['idrawmate'] == $idrawmate && $b['is_active'] == 1)
            return 'selected';
    }
    return '';
}

function get_material_id($conn, $idcategory)
{
    $q = mysqli_query($conn, "SELECT idrawmate FROM rawmate WHERE idrawcategory = $idcategory LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $d = mysqli_fetch_assoc($q);
        return $d['idrawmate'];
    }
    return 0;
}

?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h4><i class="fas fa-bomb"></i> BOM <?= htmlspecialchars($nmbarang); ?></h4>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="../barang/barang.php" class="btn btn-success btn-sm"><i class="fas fa-undo-alt"></i> Kembali</a>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <form action="updatebom.php" method="POST">
                <input type="hidden" name="idbarang" value="<?= $idbarang; ?>">

                <div class="card card-dark shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title">Bahan Penolong Packaging</h3>
                    </div>
                    <div class="card-body">

                        <!-- Karung -->
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">Kemasan Karung</label>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="karung" name="karung" value="1"
                                        <?= has_material($bom, get_material_id($conn, 21)) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="karung">Gunakan Karung</label>
                                </div>
                            </div>
                        </div>

                        <!-- Karton Top -->
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">Karton Top</label>
                            <div class="col-md-6">
                                <select name="karton_top" id="karton_top" class="form-control select2">
                                    <option value="">-- Pilih Karton Top --</option>
                                    <?php
                                    while ($r = mysqli_fetch_assoc($karton_top)) { ?>
                                        <option value="<?= $r['idrawmate']; ?>" <?= get_selected($bom, $r['idrawmate']); ?>>
                                            <?= htmlspecialchars($r['nmrawmate']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Karton Bottom -->
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">Karton Bottom</label>
                            <div class="col-md-6">
                                <select name="karton_bottom" id="karton_bottom" class="form-control select2">
                                    <option value="">-- Pilih Karton Bottom --</option>
                                    <?php
                                    while ($r = mysqli_fetch_assoc($karton_bottom)) { ?>
                                        <option value="<?= $r['idrawmate']; ?>" <?= get_selected($bom, $r['idrawmate']); ?>>
                                            <?= htmlspecialchars($r['nmrawmate']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Plastik Cryovac -->
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">Plastik</label>
                            <div class="col-md-6">
                                <select name="plastik" id="plastik" class="form-control select2">
                                    <option value="">-- Pilih Jenis Plastik --</option>
                                    <?php
                                    while ($r = mysqli_fetch_assoc($plastik)) { ?>
                                        <option value="<?= $r['idrawmate']; ?>" <?= get_selected($bom, $r['idrawmate']); ?>>
                                            <?= htmlspecialchars($r['nmrawmate']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Plastik Linier -->
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">Linier</label>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <?php
                                    $q_linier = mysqli_query($conn, "SELECT idrawmate FROM rawmate WHERE idrawcategory=3 AND nmrawmate LIKE '%LINIER%' LIMIT 1");
                                    $id_linier = ($q_linier && mysqli_num_rows($q_linier) > 0) ? mysqli_fetch_assoc($q_linier)['idrawmate'] : 0;
                                    ?>
                                    <input type="checkbox" class="form-check-input" id="linier" name="linier" value="1"
                                        <?= has_material($bom, $id_linier) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="linier">Gunakan Plastik Linier</label>
                                </div>
                            </div>
                        </div>

                        <!-- Drylog -->
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">Drylog</label>
                            <div class="col-md-2">
                                <input type="number" min="0" name="drylog" class="form-control"
                                    value="<?= get_qty($bom, 5); ?>" readonly>
                            </div>
                            <div class="col-md-3 align-self-center">buah per pcs</div>
                        </div>

                        <!-- Tray -->
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">Tray</label>
                            <div class="col-md-2">
                                <?php
                                $q_tray = mysqli_query($conn, "SELECT idrawmate FROM rawmate WHERE idrawcategory=22 LIMIT 1");
                                $id_tray = ($q_tray && mysqli_num_rows($q_tray) > 0) ? mysqli_fetch_assoc($q_tray)['idrawmate'] : 0;
                                ?>
                                <input type="number" min="0" name="tray" class="form-control"
                                    value="<?= get_qty($bom, $id_tray); ?>">
                            </div>
                            <div class="col-md-3 align-self-center">buah per box</div>
                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    $(function() {
        $('.select2').select2({
            theme: 'bootstrap4'
        });
    });
    document.title = "Kelola BOM - <?= addslashes($nmbarang); ?>";
</script>

<?php include "../footnote.php"; ?>
<?php include "../footer.php"; ?>