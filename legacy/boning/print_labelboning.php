<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../dist/vendor/autoload.php";

// Validasi dan sanitasi input
if (!isset($_GET['idlabelboning']) || empty($_GET['idlabelboning'])) {
   die('Error: idlabelboning is missing or invalid.');
}
$idlabelboning = intval($_GET['idlabelboning']);
$idboning = intval($_GET['idboning']);

// Ambil data label dan barang berdasarkan idlabelboning
$query = "SELECT lb.*, b.nmbarang 
          FROM labelboning lb 
          JOIN barang b ON lb.idbarang = b.idbarang 
          WHERE lb.idlabelboning = $idlabelboning";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
   header("Location: labelboning.php?id=$idboning");
   exit;
}

// Variabel
$nmbarang = $data['nmbarang'];
$qty = $data['qty'];
$pcs = $data['pcs'];
$packdate = $data['packdate'];
$exp = $data['exp'];
$idgrade = $data['idgrade'];
$kdbarcode = $data['kdbarcode'];
$ph = $data['ph'];
$show_exp = (!empty($exp) && $exp !== '0000-00-00');
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <title>Label Print</title>
   <style>
      /* 1. Atur Font Global ke Arial biar selaras */
      * {
         font-family: Arial, Helvetica, sans-serif !important;
         color: #000000;
      }

      body {
         margin: 0;
         padding: 0;
      }

      /* 2. Setting Kertas 100x75mm */
      @media print {
         @page {
            size: 100mm 75mm;
            margin: 0;
         }
      }

      /* 3. Container Margin (Sama kayak Laravel) */
      .print-area {
         width: 100mm;
         height: 75mm;
         padding: 3mm;
         /* Ini yang bikin margin lu aman */
         box-sizing: border-box;
         overflow: hidden;
      }

      /* 4. Paksa tabel lu tetep di ukurannya tapi ngikut container */
      .legacy-table {
         width: 100%;
         height: 100%;
         border-collapse: collapse;
      }

      /* Styling Barcode biar gak meluber */
      .barcode-img img {
         max-width: 100%;
         height: 50px !important;
      }
   </style>
</head>

<body>
   <div class="print-area">
      <table class="legacy-table" cellpadding="0" border="0">
         <tbody>
            <tr>
               <td height="23" colspan="4"><span style="font-size: 18px;"><strong>*YP*</strong></span></td>
            </tr>
            <tr>
               <td height="21" colspan="4"><span style="font-size: 14px;"><strong>Prod By: PT. SANTI WIJAYA MEAT</strong></span></td>
            </tr>
            <tr>
               <td height="20" colspan="4"><span style="font-size: 10px;">Perum Asabri Blok B No 20 Rt. 01/05 Ds. Sukasirna Kec. Jonggol Kab. Bogor</span></td>
            </tr>
            <tr>
               <td height="20" colspan="2"><span style="font-size: 18px;"><strong><?= strtoupper($nmbarang); ?></strong></span></td>
               <td colspan="2" rowspan="5" align="center" valign="middle">
                  <img src="../dist/img/halal.png" alt="HALAL" height="90">
               </td>
            </tr>
            <tr>
               <td colspan="1" rowspan="2">
                  <span style="font-size:30px;"><strong><?= number_format($qty, 2); ?></strong><sup style="font-size:14px;">Kg</sup></span>
               </td>
               <td height="20" style="font-size: 12px;">
                  <?php if ($pcs > 1) {
                     echo "<strong><i>$pcs-Pcs</i></strong>";
                  } else {
                     echo "&nbsp;";
                  } ?>
               </td>
            </tr>
            <tr>
               <td height="20" style="font-size: 12px;">pH <?= number_format($ph, 1); ?></td>
            </tr>
            <tr>
               <td height="20" style="font-size: 11px;">Packed Date :</td>
               <td style="font-size: 11px;"><?= date('d-M-Y', strtotime($packdate)); ?></td>
            </tr>
            <tr>
               <td style="font-size: 11px;"><?= $show_exp ? "Expired Date :" : "&nbsp;"; ?></td>
               <td style="font-size: 11px;"><?= $show_exp ? date('d-M-Y', strtotime($exp)) : "&nbsp;"; ?></td>
            </tr>
            <tr>
               <td height="20" colspan="2">
                  <span style="font-size: 12px;"><strong><?= ($idgrade == 1 || $idgrade == 3) ? "KEEP CHILL 0°C" : "KEEP FROZEN -18°C"; ?></strong></span>
               </td>
               <td style="font-size: 9px; text-align: center;">ID00110015321510124<br>RPHR 3201170-027</td>
            </tr>
            <tr>
               <td height="55" colspan="4" align="center" valign="middle" class="barcode-img">
                  <?php
                  $generator = new Picqer\Barcode\BarcodeGeneratorJPG();
                  $barcode = $generator->getBarcode($kdbarcode, $generator::TYPE_CODE_128);
                  echo '<img src="data:image/jpeg;base64,' . base64_encode($barcode) . '" alt="Barcode">';
                  ?>
               </td>
            </tr>
            <tr>
               <td colspan="4" align="center"><span style="font-size: 12px;"><?= $kdbarcode; ?></span></td>
            </tr>
         </tbody>
      </table>
   </div>

   <script>
      window.onload = function() {
         window.print();
         window.onafterprint = function() {
            window.location.href = 'labelboning.php?id=<?= $idboning; ?>';
         };
         setTimeout(function() {
            window.close();
         }, 500);
      };
   </script>
</body>

</html>