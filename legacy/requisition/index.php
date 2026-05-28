<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// =====================
// KONFIG USER KHUSUS
// =====================
$SUPERADMIN_ID = 1;   // bisa melakukan semua aksi
$AYU_ID        = 13;  // Accept, Buat PO, Cetak PO
$WIDI_ID       = 15;  // Approved

$currentUserId = isset($_SESSION['idusers']) ? (int)$_SESSION['idusers'] : 0;
?>

<div class="content-wrapper">
   <div class="content-header">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12 col-sm-2">
               <a href="request.php" class="btn btn-block btn-sm btn-outline-primary">
                  <i class="fas fa-plus"></i> Request
               </a>
            </div>
         </div>
      </div>
   </div>
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-12">
               <div class="card">
                  <div class="card-body">
                     <?php
                     $sql = "SELECT r.*, u.fullname, s.nmsupplier
                             FROM request r
                             INNER JOIN users u ON r.iduser = u.idusers
                             INNER JOIN supplier s ON r.idsupplier = s.idsupplier
                             WHERE r.is_deleted = 0
                             ORDER BY r.idrequest DESC";
                     $result = $conn->query($sql);
                     ?>

                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>Request Date</th>
                              <th>Request Number</th>
                              <th>User</th>
                              <th>Order To</th>
                              <th>Due Date</th>
                              <th>Notes</th>
                              <th>Status</th>
                              <th>Actions</th>
                           </tr>
                        </thead>
                        <tbody class="text-center">
                           <?php
                           if ($result->num_rows > 0) {
                              // Output data untuk setiap baris
                              $i = 1; // nomor urut
                              while ($row = $result->fetch_assoc()) {
                           ?>
                                 <tr>
                                    <td><?= $i ?></td>
                                    <td><?= date("D, d-M-y", strtotime($row['creatime'])) ?></td>
                                    <td><?= $row['norequest'] ?></td>
                                    <td><?= $row['fullname'] ?></td>
                                    <td class="text-left"><?= $row['nmsupplier'] ?></td>
                                    <td><?= date("D, d-M-y", strtotime($row['duedate'])) ?></td>
                                    <td class="text-left"><?= $row['note'] ?></td>

                                    <td>
                                       <?php
                                       $status       = $row['stat'];
                                       $isSuperAdmin = ($currentUserId === $SUPERADMIN_ID);

                                       if ($status === 'Request') {

                                          if ($currentUserId === $AYU_ID || $isSuperAdmin) {
                                             // Ayu atau Super Admin: bisa Accept
                                       ?>
                                             <a href="accept.php?id=<?= htmlspecialchars($row['idrequest']) ?>"
                                                class="btn btn-sm btn-primary">
                                                Accept
                                             </a>
                                          <?php
                                          } else {
                                             // User lain
                                          ?>
                                             <span class="text-muted">Waiting Ayu</span>
                                          <?php
                                          }
                                       } elseif ($status === 'Waiting') {

                                          if ($currentUserId === $WIDI_ID || $isSuperAdmin) {
                                             // Widi atau Super Admin: bisa Approved
                                          ?>
                                             <a href="wtoap.php?id=<?= htmlspecialchars($row['idrequest']) ?>"
                                                class="btn btn-sm btn-primary">
                                                Approved
                                             </a>
                                          <?php
                                          } else {
                                          ?>
                                             <span class="text-muted">Waiting Widi</span>
                                          <?php
                                          }
                                       } elseif ($status === 'Ordering') {

                                          if ($currentUserId === $AYU_ID || $isSuperAdmin) {
                                             // Ayu atau Super Admin: Buat PO
                                          ?>
                                             <a href="makepo.php?id=<?= htmlspecialchars($row['idrequest']) ?>"
                                                class="btn btn-sm btn-success">
                                                Buat PO
                                             </a>
                                          <?php
                                          } else {
                                          ?>
                                             <span class="text-muted">Order Pending</span>
                                          <?php
                                          }
                                       } elseif ($status === 'PO Created') {

                                          if ($currentUserId === $AYU_ID || $isSuperAdmin) {
                                             // Ayu atau Super Admin: Cetak PO
                                          ?>
                                             <a href="lihatpo.php?idrequest=<?= htmlspecialchars($row['idrequest']) ?>"
                                                class="btn btn-sm btn-secondary">
                                                Cetak PO
                                             </a>
                                          <?php
                                          } else {
                                          ?>
                                             <span class="text-muted">In Process</span>
                                       <?php
                                          }
                                       } else {
                                          // Kondisi default jika tidak ada yang cocok
                                          echo htmlspecialchars($status);
                                       }
                                       ?>
                                    </td>

                                    <td>
                                       <a href="view.php?id=<?= $row['idrequest'] ?>"
                                          class="btn btn-info btn-sm"
                                          title="Lihat">
                                          <i class="fas fa-eye"></i>
                                       </a>

                                       <a href="edit.php?id=<?= $row['idrequest'] ?>"
                                          class="btn btn-warning btn-sm"
                                          title="Edit">
                                          <i class="fas fa-pencil-alt"></i>
                                       </a>

                                       <?php if ($row['stat'] === 'Request') { ?>
                                          <a href="delete.php?id=<?= htmlspecialchars($row['idrequest']) ?>"
                                             class="btn btn-danger btn-sm"
                                             title="Delete"
                                             onclick="return confirm('Are you sure you want to delete this item?');">
                                             <i class="fas fa-trash-alt"></i>
                                          </a>
                                       <?php } else { ?>
                                          <a href="#"
                                             class="btn btn-danger btn-sm disabled"
                                             title="Cannot delete">
                                             <i class="fas fa-trash-alt"></i>
                                          </a>
                                       <?php } ?>
                                    </td>
                                 </tr>
                           <?php
                                 $i++;
                              }
                           } else {
                              echo "<tr><td colspan='9' class='text-center'>No data available</td></tr>";
                           }
                           ?>
                        </tbody>
                     </table>

                     <?php
                     // Tutup koneksi
                     $conn->close();
                     ?>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   document.title = "Request List";
</script>

<?php
include "../footer.php";
?>