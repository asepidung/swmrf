<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
$id = $_GET['id'];
?>
<div class="content-header">
   <div class="container-fluid">
      <div class="row">
         <div class="col">
            <a href="index.php"><button type="button" class="btn btn-outline-primary"><i class="fas fa-arrow-alt-circle-left"></i> Summary</button></a>
            <a href="mutasikan.php?id=<?= $id ?>"><button type="button" class="btn btn-outline-warning"><i class="fas fa-arrow-alt-circle-right"></i> Mutasikan </button></a>
         </div>
      </div>
   </div>
</div>
<!-- Main content -->
<section class="content">
   <div class="container-fluid">
      <div class="row">
         <div class="col">
            <form method="POST" action="inputmtdetail.php">
               <div class="card">
                  <div class="card-body">
                     <div id="items-container">
                        <div class="row mb-n2">
                           <div class="col-xs-2">
                              <div class="form-group">
                                 <input type="text" placeholder="Scan Here" class="form-control text-center" name="kdbarcode" id="kdbarcode" autofocus>
                              </div>
                           </div>
                           <input type="hidden" name="idmutasi" value="<?= $id ?>">
                           <div class="col-1">
                              <div class="form-group">
                                 <button type="submit" class="btn btn-block btn-primary">Submit</button>
                              </div>
                           </div>
                           <div class="col">
                              <?php if ($_GET['stat'] == "success") { ?>
                                 <h3 class="headline text-success"><i class="fas fa-check-circle"></i> Success</h3>
                              <?php } elseif ($_GET['stat'] == "ready") { ?>
                                 <h3 class="headline text-secondary"> Ready To Scan</h3>
                              <?php } elseif ($_GET['stat'] == "deleted") { ?>
                                 <h3 class="headline text-success"> Data berhasil dihapus</h3>
                              <?php } elseif ($_GET['stat'] == "duplicate") { ?>
                                 <h3 class="headline text-warning"><i class="fas fa-exclamation-triangle"></i> Barang Sudah Terinput</h3>
                              <?php } elseif ($_GET['stat'] == "unlisted") { ?>
                                 <h3 class="headline text-danger"><i class="fas fa-times-circle"></i> Barang Tidak ada di PO</h3>
                              <?php } elseif ($_GET['stat'] == "unknown") { ?>
                                 <a href="inputmanual.php?id=<?= $id ?>">
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
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>#</th>
                                 <th>Barcode</th>
                                 <th>Item</th>
                                 <th>Grade</th>
                                 <th>Weight</th>
                                 <th>Pcs</th>
                                 <th>POD</th>
                                 <th>Origin</th>
                                 <th>Hapus</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $ambildata = mysqli_query($conn, "SELECT mutasidetail.*, barang.nmbarang, grade.nmgrade
                              FROM mutasidetail
                              INNER JOIN barang ON mutasidetail.idbarang = barang.idbarang
                              LEFT JOIN grade ON mutasidetail.idgrade = grade.idgrade
                              WHERE idmutasi = $id ORDER BY idmutasidetail DESC");

                              while ($tampil = mysqli_fetch_array($ambildata)) {
                                 $kdbarcode = $tampil['kdbarcode'];
                                 $origin = substr($kdbarcode, 0, 1);
                                 $nmbarang = $tampil['nmbarang'];
                                 $pod =  $tampil['pod'];
                                 // Create DateTime objects for each iteration
                                 $podDate = new DateTime($pod);
                                 $currentDate = new DateTime();
                                 $umur = $currentDate->diff($podDate)->days;
                              ?>
                                 <tr>
                                    <td class="text-center"><?= $no; ?></td>
                                    <td class="text-center"><?= $kdbarcode; ?></td>
                                    <td><?= $nmbarang; ?></td>
                                    <td class="text-center"><?= $tampil['nmgrade']; ?></td>
                                    <td class="text-right"><?= $tampil['qty']; ?></td>
                                    <td class="text-center"><?= $tampil['pcs']; ?></td>
                                    <td class="text-center" title="<?= date("d-M-y", strtotime($pod)); ?>"><?= $umur; ?></td>
                                    <td>
                                       <?php
                                       if ($origin == 1) {
                                          echo "BONING";
                                       } elseif ($origin == 2) {
                                          echo "TRADING";
                                       } elseif ($origin == 3) {
                                          echo "REPACK";
                                       } elseif ($origin == 4) {
                                          echo "RELABEL";
                                       } elseif ($origin == 5) {
                                          echo "IMPORT";
                                       } else {
                                          echo "Unindentified";
                                       }
                                       ?>
                                    </td>
                                    <td class="text-center">
                                       <a href="deletemtdetail.php?idmutasidetail=<?= $tampil['idmutasidetail']; ?>&id=<?= $id; ?>" class="text-danger" onclick="return confirm('Yakinkan Dirimu?')">
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
                        <table class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th>No</th>
                                 <th>Prod</th>
                                 <th>Qty</th>
                                 <th>Box</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $ambildata = mysqli_query($conn, "SELECT mutasidetail.*, barang.nmbarang, grade.nmgrade
                              FROM mutasidetail
                              INNER JOIN barang ON mutasidetail.idbarang = barang.idbarang
                              LEFT JOIN grade ON mutasidetail.idgrade = grade.idgrade
                              WHERE idmutasi = $id
                              ORDER BY idmutasidetail DESC");

                              // Inisialisasi array untuk mengelompokkan data berdasarkan idbarang
                              $groupedData = array();

                              while ($tampil = mysqli_fetch_array($ambildata)) {
                                 $idbarang = $tampil['idbarang'];

                                 // Menyusun data dalam array berdasarkan idbarang
                                 if (!isset($groupedData[$idbarang])) {
                                    $groupedData[$idbarang] = array(
                                       'nmbarang' => $tampil['nmbarang'],
                                       'qty' => 0,
                                       'box' => 0,
                                    );
                                 }

                                 $groupedData[$idbarang]['qty'] += $tampil['qty'];
                                 $groupedData[$idbarang]['box'] += 1; // Menghitung jumlah unik idbarang
                                 $groupedData[$idbarang]['pod'] = $tampil['pod'];
                              }

                              foreach ($groupedData as $idbarang => $data) {
                              ?>
                                 <tr>
                                    <td class="text-center"><?= $no; ?></td>
                                    <td><?= $data['nmbarang']; ?></td>
                                    <td class="text-right"><?= $data['qty']; ?></td>
                                    <td class="text-center"><?= $data['box']; ?></td>
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
            </div>
         </div>
      </div>
   </div>
</section>
<script>
   document.title = "MUTASI";
</script>
<?php
include "../footer.php" ?>