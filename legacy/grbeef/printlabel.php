<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../dist/vendor/autoload.php";

// Validasi dan sanitasi input
if (!isset($_GET['idgrbeefdetail']) || empty($_GET['idgrbeefdetail'])) {
    die('Error: idgrbeefdetail is missing or invalid.');
}
$idgrbeefdetail = intval($_GET['idgrbeefdetail']);
$idgr = intval($_GET['idgr']);

// Ambil data label dan barang berdasarkan idgrbeefdetail
$query = "SELECT gbd.*, b.nmbarang, g.nmgrade 
          FROM grbeefdetail gbd
          JOIN barang b ON gbd.idbarang = b.idbarang
          JOIN grade g ON gbd.idgrade = g.idgrade
          WHERE gbd.idgrbeefdetail = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $idgrbeefdetail);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

// Pastikan data ditemukan
if (!$data) {
    header("Location: grdetail.php?idgr=$idgr");
    exit;
}

// Variabel untuk digunakan di halaman cetak
$nmbarang = $data['nmbarang'];
$idgrade = $data['idgrade'];
$nmgrade = $data['nmgrade'];
$qty = $data['qty'];
$pcs = $data['pcs'];
$packdate = $data['creatime'];
$kdbarcode = $data['kdbarcode'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label</title>
</head>

<body>
    <table width="365" height="270" cellpadding="0">
        <tbody>
            <tr>
                <td height="23" colspan="4">
                    <span style="font-size: 18px; color: #000000; font-family: 'Gill Sans', 'Gill Sans MT', 'Myriad Pro', 'DejaVu Sans Condensed', Helvetica, Arial, sans-serif;">
                        <strong>*YP*</strong>
                    </span>
                </td>
            </tr>
            <tr>
                <td height="21" colspan="4">
                    <span style="color: #000000; font-family: 'Gill Sans', 'Gill Sans MT', 'Myriad Pro', 'DejaVu Sans Condensed', Helvetica, Arial, sans-serif; font-size: 14px;">
                        <strong>Prod By: PT. SANTI WIJAYA MEAT</strong>
                    </span>
                </td>
            </tr>
            <tr>
                <td height="20" colspan="4">
                    <span style="color: #000000; font-size: 10px; font-family: 'Gill Sans', 'Gill Sans MT', 'Myriad Pro', 'DejaVu Sans Condensed', Helvetica, Arial, sans-serif;">
                        Perum Asabri Blok B No 20 Rt. 01/05 Ds. Sukasirna Kec. Jonggol Kab. Bogor
                    </span>
                </td>
            </tr>
            <tr>
                <td height="20" colspan="2">
                    <span style="font-size: 18px; color: #000000; font-family: 'Gill Sans', 'Gill Sans MT', 'Myriad Pro', 'DejaVu Sans Condensed', Helvetica, Arial, sans-serif;">
                        <strong><?= $nmbarang; ?></strong>
                    </span>
                </td>
                <td colspan="2" rowspan="5" align="center" valign="middle">
                    <img src="../dist/img/halal.png" alt="HALAL" height="100" align="absmiddle">
                    <div style="text-align: center; font-size: 10px; margin-top: 5px;">
                        <strong>ID00110015321510124</strong><br>
                        <strong>RPHR 3201170-027</strong>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="1" rowspan="2">
                    <span style="color: #000000; font-family: 'Gill Sans', 'Gill Sans MT', 'Myriad Pro', 'DejaVu Sans Condensed', Helvetica, Arial, sans-serif;">
                        <span style="font-size: 30px"><strong><?= number_format($qty, 2); ?></strong></span>
                    </span>
                </td>
                <td height="20" style="font-size: 12px;">
                    <?php if ($pcs) { ?>
                        <strong><i><?= $pcs . "-Pcs"; ?></i></strong>
                    <?php } ?>
                </td>
            </tr>
            <tr>
                <td height="20" style="font-size: 12px;">
                    <!-- <strong><?= $nmgrade; ?></strong> -->
                </td>
            </tr>
            <tr>
                <td height="20" style="font-size: 11px;">
                    <span style="color: #000000;">Packed Date&nbsp; :</span>
                </td>
                <td style="font-size: 11px;">
                    <span style="color: #000000;"><?= date('d-M-Y', strtotime($packdate)); ?></span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="font-size: 12px; text-align: left;">
                    <strong>
                        <?php
                        if ($idgrade == 1 or $idgrade == 3) {
                            echo "KEEP CHILL 0°C";
                        } else {
                            echo "KEEP FROZEN -18°C";
                        }
                        ?>
                    </strong>
                </td>
            </tr>
            <tr>
                <td height="20" colspan="4" align="center" valign="middle">
                    <?php
                    $generator = new Picqer\Barcode\BarcodeGeneratorJPG();
                    $barcode = $generator->getBarcode($kdbarcode, $generator::TYPE_CODE_128);
                    echo '<img src="data:image/jpeg;base64,' . base64_encode($barcode) . '" alt="Barcode">';
                    ?>
                </td>
            </tr>
            <tr>
                <td colspan="4" align="center">
                    <span style="color: #000000; font-family: 'Gill Sans', 'Gill Sans MT', 'Myriad Pro', 'DejaVu Sans Condensed', Helvetica, Arial, sans-serif;">
                        <?= $kdbarcode; ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
    <script>
        window.onload = function() {
            window.print();
            window.onafterprint = function() {
                window.location.href = 'grdetail.php?idgr=<?= $idgr; ?>';
            };
        };
    </script>
</body>

</html>