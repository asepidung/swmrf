<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";
?>
<div class="content-wrapper">
   <div class="content-header">

   </div>
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-lg-3">
               <div class="card">
                  <div class="card-body">
                     <form method="GET" action="editlabel.php">
                        <div class="form-group">
                           <input type="text" placeholder="Scan Disini" class="form-control text-center" name="kdbarcode" id="kdbarcode" autofocus>
                        </div>
                        <button type="submit" class="btn btn-block bg-gradient-primary">Ubah</button>
                     </form>
                  </div>
               </div>
            </div>
            <div class="col-lg">
               <div class="card">
                  <div class="card-body">
                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>Barcode</th>
                              <th>Product</th>
                              <th>Grade</th>
                              <th>Qty</th>
                              <th>Pcs</th>
                              <th>P.O.D</th>
                              <th>Time</th>
                              <th>User</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           $ambildata = mysqli_query($conn, "SELECT r.*, b.nmbarang, u.fullname, g.nmgrade FROM relabel r
                                                   INNER JOIN barang b ON r.idbarang = b.idbarang
                                                   INNER JOIN users u ON r.iduser = u.idusers
                                                   LEFT JOIN grade g ON r.idgrade = g.idgrade
                                                   ORDER BY r.dibuat DESC");
                           while ($tampil = mysqli_fetch_array($ambildata)) {
                              $fullname = $tampil['fullname'];
                              $nmbarang = $tampil['nmbarang'];
                           ?>
                              <tr class="text-center">
                                 <td><?= $no; ?></td>
                                 <td><?= $tampil['kdbarcode']; ?></td>
                                 <td class="text-left"><?= $tampil['nmbarang']; ?></td>
                                 <td><?= $tampil['nmgrade']; ?></td>
                                 <td><?= $tampil['qty']; ?></td>
                                 <td title="<?= $tampil['xpcs']; ?>" class="text-primary"><?= $tampil['pcs']; ?></td>
                                 <td title="<?= date('d-M-y', strtotime($tampil['xpackdate'])); ?>" class="text-primary"><?= date('d-M-y', strtotime($tampil['packdate'])); ?></td>
                                 <td><?= date('d-M-y H:m:s', strtotime($tampil['dibuat'])); ?></td>
                                 <td>
                                    <?= $tampil['fullname']; ?>
                                 </td>
                                 <!-- <td>
                                    <a href="hapusrelabel.php?id=<?= $tampil['idrelabel']; ?>" class="text-danger">
                                       <i class="far fa-times-circle"></i>
                                    </a>
                                 </td> -->
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

<script>
   document.title = "Relabel";
</script>

<?php
require "../footer.php";
?>