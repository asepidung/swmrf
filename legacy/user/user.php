<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>
<div class="content-wrapper">
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-md-6">
               <div class="card mt-3">
                  <div class="card-body">
                     <table class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr class="text-center">
                              <th style="width: 16px;">#</th>
                              <th>Userid</th>
                              <th>Nama Lengkap</th>
                              <th>Status</th>
                              <?php if ($_SESSION['idusers'] == 1) { ?>
                                 <th>Reset</th>
                              <?php } ?>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           $ambildata = mysqli_query($conn, "SELECT * FROM users");
                           while ($tampil = mysqli_fetch_array($ambildata)) {
                              $status = $tampil['status']; ?>
                              <tr>
                                 <td class="text-center"><?= $no; ?></td>
                                 <td>
                                    <a href="edituser.php?id=<?= $tampil['idusers'] ?>"><?= $tampil['userid']; ?></a>
                                 </td>
                                 <td><?= $tampil['fullname']; ?></td>
                                 <td class="text-center">
                                    <?php
                                    if ($status == "AKTIF") { ?>
                                       <a href="nonaktifkan.php?id=<?= $tampil['idusers'] ?>" class="text-success"><?= $status; ?></a>
                                    <?php } else { ?>
                                       <a href="aktifkan.php?id=<?= $tampil['idusers'] ?>" class="text-danger"><?= $status; ?></a>
                                    <?php } ?>
                                 </td>
                                 <?php if ($_SESSION['idusers'] == 1) { ?>
                                    <td class="text-center">
                                       <a href="reset_password.php?id=<?= $tampil['idusers']; ?>"
                                          class="btn btn-sm btn-warning"
                                          onclick="return confirm('Reset password user ini?')">
                                          Reset
                                       </a>
                                    </td>
                                 <?php } ?>
                              </tr>
                           <?php $no++;
                           } ?>
                        </tbody>
                     </table>
                     <a href="../verifications/regist.php" class="btn btn-sm btn-primary">Baru</a>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   document.title = "Data User";
</script>
<?php
include "../footer.php";
?>