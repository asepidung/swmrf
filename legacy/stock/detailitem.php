<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Make sure to establish a connection before running the query
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$iditem = $_GET['id'];
$idusers = $_SESSION['idusers'];

// Fetch data from the stock table
$sql = "SELECT s.*, b.nmbarang, g.nmgrade
        FROM stock s
        JOIN barang b ON s.idbarang = b.idbarang
        JOIN grade g ON s.idgrade = g.idgrade
        WHERE s.idbarang = $iditem
        ORDER BY s.pod";

$result = $conn->query($sql);

// Close the database connection
$conn->close();
?>

<div class="content-wrapper">
  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col mt-3">
          <a href="index.php" class="btn btn-primary mb-2">Kembali</a>
          <div class="card">
            <div class="card-body">
              <div class="col">
                <table id="example1" class="table table-bordered table-striped table-sm">
                  <thead class="text-center">
                    <tr>
                      <th>#</th>
                      <th>Barcode</th>
                      <th>Item Desc</th>
                      <th>Temp</th>
                      <th>Grade</th>
                      <th>Qty</th>
                      <th>Pcs</th>
                      <th>pH</th>
                      <th>P.O.D</th>
                      <th>Umur</th>
                      <th>ORIGIN</th>
                      <?php if ($idusers == 1 or $idusers == 2) { ?>
                        <th>
                          Hapus
                        </th>
                      <?php } ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $no = 1;
                    if ($result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                        $origin = $row['origin'];
                        $podDate = date_create($row['pod']);
                        $currentDate = date_create(); // Tanggal hari ini

                        // Menghitung selisih waktu antara POD dan hari ini
                        $podDiff = date_diff($podDate, $currentDate);

                        $days = (int)$podDiff->format('%a');
                        $podInterval = sprintf('%03d days', $days);

                    ?>
                        <tr class="text-center">
                          <td><?= $no; ?></td>
                          <td><?= $row['kdbarcode']; ?></td>
                          <td class="text-left"><?= $row['nmbarang']; ?></td>
                          <td>
                            <?php
                            if (in_array($row['nmgrade'], ['J01', 'P01'])) {
                              echo "CHILL";
                            } else {
                              echo "FROZEN";
                            }
                            ?>
                          </td>

                          <td class="text-center"><?= $row['nmgrade']; ?>
                          </td>
                          <td class="text-right"><?= $row['qty']; ?></td>
                          <td><?= $row['pcs']; ?></td>
                          <td><?= $row['ph']; ?></td>
                          <td><?= date("d-M-y", strtotime($row['pod'])); ?></td>
                          <td>
                            <?= $podInterval; ?>
                          </td>
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
                            } elseif ($origin == 6) {
                              echo "RTN";
                            } else {
                              echo "Unindentified";
                            }
                            ?>
                          </td>
                          <?php if ($idusers == 1 or $idusers == 2) { ?>
                            <td>
                              <a href="deletethis.php?id=<?= $row['id']; ?>&iditem=<?= $iditem; ?>" class="text-info" onclick="return confirm('Apakah Anda Asep Idung?')">
                                <i class="far fa-times-circle"></i>
                              </a>
                            </td>
                          <?php } ?>
                        </tr>
                    <?php
                        $no++;
                      }
                    } else {
                      echo "<tr><td colspan='7'>No data available</td></tr>";
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
  </section>
</div>

<script>
  // Mengubah judul halaman web
  document.title = "STOCK detail";
</script>

<?php
include "../footer.php";
?>