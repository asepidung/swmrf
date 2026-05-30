<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";

$idgr = $_GET['idgr'];

// Mengambil daftar barang
$query = "SELECT * FROM barang ORDER BY nmbarang ASC";
$result = mysqli_query($conn, $query);
$barangOptions = "";
while ($row = mysqli_fetch_assoc($result)) {
    $idbarang = $row['idbarang'];
    $nmbarang = $row['nmbarang'];
    $barangOptions .= "<option value=\"$idbarang\">$nmbarang</option>";
}
?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-3">
                <a href="index.php"><button type="button" class="btn btn-outline-primary"><i class="fas fa-arrow-alt-circle-left"></i> Kembali</button></a>
            </div>
            <div class="col">
                <span class="text-primary">
                    <h4>PRINT LABEL BEEF</h4>
                </span>
            </div>
        </div>
    </div>
</div>
<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="insertdata.php" onsubmit="submitForm(event)">
                            <input type="hidden" name="idgr" value="<?= $idgr; ?>">
                            <div class="form-group">
                                <div class="input-group">
                                    <select class="form-control" name="origin" id="origin" required>
                                        <option value="" <?= (!isset($_SESSION['origin']) || $_SESSION['origin'] == '') ? 'selected' : ''; ?>>--ORIGIN--</option>
                                        <option value="2" <?= (isset($_SESSION['origin']) && $_SESSION['origin'] == '2') ? 'selected' : ''; ?>>Trading</option>
                                        <option value="5" <?= (isset($_SESSION['origin']) && $_SESSION['origin'] == '5') ? 'selected' : ''; ?>>Import</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="input-group">
                                    <select class="form-control" name="idbarang" id="idbarang" required>
                                        <option value="" selected>--Pilih Item--</option>
                                        <?php
                                        // Memeriksa apakah ada session sebelumnya untuk idbarang
                                        $selectedIdBarang = isset($_SESSION['idbarang']) ? $_SESSION['idbarang'] : null;

                                        // Query untuk mendapatkan data barang
                                        $query = "SELECT * FROM barang ORDER BY nmbarang ASC";
                                        $result = mysqli_query($conn, $query);

                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $selected = ($row['idbarang'] == $selectedIdBarang) ? 'selected' : '';
                                            echo "<option value='{$row['idbarang']}' {$selected}>{$row['nmbarang']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="input-group-append">
                                        <a href="../barang/newbarang.php" class="btn btn-primary"><i class="fas fa-plus"></i></a>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="input-group">
                                    <select class="form-control" name="idgrade" id="idgrade" required>
                                        <option value="" selected>--Pilih Grade--</option>
                                        <?php
                                        // Memeriksa apakah ada session sebelumnya untuk idgrade
                                        $selectedIdGrade = isset($_SESSION['idgrade']) ? $_SESSION['idgrade'] : null;

                                        // Query untuk mendapatkan data grade
                                        $query = "SELECT * FROM grade ORDER BY nmgrade ASC";
                                        $result = mysqli_query($conn, $query);

                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $selected = ($row['idgrade'] == $selectedIdGrade) ? 'selected' : '';
                                            echo "<option value='{$row['idgrade']}' {$selected}>{$row['nmgrade']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="input-group">
                                    <?php
                                    // Memeriksa apakah ada session sebelumnya untuk packdate, jika tidak gunakan tanggal hari ini
                                    $packDate = isset($_SESSION['packdate']) ? $_SESSION['packdate'] : date('Y-m-d');
                                    ?>
                                    <input type="date" class="form-control" name="packdate" id="packdate" required value="<?= htmlspecialchars($packDate); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="mt-2">Weight & Pcs <span class="text-danger">*</span></label>
                                <div class="input-group col">
                                    <input type="text" class="form-control" name="qty" id="qty" placeholder="Weight & Pcs" required autofocus>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-block bg-gradient-primary" name="submit">Print</button>
                        </form>

                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="card">
                    <div class="card-body">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                            <thead class="text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Barcode</th>
                                    <th>Product</th>
                                    <th>Kode</th>
                                    <th>Qty</th>
                                    <th>Pcs</th>
                                    <th>POD</th>
                                    <th>Create At</th>
                                    <th>Hapus</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $ambildata = mysqli_query($conn, "SELECT grbeefdetail.*, barang.nmbarang, grade.nmgrade
                                    FROM grbeefdetail
                                    INNER JOIN barang ON grbeefdetail.idbarang = barang.idbarang
                                    INNER JOIN grade ON grbeefdetail.idgrade = grade.idgrade
                                    WHERE idgr = $idgr AND grbeefdetail.is_deleted = 0 ORDER BY idgrbeefdetail DESC");
                                while ($tampil = mysqli_fetch_array($ambildata)) { ?>
                                    <tr class="text-center">
                                        <td><?= $no; ?></td>
                                        <td><?= $tampil['kdbarcode']; ?></td>
                                        <td class="text-left"><?= $tampil['nmbarang']; ?></td>
                                        <td><?= $tampil['nmgrade']; ?></td>
                                        <td class="text-right"><?= $tampil['qty']; ?></td>
                                        <?php
                                        $pcs = $tampil['pcs'] < 1 ? "" : $tampil['pcs'];
                                        ?>
                                        <td><?= $pcs; ?></td>
                                        <td><?= !empty($tampil['pod']) ? date('d-M-Y', strtotime($tampil['pod'])) : '-'; ?></td>
                                        <td><?= date("H:i:s", strtotime($tampil['creatime'])); ?></td>
                                        <td>
                                            <a href="deletegrdetail.php?idgr=<?= $idgr; ?>&idgrdetail=<?= $tampil['idgrbeefdetail']; ?>&from=grdetail " class="text-info" onclick="return confirm('Yakin Lu?')">
                                                <i class="far fa-times-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php
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
</div>
<script>
    document.title = "CETAK HASIL REPACK";
</script>
<?php
require "../footer.php";
?>