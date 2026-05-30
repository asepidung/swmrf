<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Query untuk mendapatkan data dari tabel pobeef (is_deleted = 0 dan stat = 0),
// join dengan supplier, requestbeef, dan users (requester)
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
    FROM pobeef p
    JOIN supplier s ON p.idsupplier = s.idsupplier
    JOIN requestbeef r ON p.idrequest = r.idrequest
    LEFT JOIN users u ON r.iduser = u.idusers
    WHERE p.is_deleted = 0 AND p.stat = 0
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
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Supplier</th>
                                        <th>Requester</th>
                                        <th>Due Date</th>
                                        <th>PO Number</th>
                                        <th>Request No</th>
                                        <th>Note</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $counter = 1;
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        // pastikan ada index yang diperlukan
                                        $idpo = isset($row['idpo']) ? (int)$row['idpo'] : 0;
                                        $nmsupplier = isset($row['nmsupplier']) ? $row['nmsupplier'] : '';
                                        $requester = isset($row['requester']) ? $row['requester'] : '';
                                        $deliveryat = isset($row['deliveryat']) ? $row['deliveryat'] : '';
                                        $nopo = isset($row['nopo']) ? $row['nopo'] : '';
                                        $norequest = isset($row['norequest']) ? $row['norequest'] : '';
                                        $note = isset($row['note']) ? $row['note'] : '';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($nmsupplier, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($requester, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($deliveryat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($nopo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                            <td class="text-center"><?php echo htmlspecialchars($norequest, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                            <td class="text-center">
                                                <a class="btn btn-primary btn-xs"
                                                    data-toggle="tooltip"
                                                    data-placement="bottom"
                                                    title="Buat GR"
                                                    href="newgr.php?id=<?php echo $idpo; ?>">
                                                    Proses GR <i class="fas fa-truck"></i>
                                                </a>

                                                <a class="btn btn-danger btn-xs"
                                                    data-toggle="tooltip"
                                                    data-placement="bottom"
                                                    title="Cancel"
                                                    href="cancelgr.php?id=<?php echo $idpo; ?>"
                                                    onclick="return confirm('Apa Kamu Yakin Ingin Menolak GR ini?');">
                                                    Cancel <i class="fas fa-window-close"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php
                                    } // end while

                                    // jika tidak ada row, tampilkan pesan
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
    </section>
</div>

<script>
    // Mengubah judul halaman web
    document.title = "DRAFT GR";
</script>
<?php
include "../footer.php";
?>