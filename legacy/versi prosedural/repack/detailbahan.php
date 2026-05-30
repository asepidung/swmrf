<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
$idrepack = $_GET['id'];

?>
<div class="content-header">
   <div class="container-fluid">
      <div class="row">
         <div class="col-3">
            <a href="index.php"><button type="button" class="btn btn-outline-primary"><i class="fas fa-arrow-alt-circle-left"></i> Kembali</button></a>
            <?php
            // Query untuk memeriksa apakah ada data di tabel detailbahan
            $queryDetailBahan = "SELECT COUNT(*) AS total FROM detailbahan WHERE idrepack = $idrepack";
            $resultDetailBahan = mysqli_query($conn, $queryDetailBahan);
            $rowDetailBahan = mysqli_fetch_assoc($resultDetailBahan);

            // Cek jika tidak ada data di detailbahan, maka tombol disabled
            $disabled = ($rowDetailBahan['total'] == 0) ? 'disabled' : '';
            ?>

            <a href="detailhasil.php?id=<?= $idrepack ?>" class="btn btn-outline-success <?= $disabled; ?>" title="Cetak Hasil" <?= $disabled ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
               Cetak Hasil <i class="fas fa-arrow-alt-circle-right"></i>
            </a>

         </div>
         <div class="col">
            <span class="text-danger text-center">
               <h4>BAHAN REPACK</h4>
            </span>
         </div>
      </div>
   </div>
</div>
<!-- Main content -->
<section class="content">
   <div class="container-fluid">
      <div class="row">
         <div class="col">
            <form method="POST" action="inputdetailbahan.php">
               <div class="card">
                  <div class="card-body">
                     <div id="items-container">
                        <div class="row mb-n2">
                           <div class="col-xs-2">
                              <div class="form-group">
                                 <input type="number" placeholder="Scan Here" class="form-control text-center" name="barcode" id="barcode" autofocus>
                              </div>
                           </div>
                           <input type="hidden" name="idrepack" value="<?= $idrepack ?>">
                           <div class="col-1">
                              <div class="form-group">
                                 <button type="submit" class="btn btn-primary">Submit</button>
                              </div>
                           </div>
                           <div class="col-3">
                              <?php if ($_GET['stat'] == "success") { ?>
                                 <h3 class="headline text-success"><i class="fas fa-check-circle"></i> Success</h3>
                              <?php } elseif ($_GET['stat'] == "ready") { ?>
                                 <h3 class="headline text-secondary"> Ready To Scan</h3>
                              <?php } elseif ($_GET['stat'] == "deleted") { ?>
                                 <h3 class="headline text-success"> Data berhasil dihapus</h3>
                              <?php } elseif ($_GET['stat'] == "duplicate") { ?>
                                 <h3 class="headline text-warning"><i class="fas fa-exclamation-triangle"></i> Barang Sudah Terinput</h3>
                              <?php } elseif ($_GET['stat'] == "unknown") { ?>
                                 <a href="bahanmanual.php?id=<?= $idrepack ?>">
                                    <span class="headline text-danger">BARANG TIDAK TERDAFTAR <br>
                                       Manual ADD <i class="fas fa-arrow-circle-right"></i>
                                    </span>
                                 </a>
                              <?php } ?>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </form>
            <div class="row">
               <div class="col-lg-8">
                  <div class="card">
                     <div class="card-body">
                        <?php
                        $no = 1;
                        // Contoh query SQL dengan JOIN untuk mengambil data detailbahan dan nama barang
                        $query = "SELECT detailbahan.*, barang.nmbarang
                        FROM detailbahan
                        JOIN barang ON detailbahan.idbarang = barang.idbarang
                        WHERE detailbahan.idrepack = $idrepack
                        ORDER BY detailbahan.creatime DESC, detailbahan.iddetailbahan DESC";
                        $result = mysqli_query($conn, $query);

                        // Mengecek apakah query berhasil dijalankan
                        if (!$result) {
                           die("Query Error: " . mysqli_error($conn));
                        }
                        ?>
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>#</th>
                                 <th>Barcode</th>
                                 <th>Item</th>
                                 <th>Kg</th>
                                 <th>Pcs</th>
                                 <th>POD</th>
                                 <th>Origin</th>
                                 <th>Hapus</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              // Loop untuk menampilkan data ke dalam tabel HTML
                              while ($row = mysqli_fetch_assoc($result)) {
                                 $barcode = $row['barcode'];
                                 $nmbarang = $row['nmbarang'];
                                 $qty = $row['qty'];
                                 $pod = $row['pod'];
                                 $origin = $row['origin'];
                              ?>
                                 <tr class="text-center">
                                    <td><?= $no; ?></td>
                                    <td><?= $barcode ?></td>
                                    <td class="text-left"><?= $nmbarang; ?></td>
                                    <td><?= $qty; ?></td>
                                    <?php
                                    if ($row['pcs'] < 1) {
                                       $pcs = "";
                                    } else {
                                       $pcs = $row['pcs'];
                                    }
                                    ?>
                                    <td><?= $pcs; ?></td>
                                    <td><?= $pod; ?></td>
                                    <td>
                                       <?php
                                       // Konversi nilai origin menjadi teks
                                       switch ($origin) {
                                          case 1:
                                             echo "BONING";
                                             break;
                                          case 2:
                                             echo "TRADING";
                                             break;
                                          case 3:
                                             echo "REPACK";
                                             break;
                                          case 4:
                                             echo "RELABEL";
                                             break;
                                          default:
                                             echo "Unidentified";
                                             break;
                                       }
                                       ?>
                                    </td>
                                    <td class="text-center">
                                       <a href="deletedetailbahan.php?iddetail=<?= $row['iddetailbahan']; ?>&id=<?= $idrepack; ?>" class="text-danger" onclick="return confirm('Yakin?')">
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
               <div class="col-lg-4">
                  <div class="card">
                     <div class="card-body">
                        <strong>BAHAN</strong>
                        <table class="table table-bordered table-striped table-sm mb-3">
                           <thead class="text-center">
                              <tr>
                                 <th>NAMA BARANG</th>
                                 <th>BOX</th>
                                 <th>QTY</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $query = "SELECT detailbahan.idbarang, barang.nmbarang, SUM(detailbahan.qty) AS total_qty, COUNT(detailbahan.qty) AS count_qty
                              FROM detailbahan
                              INNER JOIN barang ON detailbahan.idbarang = barang.idbarang
                              WHERE detailbahan.idrepack = $idrepack
                              GROUP BY detailbahan.idbarang, barang.nmbarang";
                              $result = mysqli_query($conn, $query);
                              while ($row = mysqli_fetch_assoc($result)) { ?>
                                 <tr>
                                    <td><?= $row['nmbarang'] ?></td>
                                    <td class="text-center"><?= $row['count_qty'] ?></td>
                                    <td class="text-right"><?= number_format($row['total_qty'], 2) ?></td>
                                 </tr>
                              <?php }
                              ?>
                           </tbody>
                           <tfoot>
                              <?php
                              $queryhasil = "SELECT SUM(detailbahan.qty) AS hasilqty, COUNT(detailbahan.qty) AS hasilbox
                              FROM detailbahan
                              WHERE detailbahan.idrepack = $idrepack";
                              $resulthasil = mysqli_query($conn, $queryhasil);
                              $rowhasil = mysqli_fetch_assoc($resulthasil);
                              ?>
                              <tr class="text-right">
                                 <th>TOTAL</th>
                                 <th class="text-center"><?= $rowhasil['hasilbox']; ?></th>
                                 <th><?= isset($rowhasil['hasilqty']) ? number_format($rowhasil['hasilqty'], 2) : '0.00'; ?></th>
                              </tr>
                           </tfoot>
                        </table>
                        <strong>HASIL</strong>
                        <table class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>NAMA BARANG</th>
                                 <th>BOX</th>
                                 <th>QTY</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $query = "SELECT detailhasil.idbarang, barang.nmbarang, 
                SUM(detailhasil.qty) AS total_qty, 
                COUNT(detailhasil.qty) AS count_qty
                FROM detailhasil
                INNER JOIN barang ON detailhasil.idbarang = barang.idbarang
                WHERE detailhasil.idrepack = $idrepack AND detailhasil.is_deleted = 0
                GROUP BY detailhasil.idbarang, barang.nmbarang";
                              $result = mysqli_query($conn, $query);
                              while ($row = mysqli_fetch_assoc($result)) { ?>
                                 <tr>
                                    <td><?= $row['nmbarang'] ?></td>
                                    <td class="text-center"><?= $row['count_qty'] ?></td>
                                    <td class="text-right"><?= number_format($row['total_qty'], 2) ?></td>
                                 </tr>
                              <?php }
                              ?>
                           </tbody>
                           <tfoot>
                              <?php
                              $queryhasil = "SELECT SUM(detailhasil.qty) AS hasilqty, COUNT(detailhasil.qty) AS hasilbox
                     FROM detailhasil
                     WHERE detailhasil.idrepack = $idrepack AND detailhasil.is_deleted = 0";
                              $resulthasil = mysqli_query($conn, $queryhasil);
                              $rowhasil = mysqli_fetch_assoc($resulthasil);
                              ?>
                              <tr class="text-right">
                                 <th>TOTAL</th>
                                 <th class="text-center"><?= $rowhasil['hasilbox']; ?></th>
                                 <th><?= isset($rowhasil['hasilqty']) ? number_format($rowhasil['hasilqty'], 2) : '0.00'; ?></th>
                              </tr>
                           </tfoot>
                        </table>

                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
<script>
   document.title = "REPACK";
</script>
<?php
include "../footer.php" ?>