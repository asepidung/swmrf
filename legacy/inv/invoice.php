<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

$awal  = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');

$today = date('Y-m-d');
?>
<div class="content-wrapper">
   <div class="content-header">
      <div class="container-fluid">

         <div class="card mb-2">
            <div class="card-body py-2">
               <div class="row align-items-center">

                  <div class="col-lg-6 col-md-7">
                     <form class="form-inline" method="GET">
                        <label class="mr-2 mb-2 mb-md-0">Periode</label>

                        <input type="date"
                           class="form-control form-control-sm mr-2 mb-2 mb-md-0"
                           name="awal"
                           value="<?= $awal; ?>">

                        <span class="mr-2 mb-2 mb-md-0">s/d</span>

                        <input type="date"
                           class="form-control form-control-sm mr-2 mb-2 mb-md-0"
                           name="akhir"
                           value="<?= $akhir; ?>">

                        <button type="submit"
                           class="btn btn-sm btn-primary mb-2 mb-md-0">
                           <i class="fas fa-search mr-1"></i> Cari
                        </button>
                     </form>
                  </div>

                  <div class="col-lg-6 col-md-5 text-md-right mt-2 mt-md-0">
                     <div class="btn-group">

                        <a href="invdraft.php" class="btn btn-sm btn-outline-primary">
                           <span class="badge badge-danger mr-1"><?= $draftinvoice; ?></span>
                           Draft Invoice
                        </a>

                        <a href="tf.php" class="btn btn-sm btn-outline-warning">
                           <span class="badge badge-warning mr-1"><?= $belumTFCount; ?></span>
                           Invoice Belum TF
                        </a>

                        <a href="invoicedetail.php" class="btn btn-sm btn-outline-info">
                           <i class="fas fa-info-circle mr-1"></i> Detail
                        </a>

                     </div>
                  </div>

               </div>
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
                     <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                           <tr>
                              <th>#</th>
                              <th>Customer</th>
                              <th>No Invoice</th>
                              <th>No DO</th>
                              <th>Tgl Invoice</th>
                              <th>PO</th>
                              <th>Amount</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php
                           $no = 1;
                           $ambildata = mysqli_query($conn, "
                              SELECT invoice.*, customers.nama_customer, do.iddo
                              FROM invoice
                              INNER JOIN customers ON invoice.idcustomer = customers.idcustomer
                              LEFT JOIN do ON invoice.donumber = do.donumber
                              WHERE invoice.invoice_date BETWEEN '$awal' AND '$akhir'
                              AND invoice.is_deleted = 0
                              ORDER BY idinvoice DESC
                           ");

                           while ($tampil = mysqli_fetch_array($ambildata)) {

                              $invoiceDate  = $tampil['invoice_date'];

                              /* ================= LOCK LOGIC FIX ================= */
                              // ambil bulan invoice (YYYY-MM)
                              $invoiceMonth = date('Y-m', strtotime($invoiceDate));

                              // tanggal lock = 06 bulan setelah invoiceMonth
                              // NOTE: hitung dari tanggal 1 agar aman utk 28–31
                              $lockDate = date(
                                 'Y-m-06',
                                 strtotime($invoiceMonth . '-01 +1 month')
                              );

                              $isLocked = ($today >= $lockDate);
                           ?>
                              <tr>
                                 <td class="text-center"><?= $no; ?></td>
                                 <td><?= $tampil['nama_customer']; ?></td>

                                 <td class="text-center">
                                    <a href="pib.php?idinvoice=<?= $tampil['idinvoice']; ?>">
                                       <?= $tampil['noinvoice']; ?>
                                    </a>
                                 </td>

                                 <td class="text-center">
                                    <a href="../do/lihatdo.php?iddo=<?= $tampil['iddo']; ?>">
                                       <?= substr($tampil['donumber'], -4); ?>
                                    </a>
                                 </td>

                                 <td class="text-center">
                                    <?= date("d-M-y", strtotime($invoiceDate)); ?>
                                 </td>

                                 <td><?= $tampil['pocustomer']; ?></td>

                                 <td class="text-right">
                                    <?= number_format($tampil['balance'], 2); ?>
                                 </td>

                                 <td class="text-center">

                                    <!-- VIEW selalu aktif -->
                                    <a href="lihatinvoice.php?idinvoice=<?= $tampil['idinvoice']; ?>">
                                       <button class="btn btn-sm btn-primary">
                                          <i class="fas fa-eye"></i>
                                       </button>
                                    </a>

                                    <?php if (!$isLocked) { ?>

                                       <a href="editinvoice.php?idinvoice=<?= $tampil['idinvoice']; ?>">
                                          <button class="btn btn-sm btn-warning">
                                             <i class="fas fa-pencil-alt"></i>
                                          </button>
                                       </a>

                                       <a href="deleteinvoice.php?idinvoice=<?= $tampil['idinvoice']; ?>&iddo=<?= $tampil['iddo']; ?>"
                                          onclick="return confirm('Anda yakin ingin Membatalkan invoice ini?');">
                                          <button class="btn btn-sm btn-danger">
                                             <i class="fas fa-trash-alt"></i>
                                          </button>
                                       </a>

                                    <?php } else { ?>

                                       <!-- EDIT & DELETE tampil tapi DISABLED -->
                                       <button class="btn btn-sm btn-warning" disabled
                                          title="Periode invoice sudah terkunci">
                                          <i class="fas fa-pencil-alt"></i>
                                       </button>

                                       <button class="btn btn-sm btn-danger" disabled
                                          title="Periode invoice sudah terkunci">
                                          <i class="fas fa-trash-alt"></i>
                                       </button>

                                    <?php } ?>

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
         </div>
      </div>
   </section>
</div>

<script>
   document.title = "Invoice List";
</script>

<?php include "../footer.php"; ?>
