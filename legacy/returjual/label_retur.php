<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

$idreturjual = intval($_GET['idreturjual'] ?? 0);
if ($idreturjual <= 0) die("ID retur tidak valid");

// default session
if (empty($_SESSION['packdate'])) $_SESSION['packdate'] = date('Y-m-d');
?>
<style>
    .mini-wrap {
        position: relative;
    }

    .mini-wrap .mini-inside {
        position: absolute;
        top: -8px;
        left: 12px;
        background: #fff;
        padding: 0 6px;
        font-size: 11px;
        color: #6c757d;
        z-index: 2;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-3">
                    <a href="index.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-alt-circle-left"></i> Kembali
                    </a>
                </div>
                <div class="col text-right text-danger">
                    <h4>LABEL BAHAN RETUR</h4>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">

                <!-- FORM -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="printlabel.php">
                                <input type="hidden" name="idreturjual" value="<?= $idreturjual ?>">

                                <!-- ITEM -->
                                <div class="form-group">
                                    <?php $sidbarang = $_SESSION['idbarang'] ?? ''; ?>
                                    <select class="form-control" name="idbarang" required autofocus>
                                        <option value="">-- Pilih Item --</option>
                                        <?php
                                        $qb = mysqli_query($conn, "SELECT idbarang,nmbarang FROM barang ORDER BY nmbarang ASC");
                                        while ($b = mysqli_fetch_assoc($qb)) {
                                            $sel = ($b['idbarang'] == $sidbarang) ? 'selected' : '';
                                            echo "<option value='{$b['idbarang']}' $sel>{$b['nmbarang']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- GRADE -->
                                <div class="form-group">
                                    <?php $sidgrade = $_SESSION['idgrade'] ?? ''; ?>
                                    <select class="form-control" name="idgrade" required>
                                        <option value="">-- Pilih Grade --</option>
                                        <?php
                                        $qg = mysqli_query($conn, "SELECT idgrade,nmgrade FROM grade ORDER BY nmgrade ASC");
                                        while ($g = mysqli_fetch_assoc($qg)) {
                                            $sel = ($g['idgrade'] == $sidgrade) ? 'selected' : '';
                                            echo "<option value='{$g['idgrade']}' $sel>{$g['nmgrade']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- PROD -->
                                <div class="form-group mini-wrap">
                                    <span class="mini-inside">Prod</span>
                                    <input type="date" class="form-control"
                                        name="packdate"
                                        value="<?= htmlspecialchars($_SESSION['packdate'], ENT_QUOTES); ?>" required>
                                </div>

                                <!-- EXP -->
                                <div class="form-group mini-wrap">
                                    <span class="mini-inside">Exp</span>
                                    <input type="date" class="form-control"
                                        name="exp"
                                        value="<?= htmlspecialchars($_SESSION['exp'] ?? '', ENT_QUOTES); ?>">
                                </div>

                                <!-- CATATAN -->
                                <div class="form-group">
                                    <input type="text" class="form-control"
                                        name="note"
                                        placeholder="Catatan Item"
                                        value="<?= htmlspecialchars($_SESSION['note'] ?? '', ENT_QUOTES); ?>">
                                </div>

                                <!-- QTY + PH -->
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-6">
                                            <input type="text" class="form-control"
                                                name="qty"
                                                placeholder="Weight / Pcs (12.34/5)"
                                                value="<?= htmlspecialchars($_SESSION['qty'] ?? '', ENT_QUOTES); ?>"
                                                required>
                                        </div>
                                        <div class="col-6">
                                            <input type="number" step="0.1" min="5.4" max="5.7"
                                                class="form-control"
                                                name="ph"
                                                placeholder="pH 5.4–5.7"
                                                value="<?= htmlspecialchars($_SESSION['ph'] ?? '', ENT_QUOTES); ?>"
                                                required>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-block bg-gradient-primary">
                                    Print
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- TABLE -->
                <div class="col-md-8">
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
                                        <th>pH</th>
                                        <th>Create</th>
                                        <th>Hapus</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $q = mysqli_query($conn, "
                              SELECT rd.*, b.nmbarang, g.nmgrade
                              FROM returjualdetail rd
                              JOIN barang b ON rd.idbarang=b.idbarang
                              JOIN grade g ON rd.idgrade=g.idgrade
                              WHERE rd.idreturjual=$idreturjual
                                AND rd.is_deleted=0
                              ORDER BY rd.idreturjualdetail DESC
                           ");
                                    while ($r = mysqli_fetch_assoc($q)) {
                                        $pcs = ($r['pcs'] > 0) ? $r['pcs'] : '';
                                        echo "<tr class='text-center'>
                                 <td>{$no}</td>
                                 <td>{$r['kdbarcode']}</td>
                                 <td class='text-left'>{$r['nmbarang']}</td>
                                 <td>{$r['nmgrade']}</td>
                                 <td>{$r['qty']}</td>
                                 <td>{$pcs}</td>
                                 <td>{$r['ph']}</td>
                                 <td>" . date('H:i:s', strtotime($r['creatime'])) . "</td>
                                 <td>
                                    <a href='deletedetailretur.php?iddetail={$r['idreturjualdetail']}&id={$idreturjual}'
                                       class='text-danger'
                                       onclick=\"return confirm('Yakin hapus?')\">
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

            </div>
        </div>
    </section>
</div>

<script>
    document.title = "RETUR PENJUALAN";
</script>

<?php require "../footer.php"; ?>