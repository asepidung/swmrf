<?php
require "../verifications/auth.php";
require "../konak/conn.php";
$idtally = $_GET['id'];
require "hitungantally.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Document</title>
   <style>
      .data {
         padding-right: 5px;
         padding-left: 5px;
         /* Ubah angka sesuai kebutuhan Anda */
      }

      body {
         font-family: poppins, sans-serif;
         font-size: 14px;
      }

      .border-collapse {
         border-collapse: collapse;
      }

      .half-width {
         width: 50%;
      }

      .small-text {
         font-size: 12px;
      }
   </style>
</head>

<body>
   <p align="center">TALY SHEET<br />
      <strong>Taly Number : <?= $row_tally['notally']; ?></strong>
   </p>
   <table width="100%">
      <tr>
         <td width="12%">Customer</td>
         <td width="2%">:</td>
         <td width="25%"><?= $row_tally['nama_customer']; ?></td>
         <td width="12%">Delivery Date</td>
         <td width="2%">:</td>
         <td width="30%"><?= date('d-M-Y', strtotime($row_tally['deliverydate'])); ?></td>
      </tr>
      <tr>
         <td width="12%">PO Numb</td>
         <td width="2%">:</td>
         <td width="25%"><?= $row_tally['po']; ?></td>
         <td width="12%">SO Numb</td>
         <td width="2%">:</td>
         <td width="30%"><?= $row_tally['sonumber']; ?></td>
      </tr>
   </table>
   <br>
   <table width="100%" border="1" cellpadding="2" style="border-collapse: collapse; border: 1px solid black;">
      <tr align="center">
         <th>Product</th>
         <th>01</th>
         <th>02</th>
         <th>03</th>
         <th>04</th>
         <th>05</th>
         <th>06</th>
         <th>07</th>
         <th>08</th>
         <th>09</th>
         <th>10</th>
         <th>TOTAL</th>
      </tr>
      <?php
      foreach ($productData as $productName => $data) {
         $weightArray = $data['weights'];
         $count = count($weightArray);
         $rowsNeeded = ceil($count / 10);

         for ($rowIndex = 0; $rowIndex < $rowsNeeded; $rowIndex++) {
            echo '<tr align="center">';
            if ($rowIndex === 0) {
               echo '<td align="left">' . $productName . '</td>';
            } else {
               echo '<td></td>';
            }

            for ($i = 0; $i < 10; $i++) {
               $weightIndex = ($rowIndex * 10) + $i;
               if (isset($weightArray[$weightIndex])) {
                  echo '<td>' . $weightArray[$weightIndex] . '</td>';
               } else {
                  echo '<td></td>';
               }
            }

            if ($rowIndex == $rowsNeeded - 1) {
               echo '<td align="right"><strong>' . number_format($data['total'], 2) . '</strong></td>';
            } else {
               echo '<td></td>';
            }

            echo '</tr>';
         }
      }
      ?>
      <tr align="right">
         <th colspan="10" align="right">TOTAL</th>
         <th align="center"><?= $totalBox ?></th>
         <th><?= number_format($totalQty, 2) ?></th>
      </tr>
   </table>
   <br>
   <br>
   <table width="100%">
      <tr align="center">
         <td width="30%">WAREHOUSE <br><br><br><br><br> ( ..................................... )</td>
         <td width="30%">QC/QA <br><br><br><br><br> ( ..................................... )</td>
         <td width="30%">CUSTOMER <br><br><br><br><br> ( ..................................... )</td>
      </tr>
   </table>
   <script>
      // Trigger the print dialog when the page loads
      window.onload = function() {
         window.print();
      };

      // Redirect to the previous page after printing
      window.onafterprint = function() {
         window.history.back();
      };

      document.title = "Tally No : <?= $row_tally['notally']; ?>";
   </script>

</body>