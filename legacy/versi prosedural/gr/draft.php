<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Mengambil data PO dari database
$query = "
    SELECT 
        p.idpo,
        s.nmsupplier,
        p.duedate AS deliveryat,
        p.nopo,
        r.norequest,
        p.note,
        p.stat,
        COALESCE(u.fullname, u.userid, '') AS requester
    FROM po p
    JOIN supplier s ON p.idsupplier = s.idsupplier
    JOIN request r ON p.idrequest = r.idrequest
    LEFT JOIN users u ON r.iduser = u.idusers
    WHERE p.is_deleted = 0
      AND p.stat IN (0,3)
    ORDER BY p.duedate ASC, p.nopo ASC
";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Query error: " . htmlspecialchars(mysqli_error($conn)));
}
?>
<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card mt-3">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="example1" class="table table-bordered table-striped table-sm">
                                    <thead class="text-center text-nowrap">
                                        <tr>
                                            <th>#</th>
                                            <th>Supplier</th>
                                            <th>Requester</th>
                                            <th>Receiving Date</th>
                                            <th>PO</th>
                                            <th>Request No</th>
                                            <th>Note</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $counter = 1;
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $idpo       = (int)$row['idpo'];
                                            $nmsupplier = $row['nmsupplier'] ?? '';
                                            $requester  = $row['requester'] ?? '';
                                            $deliveryat = $row['deliveryat'] ?? '';
                                            $nopo       = $row['nopo'] ?? '';
                                            $norequest  = $row['norequest'] ?? '';
                                            $note       = $row['note'] ?? '';
                                            $stat       = (int)$row['stat'];

                                            // Menentukan label dan style berdasarkan status
                                            if ($stat === 3) {
                                                $btnText  = "Lanjutkan GR";
                                                $btnClass = "btn-warning";
                                                $title    = "GR Partial";
                                            } else {
                                                $btnText  = "Proses GR";
                                                $btnClass = "btn-primary";
                                                $title    = "Buat GR";
                                            }
                                        ?>
                                            <tr>
                                                <td class="text-center"><?= $counter++; ?></td>
                                                <td><?= htmlspecialchars($nmsupplier); ?></td>
                                                <td><?= htmlspecialchars($requester); ?></td>
                                                <td class="text-center text-nowrap"><?= htmlspecialchars($deliveryat); ?></td>
                                                <td class="text-center text-nowrap"><?= htmlspecialchars($nopo); ?></td>
                                                <td class="text-center text-nowrap"><?= htmlspecialchars($norequest); ?></td>
                                                <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($note); ?>">
                                                    <?= htmlspecialchars($note); ?>
                                                </td>
                                                <td class="text-center text-nowrap">
                                                    <a class="btn <?= $btnClass ?> btn-xs mb-1"
                                                        data-toggle="tooltip"
                                                        data-placement="bottom"
                                                        title="<?= $title ?>"
                                                        href="newgr.php?id=<?= $idpo ?>">
                                                        <?= $btnText ?> <i class="fas fa-truck"></i>
                                                    </a>
                                                    <a class="btn btn-danger btn-xs mb-1"
                                                        data-toggle="tooltip"
                                                        data-placement="bottom"
                                                        title="Cancel"
                                                        href="cancel.php?id=<?= $idpo ?>"
                                                        onclick="return confirm('Apa Kamu Yakin Ingin Menolak PO ini?');">
                                                        Cancel <i class="fas fa-window-close"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php
                                        }
                                        if (mysqli_num_rows($result) === 0) {
                                            echo "<tr><td colspan='8' class='text-center'>No data available</td></tr>";
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
    document.title = "DRAFT GR";
    // Mengaktifkan tooltip bawaan Bootstrap
    document.addEventListener("DOMContentLoaded", function() {
        if (typeof $ !== 'undefined') {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
</script>

<?php include "../footer.php"; ?>