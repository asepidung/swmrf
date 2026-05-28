<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Set default rentang tanggal: dari awal bulan sampai hari ini
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">

                <!-- FILTER PERIODE -->
                <div class="col-sm-9 col-12">
                    <form method="GET" class="form-inline flex-wrap">
                        <label class="mr-2 font-weight-bold d-none d-sm-inline">Periode (Request Date)</label>

                        <input type="date"
                            name="awal"
                            value="<?= htmlspecialchars($awal, ENT_QUOTES); ?>"
                            class="form-control form-control-sm mr-sm-2 mb-1">

                        <span class="mr-sm-2 mb-1">s/d</span>

                        <input type="date"
                            name="akhir"
                            value="<?= htmlspecialchars($akhir, ENT_QUOTES); ?>"
                            class="form-control form-control-sm mr-sm-2 mb-1">

                        <button type="submit"
                            class="btn btn-sm btn-primary mb-1"
                            name="search">
                            <i class="fas fa-search"></i> Cari
                        </button>
                    </form>
                </div>

                <!-- BACK -->
                <div class="col-sm-3 col-12 text-sm-right mt-2 mt-sm-0">
                    <a href="javascript:history.back()"
                        class="btn btn-sm btn-outline-primary btn-block btn-sm-inline">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>

            </div>
        </div>
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 mt-3">
                    <div class="card">
                        <!-- /.card-header -->
                        <div class="card-body">
                            <?php
                            // Query menggabungkan header requestbeef dan requestbeefdetail
                            $query = "SELECT 
                                 r.idrequest, 
                                 r.norequest, 
                                 r.creatime, 
                                 r.duedate, 
                                 r.stat,
                                 s.nmsupplier, 
                                 u.fullname,
                                 b.nmbarang, 
                                 rd.qty, 
                                 rd.price, 
                                 rd.notes AS detail_notes
                               FROM requestbeef r
                               INNER JOIN supplier s ON r.idsupplier = s.idsupplier
                               INNER JOIN requestbeefdetail rd ON r.idrequest = rd.idrequest
                               INNER JOIN barang b ON rd.idbarang = b.idbarang
                               INNER JOIN users u ON r.iduser = u.idusers
                               WHERE r.is_deleted = 0 
                                 AND DATE(r.creatime) BETWEEN '$awal' AND '$akhir'
                               ORDER BY r.idrequest DESC, rd.iddetail ASC";

                            $result = $conn->query($query);
                            ?>

                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Req Date</th>
                                        <th>No. Request</th>
                                        <th>Supplier</th>
                                        <th>Item Name</th>
                                        <th>Qty</th>
                                        <th>Price (Rp)</th>
                                        <th>Status</th>
                                        <th>User</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result && $result->num_rows > 0) {
                                        $row_number = 1;
                                        while ($row = $result->fetch_assoc()) { ?>
                                            <tr>
                                                <td class="text-center"> <?= $row_number; ?> </td>
                                                <td class="text-center"> <?= date("d-M-y", strtotime($row["creatime"])); ?> </td>
                                                <td class="text-center"> <?= htmlspecialchars($row["norequest"]); ?> </td>
                                                <td class="text-left"> <?= htmlspecialchars($row["nmsupplier"]); ?> </td>
                                                <td class="text-left"> <?= htmlspecialchars($row["nmbarang"]); ?> </td>
                                                <td class="text-right"> <?= number_format($row["qty"]); ?> </td>
                                                <td class="text-right"> <?= number_format($row["price"]); ?> </td>
                                                <td class="text-center">
                                                    <?php
                                                    // Bikin badge warna sesuai status biar cakep
                                                    $stat = $row["stat"];
                                                    $badgeClass = "badge-secondary";
                                                    if ($stat == "Request") $badgeClass = "badge-warning";
                                                    if ($stat == "Waiting") $badgeClass = "badge-info";
                                                    if ($stat == "Ordering") $badgeClass = "badge-primary";
                                                    if ($stat == "PO Created") $badgeClass = "badge-success";
                                                    ?>
                                                    <span class="badge <?= $badgeClass; ?>"><?= htmlspecialchars($stat); ?></span>
                                                </td>
                                                <td class="text-center"> <?= htmlspecialchars($row["fullname"]); ?> </td>
                                                <td class="text-left"> <?= htmlspecialchars($row["detail_notes"]); ?> </td>
                                            </tr>
                                    <?php
                                            $row_number++;
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>

                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

<script>
    // Mengubah judul halaman web
    document.title = "Detail Request Beef List";
</script>

<?php
include "../footer.php";
?>