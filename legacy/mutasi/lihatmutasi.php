<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$id = $_GET['id'];
// Query untuk mengambil data dari tabel do
$query = "SELECT * FROM mutasi WHERE idmutasi = '$id'";
$result = mysqli_query($conn, $query);
$row_mutasi = mysqli_fetch_assoc($result);


// Query untuk mengambil data dari tabel mutasidetail berdasarkan id
$query_detail = "SELECT mutasidetail.idbarang, barang.nmbarang, 
                        SUM(mutasidetail.qty) as total_qty,
                        COUNT(mutasidetail.qty) as total_box
                FROM mutasidetail
                INNER JOIN barang ON mutasidetail.idbarang = barang.idbarang
                WHERE idmutasi = '$id'
                GROUP BY mutasidetail.idbarang";
$result_detail = mysqli_query($conn, $query_detail);
$xbox = 0;
$xweight = 0

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
         font-family: Cambria, sans-serif;
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
   <p>Mutasi Order<br />
      <strong>PT. SANTI WIJAYA MEAT</strong><br />
      Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103
   </p>
   <hr />
   <table width="100%" border="0">
      <tr>
         <td width="12%">Mutasi Number</td>
         <td width="2%" align="right">:</td>
         <td width="30%"><?= $row_mutasi['nomutasi']; ?></td>
         <td width="12%">Delivery Date</td>
         <td width="2%" align="right">:</td>
         <td width="30%"><?= date('d-M-Y', strtotime($row_mutasi['tglmutasi'])); ?></td>
      </tr>
      <tr>
         <td width="12%">Driver</td>
         <td width="2%" align="right">:</td>
         <td width="30%"> <?= $row_mutasi['driver']; ?></td>
         <td width="12%">Gudang Tujuan</td>
         <td width="2%" align="right">:</td>
         <td width="30%"><?= $row_mutasi['gudang']; ?></td>
      </tr>
      <tr>
         <td width="12%">No POL</td>
         <td width="2%" align="right">:</td>
         <td width="30%"> <?= $row_mutasi['nopol']; ?></td>
         <td class="border-collapse" width="12%" valign="top">Address</td>
         <td class="border-collapse" width="2%" valign="top" align="right">:</td>
         <td width="30%" align="justify" valign="top" rowspan="2">
            <?php
            if ($row_mutasi['gudang'] == "PERUM") {
               echo "Jl. Perum Asabri Blok B Desa Sukasirna Kec. Jonggol Kab. Bogor Telp. 021-89935103";
            } else {
               echo "RPHR Jonggol Jl. SMPN 1 Jonggol Kp. Menan Rt. 04/01 Ds. Sukamaju Kec. Jonggol - Bogor";
            }
            ?>
         </td>
      </tr>
      <tr height="30px">
         <td width="12%"></td>
         <td width="2%"></td>
         <td width="30%"></td>
         <td width="12%"></td>
         <td width="2%"></td>
      </tr>
   </table>
   <br>
   <table width="100%" border="1" cellpadding="2" class="border-collapse">
      <tr>
         <th width="5%">No</th>
         <th width="30%">Item Descriptions</th>
         <th width="10%">Box</th>
         <th width="15%">Weight</th>
      </tr>
      <?php
      $no = 1; // Inisialisasi nomor urut
      while ($row_detail = mysqli_fetch_assoc($result_detail)) {
         $xweight += $row_detail['total_qty'];
         $xbox += $row_detail['total_box'];
      ?>
         <tr align="center">
            <td><?= $no ?></td>
            <td align="left"><span class="data"><?= $row_detail['nmbarang']; ?></span></td>
            <td><?= $row_detail['total_box'] ?></td>
            <td align="right"><span class="data"><?= number_format($row_detail['total_qty'], 2); ?></span></td>
         </tr>
      <?php
         $no++; // Increment nomor urut
      }
      ?>
      <tr align="right">
         <th colspan="2">Total</th>
         <th align="center"><?= $xbox; ?></th>
         <th><span class="data"><?= number_format(($xweight), 2); ?></span></th>
      </tr>
   </table>
   <i>
      <p align="justify" class="half-width">
         <strong>Note !</strong><br>
         <?php
         if ($row_mutasi['note'] !== "") {
            echo $row_mutasi['note'];
         } else {
            echo "-";
         }
         ?>
      </p>
   </i>
   <table width="100%">
      <tr align="center">
         <td width="20%">Warehouse <br><br><br><br><br> ....................................</td>
         <td width="20%">QC/QA <br><br><br><br><br> ....................................</td>
         <td width="20%">Driver <br><br><br><br><br> ....................................</td>
         <td width="20%">Security <br><br><br><br><br> ....................................</td>
         <td width="20%">Customer <br><br><br><br><br> ....................................</td>
      </tr>
   </table>
   <script>
      document.title = "<?php echo "Mutasi" . " " . $row_mutasi['nomutasi']; ?>";
   </script>
</body>

</html>