<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

$idpricelist = $_GET['idpricelist'];

$query = "SELECT pricelist.*, groupcs.nmgroup 
          FROM pricelist 
          JOIN groupcs ON pricelist.idgroup = groupcs.idgroup 
          WHERE pricelist.idpricelist = $idpricelist";

$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Price List</title>

</head>

<body>
   <div class="container">
      <div class="row mb-2">
         <img src="../dist/img/headerquo.png" alt="quotations" class="img-fluid">
      </div>
      <h3 class="text-center">PRICE LIST</h3>
      <table class="table table-responsive table-borderless table-sm">
         <tr>
            <td>Customer</td>
            <td>:</td>
            <th><?= $row['nmgroup']; ?></th>
         </tr>
         <tr>
            <td>CP</td>
            <td>:</td>
            <th><?= $row['up']; ?></th>
         </tr>
         <tr>
            <td>Price Update</td>
            <td>:</td>
            <th><?= date("d-M-y", strtotime($row['latestupdate'])); ?></th>
         </tr>
      </table>
      <table class="table table-sm table-striped table-bordered">
         <thead class="thead-dark">
            <tr class="text-center">
               <th>#</th>
               <th>Product Desc</th>
               <th>Product Kategory</th>
               <th>Brand</th>
               <th>Price</th>
               <th>Notes</th>
            </tr>
         </thead>
         <tbody>
            <?php
            $no = 1;
            $query_pricelistdetail = "SELECT pricelistdetail.*, barang.nmbarang, cuts.nmcut
           FROM pricelistdetail
           INNER JOIN barang ON pricelistdetail.idbarang = barang.idbarang
           LEFT JOIN cuts ON barang.idcut = cuts.idcut
           WHERE idpricelist = '$idpricelist'";
            $result_pricelistdetail = mysqli_query($conn, $query_pricelistdetail);
            while ($row_pricelistdetail = mysqli_fetch_assoc($result_pricelistdetail)) { ?>
               <tr>
                  <td class="text-center"><?= $no; ?></td>
                  <td><?= $row_pricelistdetail['nmbarang']; ?></td>
                  <td><?= $row_pricelistdetail['nmcut']; ?></td>
                  <td class="text-center">Wijaya Meat</td>
                  <td class="text-right"><?= number_format($row_pricelistdetail['price']); ?></td>
                  <td><?= $row_pricelistdetail['notes']; ?></td>
               </tr>
            <?php $no++;
            } ?>
         </tbody>
      </table>
      <div class="row">
         <div class="col-6">
            Informasi Lebih Lanjut Silahkan Hubungi <br> Muryani 0818 0898 5323<br>yani@wijayameat.co.id
         </div>
      </div>
      <div class="row">
         <div class="col-9"></div>
         <div class="col-1">
            <a href="cetakpricelist.php?idpricelist=<?= $idpricelist ?>">
               <button type="button" class="btn btn-block btn-primary"><i class="fas fa-print"></i> Print</button>
            </a>
         </div>
         <div class="col-1">
            <a href="editpricelist.php?idpricelist=<?= $idpricelist ?>">
               <button type="button" class="btn btn-block btn-warning"><i class="fas fa-edit"></i> Edit</button>
            </a>
         </div>
         <div class="col-1">
            <a href="index.php">
               <button type="button" class="btn btn-block btn-success"><i class="fas fa-undo"></i> Back</button>
            </a>
         </div>
      </div>
   </div>

</body>
<script>
   document.title = "<?= $row['nama_customer'] . " " . "Price List" ?>"
</script>
<?php

include "../footer.php"
?>