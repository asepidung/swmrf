<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
$idst = $_GET['id'];
?>
<div class="content-wrapper">
   <!-- Main content -->
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
                                 <th>#</th>
                                 <th>Product Desc</th>
                                 <th>J01</th>
                                 <th>J02</th>
                                 <th>J03</th>
                                 <th>P01</th>
                                 <th>P02</th>
                                 <th>P03</th>
                                 <th>UNLISTED</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $no = 1;
                              $query_stocktakedetail = "SELECT DISTINCT stocktakedetail.idbarang, barang.nmbarang
                                 FROM stocktakedetail
                                 INNER JOIN barang ON stocktakedetail.idbarang = barang.idbarang
                                 WHERE idst = '$idst'";
                              $result_stocktakedetail = mysqli_query($conn, $query_stocktakedetail);

                              while ($row_stocktakedetail = mysqli_fetch_assoc($result_stocktakedetail)) {
                                 $idbarang = $row_stocktakedetail['idbarang'];
                              ?>
                                 <tr class="text-right">
                                    <td class="text-center"><?= $no; ?></td>
                                    <td class="text-left"><?= $row_stocktakedetail['nmbarang']; ?></td>
                                    <?php
                                    // Inisialisasi array untuk menyimpan total qty berdasarkan kategori
                                    $categoryQty = array('J01' => 0, 'J02' => 0, 'J03' => 0, 'P01' => 0, 'P02' => 0, 'P03' => 0, 'UNLISTED' => 0);

                                    // Ambil data stocktakedetail untuk setiap idbarang
                                    $query_per_idbarang = "SELECT stocktakedetail.*, grade.nmgrade
                                       FROM stocktakedetail
                                       LEFT JOIN grade ON stocktakedetail.idgrade = grade.idgrade
                                       WHERE idst = '$idst' AND stocktakedetail.idbarang = '$idbarang'";
                                    $result_per_idbarang = mysqli_query($conn, $query_per_idbarang);

                                    // Hitung total qty berdasarkan kategori
                                    while ($row_per_idbarang = mysqli_fetch_assoc($result_per_idbarang)) {
                                       $category = 'UNLISTED'; // Default category
                                       if ($row_per_idbarang['nmgrade'] != null) {
                                          $category = ($row_per_idbarang['nmgrade'] == 'J01') ? 'J01' : $category;
                                          $category = ($row_per_idbarang['nmgrade'] == 'J02') ? 'J02' : $category;
                                          $category = ($row_per_idbarang['nmgrade'] == 'J03') ? 'J03' : $category;
                                          $category = ($row_per_idbarang['nmgrade'] == 'P01') ? 'P01' : $category;
                                          $category = ($row_per_idbarang['nmgrade'] == 'P02') ? 'P02' : $category;
                                          $category = ($row_per_idbarang['nmgrade'] == 'P03') ? 'P03' : $category;
                                       }
                                       $categoryQty[$category] += $row_per_idbarang['qty'];
                                    }

                                    // Hapus nilai yang 0.00
                                    $filteredCategoryQty = array_filter($categoryQty, function ($value) {
                                       return $value > 0;
                                    });
                                    foreach (array('J01', 'J02', 'J03', 'P01', 'P02', 'P03', 'UNLISTED') as $category) {
                                       $formattedQty = isset($filteredCategoryQty[$category]) ? number_format($filteredCategoryQty[$category], 2) : '';
                                       echo "<td>$formattedQty</td>";
                                    }
                                    ?>
                                 </tr>
                              <?php
                                 $no++;
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