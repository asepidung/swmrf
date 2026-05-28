<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "kdrawmateunik.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// =======================
// Ambil idrawmate
// =======================
$idrawmate = isset($_GET['idrawmate']) ? (int)$_GET['idrawmate'] : 0;
if ($idrawmate <= 0) {
  echo "<div class='alert alert-danger'>ID tidak valid.</div>";
  include "../footer.php";
  exit();
}

// =======================
// Ambil data rawmate
// =======================
$stmt = $conn->prepare("SELECT * FROM rawmate WHERE idrawmate = ?");
$stmt->bind_param("i", $idrawmate);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
  echo "<div class='alert alert-danger'>Data material tidak ditemukan.</div>";
  include "../footer.php";
  exit();
}

// =======================
// Daftar unit
// =======================
$units = ["Box", "Ikat", "Kg", "Pack", "Pcs", "Set"];
$barmin = isset($row['barmin']) ? (int)$row['barmin'] : 0;
?>

<div class="content-wrapper">
  <section class="content">
    <div class="container-fluid">
      <div class="row justify-content-center">
        <!-- Responsive width -->
        <div class="col-12 col-lg-7">

          <div class="card card-dark mt-3">
            <div class="card-header">
              <h3 class="card-title">Edit Material</h3>
            </div>

            <form method="POST" action="proseseditrawmate.php">
              <div class="card-body">

                <input type="hidden" name="idrawmate"
                  value="<?= htmlspecialchars($row['idrawmate'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="row">
                  <!-- KODE -->
                  <div class="col-12 col-md-6">
                    <div class="form-group">
                      <label for="kdrawmate">Kode</label>
                      <input type="text" class="form-control"
                        name="kdrawmate" id="kdrawmate"
                        value="<?= htmlspecialchars($row['kdrawmate'], ENT_QUOTES, 'UTF-8'); ?>"
                        readonly>
                    </div>
                  </div>

                  <!-- UNIT -->
                  <div class="col-12 col-md-6">
                    <div class="form-group">
                      <label for="unit">Unit / Satuan <span class="text-danger">*</span></label>
                      <select name="unit" id="unit" class="form-control" required>
                        <option value="">-- Pilih Satuan --</option>
                        <?php foreach ($units as $u): ?>
                          <option value="<?= htmlspecialchars($u); ?>"
                            <?= ($row['unit'] === $u) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($u); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- NAMA -->
                <div class="form-group">
                  <label for="nmrawmate">Nama Material <span class="text-danger">*</span></label>
                  <input type="text" class="form-control"
                    name="nmrawmate" id="nmrawmate"
                    value="<?= htmlspecialchars($row['nmrawmate'], ENT_QUOTES, 'UTF-8'); ?>"
                    required>
                </div>

                <!-- CATEGORY -->
                <div class="form-group">
                  <label for="category">Category <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <select name="idrawcategory" id="category" class="form-control" required>
                      <option value="">-- Select Category --</option>
                      <?php
                      $qcat = mysqli_query($conn, "SELECT idrawcategory, nmcategory FROM rawcategory ORDER BY nmcategory ASC");
                      while ($cat = mysqli_fetch_assoc($qcat)):
                      ?>
                        <option value="<?= (int)$cat['idrawcategory']; ?>"
                          <?= ($cat['idrawcategory'] == $row['idrawcategory']) ? 'selected' : ''; ?>>
                          <?= htmlspecialchars($cat['nmcategory'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                      <?php endwhile; ?>
                    </select>
                    <div class="input-group-append">
                      <a href="../rawcategory/newrawcategory.php" class="btn btn-dark" title="Tambah Category">
                        <i class="fas fa-plus"></i>
                      </a>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <!-- BARMIN -->
                  <div class="col-12 col-md-6">
                    <div class="form-group">
                      <label for="barmin">Batas Minimal Stock</label>
                      <input type="number" class="form-control"
                        name="barmin" id="barmin"
                        min="0"
                        value="<?= $barmin > 0 ? $barmin : ''; ?>"
                        placeholder="Opsional, contoh: 10">
                      <small class="text-muted">Kosongkan jika tidak ada batas minimal</small>
                    </div>
                  </div>

                  <!-- STOCK -->
                  <div class="col-12 col-md-6">
                    <div class="form-group">
                      <label>Tampilkan di Stock</label>
                      <div class="d-flex flex-wrap">
                        <div class="form-check mr-4">
                          <input class="form-check-input" type="radio"
                            name="stock" id="stock_yes" value="1"
                            <?= ($row['stock'] == 1) ? 'checked' : ''; ?>>
                          <label class="form-check-label" for="stock_yes">Ya</label>
                        </div>
                        <div class="form-check">
                          <input class="form-check-input" type="radio"
                            name="stock" id="stock_no" value="0"
                            <?= ($row['stock'] == 0) ? 'checked' : ''; ?>>
                          <label class="form-check-label" for="stock_no">Tidak</label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <div class="card-footer text-right">
                <button type="submit" class="btn bg-gradient-primary px-4">
                  <i class="fas fa-save mr-1"></i> Update
                </button>
              </div>

            </form>
          </div>

        </div>
      </div>
    </div>
  </section>
</div>

<script>
  document.title = "EDIT RAW MATERIAL";
</script>

<?php
include "../footer.php";
include "../footnote.php";
?>