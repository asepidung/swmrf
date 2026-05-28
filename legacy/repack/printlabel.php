<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../dist/vendor/autoload.php";
require "seriallabelrepack.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   // --- Sanitasi dasar ---
   $idbarang  = mysqli_real_escape_string($conn, $_POST['idbarang']);
   $note      = mysqli_real_escape_string($conn, $_POST['note'] ?? '');
   $origin    = mysqli_real_escape_string($conn, $_POST['origin']);
   $idrepack  = mysqli_real_escape_string($conn, $_POST['idrepack']);
   $idgrade   = mysqli_real_escape_string($conn, $_POST['idgrade']);
   $packdate  = mysqli_real_escape_string($conn, $_POST['packdate']); // yyyy-mm-dd
   $ph_input  = $_POST['ph'] ?? '';    // "5.4".."5.7" atau ''

   $qtyPcsInput = $_POST['qty'];

   // Ambil status checkbox Print Exp
   $print_exp = isset($_POST['print_exp']) ? 1 : 0;

   // --- Bentuk barcode ---
   $barcode = $origin . $idrepack . $kodeauto;

   // Flag opsional
   $tenderstreachActive = isset($_POST['tenderstreach']);
   $pembulatan          = isset($_POST['pembulatan']);

   // --- Simpan untuk session (prefill selanjutnya) ---
   $_SESSION['idbarang']      = $idbarang;
   $_SESSION['idgrade']       = $idgrade;
   $_SESSION['packdate']      = $packdate;
   $_SESSION['origin']        = $origin;
   $_SESSION['tenderstreach'] = $tenderstreachActive;
   $_SESSION['pembulatan']    = $pembulatan;
   $_SESSION['print_exp']     = $print_exp; // Simpan memori checkbox

   // --- Logic Perhitungan Expired Date ---
   $exp = null;
   if ($print_exp === 1 && $packdate !== '') {
      $dateObj = new DateTime($packdate);
      // Jika Grade J01 (id 1) atau P01 (id 3) = 3 Bulan
      if ($idgrade == 1 || $idgrade == 3) {
         $dateObj->modify('+3 months');
      } else {
         // Selain itu = 1 Tahun
         $dateObj->modify('+1 year');
      }
      $exp = $dateObj->format('Y-m-d');
   }

   // --- Normalisasi & validasi pH (opsional) ---
   $phFloat = null;
   if ($ph_input !== null && $ph_input !== '') {
      $rawPh = str_replace(',', '.', (string)$ph_input);
      $phVal = filter_var($rawPh, FILTER_VALIDATE_FLOAT);
      if ($phVal === false) {
         die("Nilai pH tidak valid.");
      }
      if ($phVal < 5.4 || $phVal > 5.7) {
         die("Nilai pH harus antara 5.4 dan 5.7.");
      }
      // truncate ke 1 desimal
      $phFloat = floor($phVal * 10) / 10;
      $_SESSION['ph'] = number_format($phFloat, 1, '.', '');
   } else {
      $_SESSION['ph'] = '';
   }

   // --- Parsing qty/pcs ---
   $qty = null;
   $pcs = null;
   if (strpos($qtyPcsInput, "/") !== false) {
      list($qty, $pcs) = explode("/", $qtyPcsInput, 2);
   } else {
      $qty = $qtyPcsInput;
      $pcs = null;
   }

   // Normalisasi qty (koma→titik, 2 desimal)
   $qty = str_replace(',', '.', trim((string)$qty));
   $qty = is_numeric($qty) ? number_format((float)$qty, 2, '.', '') : null;

   // pcs ke integer (boleh null)
   if ($pcs !== null && $pcs !== '') {
      $pcs = preg_replace('/\D+/', '', (string)$pcs);
      $pcs = ($pcs === '') ? null : (int)$pcs;
   } else {
      $pcs = null;
   }

   if ($qty === null || (float)$qty <= 0) {
      die("Invalid quantity.");
   }

   // --- Siapkan nilai SQL aman untuk NULL ---
   $qtySql = $qty; // angka
   $pcsSql = is_null($pcs) ? "NULL" : (int)$pcs;
   $phSql  = is_null($phFloat) ? "NULL" : number_format($phFloat, 1, '.', '');
   $expSql = is_null($exp) ? "NULL" : "'" . mysqli_real_escape_string($conn, $exp) . "'";

   // --- Insert ke detailhasil ---
   $queryDetailhasil = "
    INSERT INTO detailhasil
      (idrepack, kdbarcode, idbarang, idgrade, qty, pcs, packdate, ph, exp, note)
    VALUES
      ('$idrepack', '$barcode', '$idbarang', '$idgrade', $qtySql, $pcsSql, '$packdate', $phSql, $expSql, '$note')
  ";
   if (!mysqli_query($conn, $queryDetailhasil)) {
      die("Error inserting into detailhasil: " . mysqli_error($conn));
   }

   // --- Insert ke stock ---
   $queryStock = "
    INSERT INTO stock
      (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin, ph)
    VALUES
      ('$barcode', '$idgrade', '$idbarang', $qtySql, $pcsSql, '$packdate', '$origin', $phSql)
  ";
   if (!mysqli_query($conn, $queryStock)) {
      die("Error inserting into stock: " . mysqli_error($conn));
   }
}

// --- Ambil nama barang untuk label ---
$nmbarang = '';
if (!empty($idbarang)) {
   $stmtBarang = mysqli_prepare($conn, "SELECT nmbarang FROM barang WHERE idbarang = ?");
   mysqli_stmt_bind_param($stmtBarang, "i", $idbarang);
   mysqli_stmt_execute($stmtBarang);
   mysqli_stmt_bind_result($stmtBarang, $nmbarang);
   mysqli_stmt_fetch($stmtBarang);
   mysqli_stmt_close($stmtBarang);
}

// Validasi extra untuk render table
$show_exp = (!empty($exp) && $exp !== '0000-00-00');

function h($v)
{
   return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
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

      /* 3. Container Margin */
      .print-area {
         width: 100mm;
         height: 75mm;
         padding: 3mm;
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
               <td height="20" colspan="2"><span style="font-size: 18px;"><strong><?= strtoupper(h($nmbarang)); ?></strong></span></td>
               <td colspan="2" rowspan="5" align="center" valign="middle">
                  <img src="../dist/img/halal.png" alt="HALAL" height="90">
               </td>
            </tr>
            <tr>
               <td colspan="1" rowspan="2">
                  <span style="color:#000;">
                     <?php if (!empty($pembulatan)) { ?>
                        <span style="font-size:30px"><strong><?= number_format((float)$qty, 1); ?></strong></span>
                     <?php } else { ?>
                        <span style="font-size:30px;">
                           <strong><?= number_format($qty, 2); ?></strong>
                           <sup style="font-size:14px;">Kg</sup>
                        </span>
                     <?php } ?>
                  </span>
               </td>
               <td height="20" style="font-size: 12px;">
                  <?php if ($pcs > 0) { ?>
                     <strong><i><?= $pcs . "-Pcs"; ?></i></strong>
                  <?php } else {
                     echo "&nbsp;";
                  } ?>
               </td>
            </tr>
            <tr>
               <td height="20" style="font-size: 12px;">
                  <?php if ($phFloat !== null) : ?>
                     pH <?= number_format($phFloat, 1); ?>
                  <?php else: ?>
                     &nbsp;
                  <?php endif; ?>
               </td>
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
                  <span style="font-size: 12px;">
                     <strong><?= ($idgrade == 1 || $idgrade == 3) ? "KEEP CHILL 0°C" : "KEEP FROZEN -18°C"; ?></strong>
                  </span>
               </td>
               <td style="font-size: 9px; text-align: center;">ID00110015321510124<br>RPHR 3201170-027</td>
            </tr>
            <tr>
               <td height="55" colspan="4" align="center" valign="middle" class="barcode-img">
                  <?php
                  try {
                     $generator = new Picqer\Barcode\BarcodeGeneratorJPG();
                     $label = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);
                     echo '<img src="data:image/jpeg;base64,' . base64_encode($label) . '" alt="Barcode">';
                  } catch (Throwable $e) {
                     echo '<small>Barcode error</small>';
                  }
                  ?>
               </td>
            </tr>
            <tr>
               <td colspan="4" align="center"><span style="font-size: 12px;"><?= h($barcode); ?></span></td>
            </tr>
         </tbody>
      </table>
   </div>

   <script>
      window.onload = function() {
         window.print();
         window.onafterprint = function() {
            window.location.href = 'detailhasil.php?id=<?= (int)$idrepack ?>';
         };
         setTimeout(function() {
            window.close();
         }, 500);
      };
   </script>
</body>

</html>