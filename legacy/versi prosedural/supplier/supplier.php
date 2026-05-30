<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <a href="newsupplier.php"><button type="button" class="btn btn-info"> Supplier Baru</button></a>
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
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Nama Supplier</th>
                    <th>Alamat</th>
                    <th>Jenis Usaha</th>
                    <th>Telepon</th>
                    <th>NPWP</th>
                    <th>Hutang</th>
                    <th>Tinjau</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $no = 1;
                  $ambildata = mysqli_query($conn, "SELECT * FROM supplier ORDER BY nmsupplier ASC");
                  while ($tampil = mysqli_fetch_array($ambildata)) {
                  ?>
                    <tr>
                      <td><?= $no; ?></td>
                      <td><?= htmlspecialchars($tampil['nmsupplier']); ?></td>
                      <td><?= htmlspecialchars(strlen($tampil['alamat']) > 50 ? substr($tampil['alamat'], 0, 50) . '...' : $tampil['alamat']); ?></td>
                      <td><?= htmlspecialchars($tampil['jenis_usaha']); ?></td>
                      <td><?= htmlspecialchars($tampil['telepon']); ?></td>
                      <td><?= htmlspecialchars($tampil['npwp']); ?></td>
                      <td></td>
                      <td class="text-center">
                        <a href="edit.php?id=<?= htmlspecialchars($tampil['idsupplier']); ?>" class="btn btn-sm btn-warning">
                          <i class="fas fa-pencil-alt"></i>
                        </a>
                        <a href="delete.php?id=<?= htmlspecialchars($tampil['idsupplier']); ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete();">
                          <i class="fas fa-trash-alt"></i>
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
      </div>
    </div>
  </section>
</div>
<script>
  function confirmDelete() {
    return confirm("Apakah Anda yakin ingin menghapus data ini?");
  }
</script>
<?php include "../footer.php"; ?>