<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Check the connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Fetch data from the stock table
$sql = "SELECT 
            s.idbarang,
            g.nmgrade,
            MAX(s.id) as id,
            MAX(s.kdbarcode) as kdbarcode,
            b.nmbarang,
            SUM(s.qty) as total_qty,
            COUNT(s.qty) as total_box
        FROM stock s
        JOIN barang b ON s.idbarang = b.idbarang
        JOIN grade g ON s.idgrade = g.idgrade
        GROUP BY s.idbarang, s.idgrade, b.nmbarang, g.nmgrade
        ORDER BY b.nmbarang";


$result = $conn->query($sql);

// Close the database connection
$conn->close();
?>

<div class="content-wrapper">
  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-8 mt-3">
          <a href="index.php" class="btn btn-primary mb-2">Summary</a>
          <div class="card">
            <div class="card-body">
              <div class="col">
                <table id="example1" class="table table-bordered table-striped table-sm">
                  <thead class="text-center">
                    <tr>
                      <th>#</th>
                      <th>Grade</th>
                      <th>Item Desc</th>
                      <th>Box</th>
                      <th>Weight</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $no = 1;
                    $currentIdbarang = null;

                    if ($result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                        // echo "Processing data for idbarang: " . $row['idbarang'] . " and nmgrade: " . $row['nmgrade'] . "<br>";

                        if ($currentIdbarang !== $row['idbarang']) {
                          // Start a new group with a new nmgrade
                          $currentIdbarang = $row['idbarang'];
                    ?>
                          <tr class="text-center">
                            <td><?= $no; ?></td>
                            <td><?= $row['nmgrade'] ? $row['nmgrade'] : '-'; ?></td>
                            <td class="text-left">
                              <?= $row['nmbarang']; ?>
                            </td>
                            <td><?= $row['total_box']; ?></td>
                            <td class="text-right"><?= number_format($row['total_qty'], 2); ?></td>
                          </tr>
                        <?php
                          // echo "Processed data for idbarang: " . $row['idbarang'] . " and nmgrade: " . $row['nmgrade'] . "<br>";
                          $no++;
                        } else {
                          // For subsequent rows in the same idbarang group, only display nmbarang, total_box, and total_qty
                        ?>
                          <tr class="text-center">
                            <td><?= $no; ?></td>
                            <td><?= $row['nmgrade'] ? $row['nmgrade'] : '-'; ?></td> <!-- Leave this cell empty for subsequent rows in the same idbarang group -->
                            <td class="text-left"><?= $row['nmbarang']; ?></td>
                            <td><?= $row['total_box']; ?></td>
                            <td class="text-right"><?= number_format($row['total_qty'], 2); ?></td>
                          </tr>
                    <?php
                          // echo "Processed data for idbarang: " . $row['idbarang'] . " and nmgrade: " . $row['nmgrade'] . "<br>";
                          $no++;
                        }
                      }
                    } else {
                      echo "<tr><td colspan='5'>No data available</td></tr>";
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