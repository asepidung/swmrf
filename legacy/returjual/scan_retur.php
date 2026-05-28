<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idreturjual = intval($_GET['idreturjual'] ?? 0);
if ($idreturjual <= 0) die("ID retur tidak valid");

// header retur
$q = mysqli_query($conn, "
    SELECT r.returnnumber, c.nama_customer
    FROM returjual r
    JOIN customers c ON r.idcustomer = c.idcustomer
    WHERE r.idreturjual = $idreturjual
");
$header = mysqli_fetch_assoc($q);
if (!$header) die("Data retur tidak ditemukan");
?>

<div class="content-wrapper">

    <!-- CONTENT HEADER (INI YANG PENTING) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">

                <!-- LEFT -->
                <div class="col-sm-6 col-12 mb-2">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>

                <!-- RIGHT -->
                <div class="col-sm-6 col-12 text-sm-right">
                    <strong><?= htmlspecialchars($header['returnnumber'] ?? '-') ?></strong><br>
                    <small>Customer: <?= htmlspecialchars($header['nama_customer']) ?></small>
                </div>

            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <section class="content">
        <div class="container-fluid">

            <!-- SCAN FORM -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="POST" action="scan_retur_store.php" id="scanForm">
                        <input type="hidden" name="idreturjual" value="<?= $idreturjual ?>">

                        <div class="form-group mb-0">
                            <label>Scan Barcode</label>
                            <input type="text"
                                name="barcode"
                                id="barcode"
                                class="form-control form-control-md"
                                placeholder="Scan barcode (otomatis)"
                                autofocus>
                        </div>
                    </form>
                </div>
            </div>

            <!-- HASIL SCAN -->
            <div class="card">
                <div class="card-body table-responsive">
                    <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                            <tr>
                                <th>#</th>
                                <th>Barcode</th>
                                <th>Item</th>
                                <th>Grade</th>
                                <th>Qty</th>
                                <th>PCS</th>
                                <th>pH</th>
                                <th>POD</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $q = mysqli_query($conn, "
                                SELECT rd.*, b.nmbarang, g.nmgrade
                                FROM returjualdetail rd
                                LEFT JOIN barang b ON rd.idbarang = b.idbarang
                                LEFT JOIN grade g ON rd.idgrade = g.idgrade
                                WHERE rd.idreturjual = $idreturjual
                                  AND rd.is_deleted = 0
                                ORDER BY rd.idreturjualdetail DESC
                            ");

                            while ($r = mysqli_fetch_assoc($q)) {
                                $pod = '-';
                                if (!empty($r['pod'])) {
                                    $pod = date('d-m-Y', strtotime($r['pod']));
                                }

                                echo "<tr class='text-center'>
                                    <td>{$no}</td>
                                    <td>{$r['kdbarcode']}</td>
                                    <td>{$r['nmbarang']}</td>
                                    <td>{$r['nmgrade']}</td>
                                    <td class='text-right'>" . number_format($r['qty'], 2) . "</td>
                                    <td>{$r['pcs']}</td>
                                    <td>{$r['ph']}</td>
                                    <td>{$pod}</td>
                                    <td>
                                        <a href='delete_detail.php?id={$r['idreturjualdetail']}&idreturjual={$idreturjual}'
                                           class='text-danger'
                                           onclick=\"return confirm('Hapus item ini?')\">
                                           <i class='far fa-times-circle'></i>
                                        </a>
                                    </td>
                                </tr>";
                                $no++;
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
    document.title = "Scan Retur";

    const input = document.getElementById('barcode');
    input.focus();

    input.addEventListener('input', function() {
        if (this.value.length > 4) {
            document.getElementById('scanForm').submit();
        }
    });
</script>

<?php include "../footer.php"; ?>