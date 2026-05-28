<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../dist/vendor/autoload.php";
require "barcoderj.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ================== INPUT ================== */
    $idreturjual = (int)$_POST['idreturjual'];
    $idbarang    = (int)$_POST['idbarang'];
    $idgrade     = (int)$_POST['idgrade'];
    $packdate    = $_POST['packdate'];
    $exp_input   = $_POST['exp'] ?? '';
    $qtyInput    = $_POST['qty'];
    $ph_input    = $_POST['ph'] ?? '';

    /* ================== BARCODE ================== */
    // digit 6 = retur jual
    $kdbarcode = '6' . $idreturjual . $kodeauto;

    /* ================== SESSION (prefill) ================== */
    $_SESSION['idbarang'] = $idbarang;
    $_SESSION['idgrade']  = $idgrade;
    $_SESSION['packdate'] = $packdate;
    $_SESSION['exp']      = $exp_input;
    $_SESSION['ph']       = $ph_input;
    $_SESSION['qty']      = $qtyInput;

    /* ================== EXP ================== */
    $exp = ($exp_input !== '') ? mysqli_real_escape_string($conn, $exp_input) : null;
    $expSql = is_null($exp) ? "NULL" : "'$exp'";

    /* ================== pH ================== */
    $phFloat = null;
    if ($ph_input !== '') {
        $rawPh = str_replace(',', '.', $ph_input);
        $phVal = filter_var($rawPh, FILTER_VALIDATE_FLOAT);

        if ($phVal === false || $phVal < 5.4 || $phVal > 5.7) {
            die("Nilai pH harus antara 5.4 – 5.7");
        }

        // truncate 1 digit
        $phFloat = floor($phVal * 10) / 10;
    }
    $phSql = is_null($phFloat) ? "NULL" : number_format($phFloat, 1, '.', '');

    /* ================== QTY / PCS ================== */
    $qty = null;
    $pcs = null;

    if (strpos($qtyInput, "/") !== false) {
        [$qty, $pcs] = explode("/", $qtyInput, 2);
    } else {
        $qty = $qtyInput;
    }

    $qty = str_replace(',', '.', trim($qty));
    if (!is_numeric($qty) || (float)$qty <= 0) {
        die("Qty tidak valid");
    }
    $qty = number_format((float)$qty, 2, '.', '');

    if ($pcs !== null && $pcs !== '') {
        $pcs = preg_replace('/\D+/', '', $pcs);
        $pcs = ($pcs === '') ? null : (int)$pcs;
    } else {
        $pcs = null;
    }

    $pcsSql = is_null($pcs) ? "NULL" : $pcs;

    /* ================== INSERT RETUR DETAIL ONLY ================== */
    $sqlDetail = "
        INSERT INTO returjualdetail
            (idreturjual, kdbarcode, idbarang, idgrade, qty, pcs, pod, ph)
        VALUES
            ($idreturjual, '$kdbarcode', $idbarang, $idgrade, $qty, $pcsSql, '$packdate', $phSql)
    ";

    if (!mysqli_query($conn, $sqlDetail)) {
        die(mysqli_error($conn));
    }
}

/* ================== NAMA BARANG ================== */
$nmbarang = '';
$res = mysqli_query($conn, "SELECT nmbarang FROM barang WHERE idbarang = $idbarang");
if ($r = mysqli_fetch_assoc($res)) {
    $nmbarang = $r['nmbarang'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Label Retur</title>
</head>

<body>
    <table width="365" height="270" cellpadding="0">
        <tbody>

            <tr>
                <td colspan="4"><strong>*YP*</strong></td>
            </tr>

            <tr>
                <td colspan="4"><strong>Prod By: PT. SANTI WIJAYA MEAT</strong></td>
            </tr>

            <tr>
                <td colspan="4" style="font-size:10px">
                    Perum Asabri Blok B No 20 Rt.01/05 Ds. Sukasirna Kec. Jonggol Kab. Bogor
                </td>
            </tr>

            <tr>
                <td colspan="2"><strong><?= htmlspecialchars($nmbarang) ?></strong></td>
                <td colspan="2" rowspan="5" align="center">
                    <img src="../dist/img/hi2.svg" height="100">
                </td>
            </tr>

            <tr>
                <td rowspan="2" style="white-space:nowrap;">
                    <span style="font-size:28px;font-weight:bold;">
                        <?= number_format($qty, 2) ?>
                    </span>
                    <span style="font-size:14px;font-weight:bold;">Kg</span>
                </td>
                <td><?= $pcs ? $pcs . ' Pcs' : '' ?></td>
            </tr>

            <tr>
                <td><?= $phFloat !== null ? "pH " . number_format($phFloat, 1) : "&nbsp;" ?></td>
            </tr>

            <tr>
                <td>Packed Date :</td>
                <td><?= date('d-M-Y', strtotime($packdate)) ?></td>
            </tr>

            <?php if ($exp): ?>
                <tr>
                    <td>Expired Date :</td>
                    <td><?= date('d-M-Y', strtotime($exp)) ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            <?php endif; ?>

            <tr>
                <td colspan="2"><strong>KEEP CHILL / FROZEN</strong></td>
                <td style="font-size:10px;text-align:center">
                    NO.01011263450821<br>NKV CS-3201170-027
                </td>
            </tr>

            <tr>
                <td colspan="4" align="center">
                    <?php
                    $gen = new Picqer\Barcode\BarcodeGeneratorJPG();
                    echo '<img src="data:image/jpeg;base64,' .
                        base64_encode($gen->getBarcode($kodeauto, $gen::TYPE_CODE_128)) .
                        '">';
                    ?>
                </td>
            </tr>

            <tr>
                <td colspan="4" align="center"><?= $kodeauto ?></td>
            </tr>

        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
            window.onafterprint = function() {
                window.location.href = 'label_retur.php?idreturjual=<?= $idreturjual ?>';
            };
            setTimeout(() => window.close(), 500);
        };
    </script>

</body>

</html>