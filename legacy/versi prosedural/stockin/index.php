<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

// Query untuk mendapatkan data barang & grade (urutkan nama)
$query_barang = "SELECT idbarang, nmbarang FROM barang ORDER BY nmbarang ASC";
$result_barang = mysqli_query($conn, $query_barang);

$query_grade = "SELECT idgrade, nmgrade FROM grade ORDER BY nmgrade ASC";
$result_grade = mysqli_query($conn, $query_grade);

// Data tabel stockin
$query_stockin = "
    SELECT si.id, si.kdbarcode, g.nmgrade, b.nmbarang, si.qty, si.pcs, si.creatime 
    FROM stockin si
    JOIN barang b ON si.idbarang = b.idbarang
    JOIN grade g  ON si.idgrade  = g.idgrade
    WHERE si.is_deleted = 0
    ORDER BY si.creatime DESC";
$result_stockin = mysqli_query($conn, $query_stockin);
?>
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row"></div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- FORM -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <!-- Mini-label styles -->
                            <style>
                                .mini-field {
                                    position: relative;
                                }

                                .mini-field .mini-label {
                                    position: absolute;
                                    left: 10px;
                                    top: 6px;
                                    font-size: 10px;
                                    color: #6c757d;
                                    line-height: 1;
                                    background: #fff;
                                    padding: 0 2px;
                                    border-radius: 2px;
                                    z-index: 2;
                                    pointer-events: none;
                                }

                                .mini-field input.form-control {
                                    padding-left: 48px !important;
                                }
                            </style>

                            <form method="POST" action="stockin.php">
                                <!-- Dropdown Barang -->
                                <div class="form-group">
                                    <div class="input-group">
                                        <select class="form-control" name="idbarang" id="idbarang" required>
                                            <option value="">Pilih Barang</option>
                                            <?php while ($row_barang = mysqli_fetch_assoc($result_barang)) : ?>
                                                <option value="<?= (int)$row_barang['idbarang'] ?>">
                                                    <?= htmlspecialchars($row_barang['nmbarang'], ENT_QUOTES) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Dropdown Grade -->
                                <div class="form-group">
                                    <div class="input-group">
                                        <select class="form-control" name="idgrade" id="idgrade" required>
                                            <option value="">Pilih Grade</option>
                                            <?php while ($row_grade = mysqli_fetch_assoc($result_grade)) : ?>
                                                <option value="<?= (int)$row_grade['idgrade'] ?>">
                                                    <?= htmlspecialchars($row_grade['nmgrade'], ENT_QUOTES) ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- POD (Pack On Date) -->
                                <div class="form-group mini-field">
                                    <span class="mini-label">Pack</span>
                                    <div class="input-group">
                                        <input type="date" class="form-control" name="pod" id="pod" value="<?= date('Y-m-d'); ?>" required>
                                    </div>
                                </div>

                                <!-- Weight/Pcs (gabungan) + pH (sebaris) -->
                                <div class="form-group mt-1">
                                    <div class="row">
                                        <div class="col-7">
                                            <input type="text"
                                                class="form-control"
                                                name="qty" id="qty"
                                                placeholder="Weight / Pcs (cth: 12.34/5)"
                                                required>
                                        </div>
                                        <div class="col-5">
                                            <input type="number"
                                                class="form-control"
                                                name="ph" id="ph"
                                                step="0.1" min="5.4" max="5.7"
                                                placeholder="pH 5.4–5.7">
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit -->
                                <button type="submit" class="btn bg-gradient-primary btn-block" name="submit">Print</button>
                            </form>

                            <!-- Opsional: normalisasi qty saat blur -->
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const qty = document.getElementById('qty');
                                    if (qty) {
                                        qty.addEventListener('blur', function() {
                                            let v = (qty.value || '').trim().replace(/,/g, '.');
                                            const m = v.match(/^\s*([0-9.]+)\s*(?:\/\s*(\d+))?\s*$/);
                                            if (m) {
                                                const berat = parseFloat(m[1] || '0');
                                                const pcs = m[2] ? parseInt(m[2], 10) : '';
                                                if (!isNaN(berat)) {
                                                    qty.value = (pcs !== '' ? berat.toFixed(2) + '/' + pcs : berat.toFixed(2));
                                                }
                                            }
                                        });
                                    }
                                });
                            </script>

                        </div>
                    </div>
                </div>

                <!-- TABEL DATA STOCKIN -->
                <div class="col-lg">
                    <div class="card">
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Barcode</th>
                                        <th>Product</th>
                                        <th>Grade</th>
                                        <th>Qty</th>
                                        <th>Pcs</th>
                                        <th>Create</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    while ($row = mysqli_fetch_assoc($result_stockin)) :
                                    ?>
                                        <tr class="text-center">
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($row['kdbarcode'], ENT_QUOTES); ?></td>
                                            <td class="text-left"><?= htmlspecialchars($row['nmbarang'], ENT_QUOTES); ?></td>
                                            <td><?= htmlspecialchars($row['nmgrade'], ENT_QUOTES); ?></td>
                                            <td class="text-right"><?= number_format((float)$row['qty'], 2); ?></td>
                                            <td class="text-center"><?= ($row['pcs'] !== null && $row['pcs'] !== '') ? (int)$row['pcs'] : '-'; ?></td>
                                            <td class="text-center"><?= date('d-M-Y H:i:s', strtotime($row['creatime'])); ?></td>
                                            <td class="text-center">
                                                <a href="delete_stockin.php?kdbarcode=<?= urlencode($row['kdbarcode']); ?>"
                                                    onclick="return confirm('Yakin ingin menghapus?');"
                                                    class="text-danger">
                                                    <i class="fas fa-minus-square"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- /col -->
            </div><!-- /row -->
        </div><!-- /container-fluid -->
    </div><!-- /content -->

    <script>
        document.title = "Stock In";
    </script>
</div>

<?php require "../footer.php"; ?>