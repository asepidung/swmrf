<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
$idst = $_GET['id'];
?>
<div class="content-wrapper">
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12 mt-3">
               <div class="card">
                  <div class="card-body">
                     <div class="col">
                        <table id="example1" class="table table-bordered table-striped table-sm">
                           <thead class="text-center">
                              <tr>
                                 <th rowspan="2">#</th>
                                 <th rowspan="2">Product Desc</th>
                                 <th colspan="2">J01</th>
                                 <th colspan="2">J02</th>
                                 <th colspan="2">P01</th>
                                 <th colspan="2">P02</th>
                              </tr>
                              <tr>
                                 <th>Qty</th>
                                 <th>Box</th>
                                 <th>Qty</th>
                                 <th>Box</th>
                                 <th>Qty</th>
                                 <th>Box</th>
                                 <th>Qty</th>
                                 <th>Box</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $grades = array(1, 2, 3, 4); // Daftar idgrade yang ingin Anda tampilkan (1 hingga 4)
                              $ambildata = mysqli_query($conn, "SELECT std.idbarang, SUM(std.qty) AS total_qty, COUNT(std.qty) AS total_box, b.nmbarang
                              FROM stocktakedetail std
                              INNER JOIN barang b ON std.idbarang = b.idbarang
                              WHERE std.idst = $idst 
                              GROUP BY std.idbarang");
                              while ($tampil = mysqli_fetch_array($ambildata)) { ?>
                                 <tr class="text-center">
                                    <td><?= $no ?></td>
                                    <td class="text-left"><?= $tampil['nmbarang']; ?></td>
                                    <?php foreach ($grades as $grade) : ?>
                                       <?php
                                       // Query untuk mengambil jumlah qty dan box berdasarkan idgrade tertentu
                                       $query_grade = mysqli_query($conn, "SELECT SUM(qty) AS total_qty, COUNT(qty) AS total_box
                                            FROM stocktakedetail
                                            WHERE idst = $idst AND idgrade = $grade AND idbarang = {$tampil['idbarang']}");
                                       $data_grade = mysqli_fetch_assoc($query_grade);
                                       // Memeriksa apakah total_qty tidak memiliki nilai, jika iya, jangan menampilkan apapun
                                       if ($data_grade['total_qty'] !== null) {
                                       ?>
                                          <td class="text-right"><?= number_format($data_grade['total_qty'], 2); ?></td>
                                          <td><?= $data_grade['total_box']; ?></td>
                                       <?php } else { ?>
                                          <td></td>
                                          <td></td>
                                       <?php } ?>
                                    <?php endforeach; ?>
                                 </tr>
                              <?php $no++;
                              } ?>
                           </tbody>
                        </table>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>
<script>
   document.title = "HASIL STOCK";
</script>
<?php
include "../footer.php";
?>