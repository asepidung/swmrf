<?php
require "../verifications/auth.php";
$idusers = $_SESSION['idusers'] ?? 0;

require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Tangkap filter periode
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>
<div class="content-wrapper">

  <div class="content-header">
    <div class="container-fluid">
      <div class="row align-items-center mb-2">

        <div class="col-sm-9 col-12">
          <form method="GET" class="form-inline flex-wrap">
            <label class="mr-2 font-weight-bold d-none d-sm-inline">Periode</label>
            <input type="date"
              name="awal"
              value="<?= htmlspecialchars($awal, ENT_QUOTES); ?>"
              class="form-control form-control-sm mr-sm-2 mb-1">
            <span class="mr-sm-2 mb-1">s/d</span>
            <input type="date"
              name="akhir"
              value="<?= htmlspecialchars($akhir, ENT_QUOTES); ?>"
              class="form-control form-control-sm mr-sm-2 mb-1">
            <button type="submit" class="btn btn-sm btn-primary mb-1" name="search">
              <i class="fas fa-search"></i> Cari
            </button>
          </form>
        </div>

        <div class="col-sm-3 col-12 text-sm-right mt-2 mt-sm-0">
          <a href="newboning.php" class="btn btn-sm btn-info btn-block btn-sm-inline">
            <i class="fas fa-plus-circle"></i> Baru
          </a>
        </div>

      </div>
    </div>
  </div>

  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col">
          <div class="card">
            <div class="card-body">

              <table id="example1" class="table table-bordered table-striped table-sm">
                <thead class="text-center">
                  <tr>
                    <th>#</th>
                    <th>Batch Number</th>
                    <th>Tgl Boning</th>
                    <th>Supplier</th>
                    <th>Jml Sapi</th>
                    <th>Ttl Weight</th>
                    <th>MBR</th>
                    <th>Catatan</th>
                    <th>AKSI</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  // Query diubah untuk memfilter berdasarkan tanggal boning
                  $ambildata = mysqli_query($conn, "
                    SELECT b.*, p.nmsupplier
                    FROM boning b
                    JOIN supplier p ON b.idsupplier = p.idsupplier
                    WHERE b.is_deleted = 0 AND b.tglboning BETWEEN '$awal' AND '$akhir'
                    ORDER BY b.batchboning DESC
                  ");

                  while ($tampil = mysqli_fetch_assoc($ambildata)) {

                    $idboning = (int)$tampil['idboning'];

                    // Mengambil total berat dari label produksi
                    $qWeight = mysqli_query($conn, "
                      SELECT SUM(qty) AS total_weight
                      FROM labelboning
                      WHERE idboning = $idboning AND is_deleted = 0
                    ");
                    $rw = mysqli_fetch_assoc($qWeight);
                    $total_weight = (float)($rw['total_weight'] ?? 0);

                    // Menghitung MBR
                    $avg_weight = 0;
                    if ((int)$tampil['qtysapi'] > 0) {
                      $avg_weight = $total_weight / (int)$tampil['qtysapi'];
                    }

                    // Mengecek ketersediaan data pada production_requests
                    $qRequest = mysqli_query($conn, "
                      SELECT idrequest 
                      FROM production_requests 
                      WHERE idboning = $idboning AND is_deleted = 0 
                      LIMIT 1
                    ");
                    $hasRequest = mysqli_num_rows($qRequest) > 0;

                    $isLocked = (int)$tampil['kunci'];
                  ?>
                    <tr class="text-center">
                      <td><?= $no++; ?></td>

                      <td>
                        <a href="laporan_rawusage.php?id=<?= $idboning; ?>"
                          class="text-primary font-weight-bold"
                          title="Lihat Laporan Pemakaian Bahan (HPP)">
                          <?= htmlspecialchars($tampil['batchboning']); ?>
                        </a>
                      </td>

                      <td><?= date("d-M-Y", strtotime($tampil['tglboning'])); ?></td>
                      <td class="text-left"><?= htmlspecialchars($tampil['nmsupplier']); ?></td>
                      <td><?= (int)$tampil['qtysapi']; ?></td>
                      <td class="text-right"><?= number_format($total_weight, 2); ?></td>
                      <td class="text-right"><?= number_format($avg_weight, 2); ?></td>
                      <td class="text-left"><?= htmlspecialchars($tampil['keterangan']); ?></td>

                      <td class="text-nowrap">

                        <?php if ($hasRequest): ?>
                          <a class="btn btn-success btn-sm"
                            title="Lihat/Edit Request Marketing"
                            href="../boningreq/request_boning_view.php?idboning=<?= $idboning; ?>">
                            <i class="fas fa-clipboard-check"></i>
                          </a>
                        <?php else: ?>

                          <?php if ($isLocked == 0): ?>
                            <a class="btn btn-primary btn-sm"
                              title="Buat Request Marketing"
                              href="../boningreq/request_boning_create.php?idboning=<?= $idboning; ?>">
                              <i class="fas fa-clipboard-list"></i>
                            </a>
                          <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-sm" disabled title="Data Boning Sudah Dikunci (Tanpa Request)">
                              <i class="fas fa-clipboard-check"></i>
                            </button>
                          <?php endif; ?>

                        <?php endif; ?>

                        <?php if ($idusers == 1 || $idusers == 9): ?>
                          <a class="btn btn-sm <?= $isLocked ? 'btn-danger' : 'btn-secondary'; ?>"
                            title="<?= $isLocked ? 'Unlock' : 'Lock'; ?>"
                            href="togglekunci.php?idboning=<?= $idboning; ?>&kunci=<?= $isLocked ? 0 : 1; ?>"
                            onclick="return confirm('Apakah yakin ingin <?= $isLocked ? 'membuka kunci' : 'mengunci'; ?> boning ini?')">
                            <i class="fas <?= $isLocked ? 'fa-lock' : 'fa-lock-open'; ?>"></i>
                          </a>
                        <?php endif; ?>

                        <a class="btn btn-warning btn-sm"
                          title="Buat Label"
                          href="labelboning.php?id=<?= $idboning; ?>">
                          <i class="fas fa-barcode"></i>
                        </a>

                        <a class="btn btn-success btn-sm"
                          title="Lihat Hasil Boning"
                          href="boningdetail.php?id=<?= $idboning; ?>">
                          <i class="fas fa-eye"></i>
                        </a>

                        <a class="btn btn-info btn-sm"
                          title="Edit Boning"
                          href="editdataboning.php?idboning=<?= $idboning; ?>">
                          <i class="fas fa-pencil-alt"></i>
                        </a>

                        <a class="btn btn-danger btn-sm <?= $isLocked ? 'disabled' : ''; ?>"
                          href="<?= $isLocked ? 'javascript:void(0)' : 'deletedataboning.php?idboning=' . $idboning; ?>"
                          onclick="<?= $isLocked ? '' : 'return confirm(\'Yakin ingin menghapus data boning ini?\')'; ?>">
                          <i class="fas fa-minus-circle"></i>
                        </a>

                      </td>
                    </tr>
                  <?php } ?>
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
  document.title = "Data Boning";
  $(function() {
    $("#example1").DataTable({
      responsive: true,
      lengthChange: false,
      autoWidth: false,
      ordering: false,
      paging: true,
      pageLength: 25,
      searching: true,
      info: true,
      buttons: ["copy", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
  });
</script>

<?php include "../footer.php"; ?>