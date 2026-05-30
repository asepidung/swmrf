<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Menetapkan rentang tanggal default (awal bulan hingga hari ini) berdasarkan receivedate
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">

                <div class="col-sm-9 col-12">
                    <form method="GET" class="form-inline flex-wrap">
                        <label class="mr-2 font-weight-bold d-none d-sm-inline">Periode (Receive Date)</label>

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

                <div class="col-sm-3 col-12 text-sm-right mt-2 mt-sm-0">
                    <a href="javascript:history.back()"
                        class="btn btn-sm btn-outline-primary btn-block btn-sm-inline">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>

            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 mt-3">
                    <div class="card">
                        <div class="card-body">
                            <?php
                            // Query menggabungkan header GR dan GR Detail beserta relasinya
                            $query = "SELECT 
                                         g.idgr,
                                         g.grnumber,
                                         g.receivedate,
                                         g.note,
                                         s.nmsupplier,
                                         po.nopo,
                                         r.norequest,
                                         um.fullname AS made_by,
                                         ur.fullname AS requester_name,
                                         rm.kdrawmate,
                                         rm.nmrawmate,
                                         rm.unit,
                                         gd.orderqty,
                                         gd.qty AS receiveqty
                                       FROM grraw g
                                       INNER JOIN grrawdetail gd ON g.idgr = gd.idgr
                                       LEFT JOIN rawmate rm ON gd.idrawmate = rm.idrawmate
                                       LEFT JOIN supplier s ON g.idsupplier = s.idsupplier
                                       LEFT JOIN po ON g.idpo = po.idpo
                                       LEFT JOIN request r ON po.idrequest = r.idrequest
                                       LEFT JOIN users um ON g.idusers = um.idusers
                                       LEFT JOIN users ur ON r.iduser = ur.idusers
                                       WHERE g.is_deleted = 0 AND gd.is_deleted = 0
                                         AND g.receivedate BETWEEN '$awal' AND '$akhir'
                                       ORDER BY g.receivedate DESC, g.idgr DESC, gd.idgrrawdetail ASC";

                            $result = $conn->query($query);
                            ?>

                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Receive Date</th>
                                        <th>GR Number</th>
                                        <th>PO Number</th>
                                        <th>Requester</th>
                                        <th>Supplier</th>
                                        <th>Item Code</th>
                                        <th>Item Name</th>
                                        <th>Order Qty</th>
                                        <th>Receive Qty</th>
                                        <th>Unit</th>
                                        <th>Made By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result && $result->num_rows > 0) {
                                        $row_number = 1;
                                        while ($row = $result->fetch_assoc()) { 
                                            // Handle nama requester
                                            $requester = $row['requester_name'] ?: '-';
                                    ?>
                                            <tr>
                                                <td class="text-center"> <?= $row_number; ?> </td>
                                                <td class="text-center"> <?= $row['receivedate'] ? date("d-M-y", strtotime($row["receivedate"])) : '-'; ?> </td>
                                                <td class="text-center"> <?= htmlspecialchars($row["grnumber"]); ?> </td>
                                                <td class="text-center"> <?= htmlspecialchars($row["nopo"] ?? '-'); ?> </td>
                                                <td class="text-left"> <?= htmlspecialchars($requester); ?> </td>
                                                <td class="text-left"> <?= htmlspecialchars($row["nmsupplier"] ?? '-'); ?> </td>
                                                <td class="text-center"> <?= htmlspecialchars($row["kdrawmate"]); ?> </td>
                                                <td class="text-left"> <?= htmlspecialchars($row["nmrawmate"]); ?> </td>
                                                <td class="text-right"> <?= number_format($row["orderqty"]); ?> </td>
                                                <td class="text-right font-weight-bold text-success"> <?= number_format($row["receiveqty"]); ?> </td>
                                                <td class="text-center"> <?= htmlspecialchars($row["unit"]); ?> </td>
                                                <td class="text-center"> <?= htmlspecialchars($row["made_by"] ?? '-'); ?> </td>
                                            </tr>
                                    <?php
                                            $row_number++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='12' class='text-center'>No data available for this period</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>

                            <?php
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
    document.title = "Detail Goods Receipt List";
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

<?php
include "../footer.php";
?>