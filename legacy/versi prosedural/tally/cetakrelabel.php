<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../dist/vendor/autoload.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   http_response_code(405);
   exit('Method Not Allowed');
}

// --- Ambil parameter minimal ---
$idusers       = (int)($_SESSION['idusers'] ?? 0);
$kdbarcode     = trim($_POST['kdbarcode'] ?? '');
$idtally       = (int)($_POST['idtally'] ?? 0);
$idtallydetail = (int)($_POST['idtallydetail'] ?? 0);
$packdate      = $_POST['packdate'] ?? null;    // yyyy-mm-dd
$xpackdate     = $_POST['xpackdate'] ?? null;   // pod lama (opsional)
$ph_input      = $_POST['ph'] ?? null;          // "5.4" .. "5.7" atau kosong

// Ambil status checkbox Print Exp
$print_exp = isset($_POST['print_exp']) ? 1 : 0;

if ($idusers <= 0 || $kdbarcode === '' || $idtally <= 0 || $idtallydetail <= 0 || !$packdate) {
   exit('Parameter tidak lengkap.');
}

// Simpan preferensi checkbox ke session agar diingat sistem
$_SESSION['print_exp'] = $print_exp;

// --- Ambil record resmi dari tallydetail (kebenaran data) ---
$sql = "
SELECT td.idtallydetail, td.idtally, td.barcode,
       td.idbarang, td.idgrade, td.weight, td.pcs, td.pod,
       b.nmbarang
FROM tallydetail td
LEFT JOIN barang b ON b.idbarang = td.idbarang
WHERE td.idtallydetail = ? AND td.idtally = ? AND td.barcode = ?
LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) exit('DB Error (prepare)');
$stmt->bind_param('iis', $idtallydetail, $idtally, $kdbarcode);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) exit('Record tidak valid.');
$rec = $res->fetch_assoc();

// --- Nilai dari DB (dipakai untuk cetak & relabel) ---
$idbarang = (int)$rec['idbarang'];
$idgrade  = (int)$rec['idgrade'];
$nmbarang = (string)($rec['nmbarang'] ?? '');
$qty      = (float)$rec['weight'];   // DECIMAL(6,2)
$xpcs     = (int)$rec['pcs'];        // pcs lama (xpcs)

// --- Logic Perhitungan Expired Date ---
$exp = null;
if ($print_exp === 1) {
   $dateObj = new DateTime($packdate);
   // Jika Grade J01 (id 1) atau P01 (id 3) = 3 Bulan
   if ($idgrade === 1 || $idgrade === 3) {
      $dateObj->modify('+3 months');
   } else {
      // Selain itu = 1 Tahun
      $dateObj->modify('+1 year');
   }
   $exp = $dateObj->format('Y-m-d');
}

// --- Normalisasi input pH ---
$phFloat = null; // NULL jika dikosongkan
if ($ph_input !== null && $ph_input !== '') {
   $rawPh = str_replace(',', '.', (string)$ph_input);
   $phVal = filter_var($rawPh, FILTER_VALIDATE_FLOAT);
   if ($phVal === false) {
      exit('Nilai pH tidak valid.');
   }
   if ($phVal < 5.4 || $phVal > 5.7) {
      exit('Nilai pH harus antara 5.4 dan 5.7.');
   }
   // Truncate ke 1 desimal (bukan pembulatan)
   $phFloat = floor($phVal * 10) / 10;
}

// --- Update tallydetail: pod + ph + exp (ph/exp bisa NULL) ---
if (is_null($phFloat)) {
   // pH kosong -> set NULL
   $upd = $conn->prepare("UPDATE tallydetail SET pod = ?, ph = NULL, exp = ? WHERE idtallydetail = ? LIMIT 1");
   if (!$upd) exit('Gagal prepare update tallydetail (NULL ph): ' . $conn->error);
   $upd->bind_param('ssi', $packdate, $exp, $idtallydetail);
} else {
   // pH ada -> simpan angka 1 desimal
   $upd = $conn->prepare("UPDATE tallydetail SET pod = ?, ph = ?, exp = ? WHERE idtallydetail = ? LIMIT 1");
   if (!$upd) exit('Gagal prepare update tallydetail (ph angka): ' . $conn->error);
   $upd->bind_param('sdsi', $packdate, $phFloat, $exp, $idtallydetail);
}
if (!$upd->execute()) exit('Gagal update tallydetail: ' . $upd->error);
$upd->close();

// --- Insert ke relabel (untuk jejak cetak) ---
$pcs_str = (string)$xpcs; // relabel.pcs = CHAR(5)
$ins = $conn->prepare("
  INSERT INTO relabel
    (idbarang, qty, xpackdate, idgrade, ph, pcs, xpcs, packdate, exp, kdbarcode, iduser)
  VALUES
    (?,        ?,   ?,         ?,       ?,  ?,   ?,    ?,        ?,   ?,         ?)
");
if (!$ins) exit('Gagal prepare insert relabel: ' . $conn->error);
// tipe bind: i d s i d s i s s s i
$ins->bind_param(
   'idsidsisssi',
   $idbarang,   // i
   $qty,        // d
   $xpackdate,  // s (pod lama)
   $idgrade,    // i
   $phFloat,    // d (bisa NULL)
   $pcs_str,    // s
   $xpcs,       // i
   $packdate,   // s (pod baru)
   $exp,        // s (bisa NULL)
   $kdbarcode,  // s
   $idusers     // i
);
if (!$ins->execute()) exit('Gagal insert relabel: ' . $ins->error);
$ins->close();

// --- Helper aman HTML ---
function h($v)
{
   return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Validasi extra untuk render table
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
                  <span style="font-size:30px;"><strong><?= number_format($qty, 2); ?></strong><sup style="font-size:14px;">Kg</sup></span>
               </td>
               <td height="20" style="font-size: 12px;">
                  <?php if ($xpcs > 1) {
                     echo "<strong><i>" . (int)$xpcs . "-Pcs</i></strong>";
                  } else {
                     echo "&nbsp;";
                  } ?>
               </td>
            </tr>
            <tr>
               <td height="20" style="font-size: 12px;">
                  <?php if ($phFloat !== null): ?>
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
                  <span style="font-size: 12px;"><strong><?= ($idgrade == 1 || $idgrade == 3) ? "KEEP CHILL 0°C" : "KEEP FROZEN -18°C"; ?></strong></span>
               </td>
               <td style="font-size: 9px; text-align: center;">ID00110015321510124<br>RPHR 3201170-027</td>
            </tr>
            <tr>
               <td height="55" colspan="4" align="center" valign="middle" class="barcode-img">
                  <?php
                  try {
                     $generator = new Picqer\Barcode\BarcodeGeneratorJPG();
                     $barcodeImg = $generator->getBarcode($kdbarcode, $generator::TYPE_CODE_128);
                     echo '<img src="data:image/jpeg;base64,' . base64_encode($barcodeImg) . '" alt="Barcode">';
                  } catch (Throwable $e) {
                     echo '<small>Barcode error</small>';
                  }
                  ?>
               </td>
            </tr>
            <tr>
               <td colspan="4" align="center"><span style="font-size: 12px;"><?= h($kdbarcode); ?></span></td>
            </tr>
         </tbody>
      </table>
   </div>

   <script>
      window.onload = function() {
         window.print();
         window.onafterprint = function() {
            window.location.href = 'tallydetail.php?id=<?= (int)$idtally ?>&stat=updated';
         };
         setTimeout(function() {
            window.close();
         }, 500);
      };
   </script>
</body>

</html>