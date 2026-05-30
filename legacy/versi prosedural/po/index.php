<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <?php
                            // Query data dari tabel po
                            // Ambil juga stat dan is_deleted untuk menentukan apakah tombol delete boleh tampil
                            $sql = "SELECT 
                                        p.idpo, p.nopo, p.duedate, p.note, p.creatime, p.xamount, p.taxrp, p.tax, p.top,
                                        p.is_deleted, p.stat,
                                        r.norequest, s.nmsupplier,
                                        EXISTS (
                                            SELECT 1 FROM grraw g WHERE g.idpo = p.idpo AND COALESCE(g.is_deleted,0) = 0
                                        ) AS has_gr
                                    FROM po p
                                    LEFT JOIN request r ON p.idrequest = r.idrequest
                                    LEFT JOIN supplier s ON p.idsupplier = s.idsupplier
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
                                        <th>Supplier</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
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
                                            $idpo = (int)$row['idpo'];
                                            $hasGR = !empty($row['has_gr']);
                                            $stat = isset($row['stat']) ? (int)$row['stat'] : 0;
                                            $isDeleted = isset($row['is_deleted']) ? (int)$row['is_deleted'] : 0; // 0 active, 1 soft-deleted, 2 cancelled (if used)
                                    ?>
                                            <tr>
                                                <td><?= $i ?></td>
                                                <td><?= htmlspecialchars($row['nopo']) ?></td>
                                                <td><?= htmlspecialchars($row['norequest']) ?></td>
                                                <td class="text-left"><?= htmlspecialchars($row['nmsupplier']) ?></td>
                                                <td><?= htmlspecialchars(date("D, d-M-y", strtotime($row['duedate']))) ?></td>
                                                <td class="text-right"><?= number_format((float)$row['xamount'], 2) ?></td>
                                                <td class="text-right"><?= htmlspecialchars($row['tax']) ?> (<?= number_format((float)$row['taxrp'], 2) ?>)</td>
                                                <td><?= htmlspecialchars($row['top']) ?> Hari</td>
                                                <td class="text-left"><?= htmlspecialchars($row['note']) ?></td>
                                                <td>
                                                    <a href="view.php?id=<?= $idpo ?>" class='btn btn-info btn-sm' title="View PO">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <?php
                                                    // TOMBOL DELETE hanya tampil jika:
                                                    // - belum ada GR terkait ($hasGR == false)
                                                    // - belum dibatalkan (stat != 2)
                                                    // - dan belum dihapus (is_deleted == 0)
                                                    $canDelete = (!$hasGR) && ($stat !== 2) && ($isDeleted === 0);
                                                    ?>

                                                    <?php if (!$canDelete): ?>
                                                        <!-- Disable / tampilkan indikator jika tidak bisa dihapus -->
                                                        <?php if ($stat === 2 || $isDeleted === 2): ?>
                                                            <button class="btn btn-warning btn-sm" title="PO dibatalkan / cancelled" disabled>
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php elseif ($hasGR): ?>
                                                            <button class="btn btn-secondary btn-sm" title="PO sudah memiliki GR" disabled>
                                                                <i class="fas fa-lock"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <!-- fallback disabled -->
                                                            <button class="btn btn-secondary btn-sm" title="Tidak bisa dihapus" disabled>
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <!-- Bisa dihapus -->
                                                        <form action="delete.php" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus PO ini?');">
                                                            <input type="hidden" name="id" value="<?= $idpo ?>">
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
    document.title = "PO List";
</script>

<?php
include "../footer.php";
?>