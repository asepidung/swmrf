<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../dist/vendor/autoload.php"; // Picqer barcode

// --- Validasi parameter ---
if (!isset($_GET['idstockin']) || !ctype_digit($_GET['idstockin'])) {
    exit('Error: idstockin is missing or invalid.');
}
$idstockin = (int)$_GET['idstockin'];

// --- Ambil data stockin + barang ---
$sql = "SELECT si.kdbarcode, si.idgrade, si.idbarang, si.qty, si.pcs, si.ph, si.pod, b.nmbarang
        FROM stockin si
        JOIN barang b ON b.idbarang = si.idbarang
        WHERE si.id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) exit('DB Error (prepare): ' . $conn->error);
$stmt->bind_param('i', $idstockin);
$stmt->execute();
$res  = $stmt->get_result();
$data = $res->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: stockin.php");
    exit;
}

// --- Siapkan variabel tampilan ---
$nmbarang  = (string)$data['nmbarang'];
$qty       = (float)$data['qty'];
$pcs       = $data['pcs']; // bisa null
$ph        = $data['ph'];  // bisa null (DECIMAL(3,1))
$pod       = (string)$data['pod'];
$idgrade   = (int)$data['idgrade'];
$kdbarcode = (string)$data['kdbarcode'];

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Label</title>
</head>

<body>
    <table width="365" height="270" cellpadding="0">
        <tbody>
            <tr>
                <td height="23" colspan="4">
                    <span style="font-size:18px;color:#000;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                        <strong>*YP*</strong>
                    </span>
                </td>
            </tr>

            <tr>
                <td height="21" colspan="4">
                    <span style="color:#000;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;font-size:14px;">
                        <strong>Prod By: PT. SANTI WIJAYA MEAT</strong>
                    </span>
                </td>
            </tr>

            <tr>
                <td height="20" colspan="4">
                    <span style="color:#000;font-size:10px;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                        Perum Asabri Blok B No 20 Rt. 01/05 Ds. Sukasirna Kec. Jonggol Kab. Bogor
                    </span>
                </td>
            </tr>

            <tr>
                <td height="20" colspan="2">
                    <span style="font-size:18px;color:#000;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                        <strong><?= h($nmbarang); ?></strong>
                    </span>
                </td>
                <!-- Logo + teks di bawahnya (DALAM SEL YANG SAMA) -->
                <td colspan="2" rowspan="5" align="center" valign="middle">
                    <img src="../dist/img/halal.png" alt="HALAL" height="100" align="absmiddle">
                    <div style="font-size:10px;text-align:center;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;margin-top:6px;">
                        ID00110015321510124<br>RPHR 3201170-027
                    </div>
                </td>
            </tr>

            <tr>
                <td colspan="1" rowspan="2">
                    <span style="color:#000;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                        <span style="font-size:30px"><strong><?= number_format($qty, 2); ?></strong></span>
                    </span>
                </td>
                <td height="20" style="font-size:12px;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                    <?php if (!is_null($pcs) && (int)$pcs > 0): ?>
                        <strong><i><?= (int)$pcs; ?>-Pcs</i></strong>
                    <?php else: ?>
                        &nbsp;
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <td height="20" style="font-style:normal;font-size:12px;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                    <?php if (!is_null($ph) && $ph !== ''): ?>
                        <span style="font-size:12px">pH <?= number_format((float)$ph, 1); ?></span>
                    <?php else: ?>
                        &nbsp;
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <td height="20" style="font-size:11px">
                    <span style="color:#000;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">Packed Date&nbsp; :</span>
                </td>
                <td style="font-size:11px">
                    <span style="color:#000;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                        <?= h(date('d-M-Y', strtotime($pod))); ?>
                    </span>
                </td>
            </tr>

            <tr>
                <td height="20" colspan="2">
                    <span style="color:#000;font-size:12px;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                        <strong><?= in_array($idgrade, [1, 3], true) ? "KEEP CHILL 0°C" : "KEEP FROZEN -18°C"; ?></strong>
                    </span>
                </td>
                <!-- kolom kanan di baris ini DIKOSONGKAN karena sudah dipakai logo+teks -->
            </tr>

            <tr></tr>

            <tr>
                <td height="20" colspan="4" align="center" valign="middle">
                    <?php
                    try {
                        $generator = new Picqer\Barcode\BarcodeGeneratorJPG();
                        $img = $generator->getBarcode($kdbarcode, $generator::TYPE_CODE_128);
                        echo '<img src="data:image/jpeg;base64,' . base64_encode($img) . '" alt="Barcode">';
                    } catch (Throwable $e) {
                        echo '<small>Barcode tidak tersedia.</small>';
                    }
                    ?>
                </td>
            </tr>

            <tr>
                <td colspan="4" align="center">
                    <span style="color:#000;font-family:'Gill Sans','Gill Sans MT','Myriad Pro','DejaVu Sans Condensed',Helvetica,Arial,sans-serif;">
                        <?= h($kdbarcode); ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <script>
        window.onload = function() {
            window.print();
            window.onafterprint = function() {
                window.location.href = 'index.php';
            };
            setTimeout(function() {
                window.close();
            }, 500);
        };
    </script>
</body>

</html>