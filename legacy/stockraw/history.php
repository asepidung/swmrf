<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Query untuk mengambil data dari tabel stockraw, memfilter yang qty < 0 atau idtransaksi berawalan '2'
$sql = "
    SELECT 
        sr.creatime,
        rm.nmrawmate,
        rc.nmcategory AS category,  -- Menambahkan kategori
        sr.qty,
        sr.idtransaksi
    FROM stockraw sr
    JOIN rawmate rm ON sr.idrawmate = rm.idrawmate
    JOIN rawcategory rc ON rm.idrawcategory = rc.idrawcategory  -- Menghubungkan rawcategory
    WHERE (sr.qty < 0 OR sr.idtransaksi LIKE '2%')
    ORDER BY sr.creatime DESC
";

$result = $conn->query($sql);
?>

<div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 mt-2">
                    <div class="card">
                        <div class="card-body">
                            <div class="col">
                                <table id="example1" class="table table-bordered table-striped table-sm">
                                    <thead class="text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>Tanggal</th>
                                            <th>Item Description</th>
                                            <th>Kategori</th> <!-- Menambahkan kolom kategori -->
                                            <th>Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        if ($result->num_rows > 0) {
                                            $no = 1;
                                            while ($row = $result->fetch_assoc()) {
                                                echo "<tr class='text-center'>";
                                                echo "<td>" . $no++ . "</td>";
                                                echo "<td>" . htmlspecialchars(date('d-M-Y', strtotime($row['creatime']))) . "</td>";
                                                echo "<td class='text-left'>" . htmlspecialchars($row['nmrawmate']) . "</td>";
                                                // Menampilkan kategori
                                                echo "<td class='text-left'>" . htmlspecialchars($row['category']) . "</td>";
                                                echo "<td class='text-right'>" . htmlspecialchars(number_format($row['qty'], 2)) . "</td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5' class='text-center'>No data available</td></tr>";
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
    document.title = "HISTORI PENGELUARAN";
</script>

<?php
include "../footer.php";
?>