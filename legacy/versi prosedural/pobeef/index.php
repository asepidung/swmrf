<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// --- CSRF token (sekali per session) ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">

            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <?php
                            // Query list + flag has_gr (true jika sudah ada GR aktif utk idpo tsb)
                            $sql = "SELECT 
                                        p.idpo, p.nopo, p.duedate, p.note, p.creatime, p.xamount, p.taxrp, p.tax, p.top,
                                        p.is_deleted, p.stat,
                                        r.norequest, s.nmsupplier,
                                        EXISTS(
                                            SELECT 1 
                                            FROM grbeef g 
                                            WHERE g.idpo = p.idpo 
                                              AND COALESCE(g.is_deleted,0) = 0
                                            LIMIT 1
                                        ) AS has_gr
                                    FROM pobeef p
                                    LEFT JOIN requestbeef r ON p.idrequest  = r.idrequest
                                    LEFT JOIN supplier    s ON p.idsupplier = s.idsupplier
                                    WHERE p.is_deleted = 0 OR p.is_deleted = 2
                                    ORDER BY p.idpo DESC";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            ?>

                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>PO Number</th>
                                        <th>Request Number</th>
                                        <th class="text-left">Supplier</th>
                                        <th>Due Date</th>
                                        <th class="text-right">Amount</th>
                                        <th>Tax</th>
                                        <th>Terms</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    <?php
                                    if ($result->num_rows > 0) {
                                        $i = 1;
                                        while ($row = $result->fetch_assoc()) {
                                            $idpo  = (int)$row['idpo'];
                                            $hasGR = !empty($row['has_gr']);
                                            $isDeleted = isset($row['is_deleted']) ? (int)$row['is_deleted'] : 0; // 0 active, 1 deleted, 2 cancelled (your case)
                                            $stat = isset($row['stat']) ? (int)$row['stat'] : 0;
                                    ?>
                                            <tr>
                                                <td><?= $i ?></td>
                                                <td><?= htmlspecialchars($row['nopo']) ?></td>
                                                <td><?= htmlspecialchars($row['norequest']) ?></td>
                                                <td class="text-left"><?= htmlspecialchars($row['nmsupplier']) ?></td>
                                                <td><?= htmlspecialchars(date("D, d-M-y", strtotime($row['duedate']))) ?></td>
                                                <td class="text-right"><?= number_format((float)$row['xamount'], 2) ?></td>
                                                <td><?= htmlspecialchars($row['tax']) ?> (<?= number_format((float)$row['taxrp'], 2) ?>)</td>
                                                <td><?= htmlspecialchars($row['top']) ?> Hari</td>
                                                <td class="text-left"><?= htmlspecialchars($row['note']) ?></td>
                                                <td class="text-nowrap">
                                                    <a href="view.php?id=<?= $idpo ?>" class='btn btn-info btn-sm' title="View PO">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <?php if ($isDeleted === 2 || $stat === 2): ?>
                                                        <!-- PO sudah dibatalkan -->
                                                        <button type="button"
                                                            class="btn btn-warning btn-sm"
                                                            title="PO sudah dibatalkan (cancel)">
                                                            <i class="fas fa-ban"></i>
                                                        </button>

                                                    <?php elseif ($hasGR): ?>
                                                        <!-- Nonaktif: sudah ada GR -->
                                                        <button type="button"
                                                            class="btn btn-secondary btn-sm"
                                                            title="Tidak bisa hapus: PO ini sudah punya GR aktif">
                                                            <i class="fas fa-lock"></i>
                                                        </button>

                                                    <?php else: ?>
                                                        <!-- Aktif: belum ada GR dan tidak dibatalkan -->
                                                        <form action="delete.php" method="POST" class="d-inline"
                                                            onsubmit="return confirm('Yakin hapus PO ini?');">
                                                            <input type="hidden" name="id" value="<?= $idpo ?>">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete PO">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>

                                            </tr>
                                    <?php
                                            $i++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='10' class='text-center'>No data available</td></tr>";
                                    }
                                    $stmt->close();
                                    $conn->close();
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
    document.title = "PO Beef List";
    $(function() {
        $('[data-toggle="tooltip"], [title]').tooltip();
    });
</script>

<?php include "../footer.php"; ?>