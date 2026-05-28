<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$idso = $_GET['idso'];
$idtally = $_GET['idtally'];

// Query untuk mengambil data batchboning dan tglboning dari tabel boning
$query_boning = "SELECT idboning, batchboning, tglboning FROM boning ORDER BY tglboning DESC";
$result_boning = mysqli_query($conn, $query_boning);
?>

<div class="content-wrapper">
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-6">
               <div class="card mt-3">
                  <div class="card-body">
                     <div class="col">
                        <div class="form-group">
                           <label>KODE BONING</label>
                           <div class="input-group">
                              <select class="form-control" name="idboning">
                                 <?php
                                 // Menampilkan opsi dari hasil query
                                 while ($row_boning = mysqli_fetch_assoc($result_boning)) {
                                    $idboning = $row_boning['idboning'];
                                    echo "<option value='" . $row_boning['idboning'] . "'>" . $row_boning['batchboning'] . " | " . date("d-M-y", strtotime($row_boning['tglboning'])) . "</option>";
                                 }
                                 ?>
                              </select>
                           </div>
                        </div>
                        <a href="prosesistimewa.php?idso=<?= $idso ?>&idtally=<?= $idtally ?>" class="btn btn-sm btn-warning">Process</a>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   document.title = "Tukar Faktur";
</script>

<?php
include "../footer.php";
?>