<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Menetapkan rentang tanggal default (awal bulan hingga hari ini)
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row align-items-center mb-2">

                <div class="col-sm-8 col-12">
                    <form method="GET" class="form-inline flex-wrap">
                        <label class="mr-2 font-weight-bold d-none d-sm-inline">Periode</label>

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

                <div class="col-sm-4 col-12 text-sm-right mt-2 mt-sm-0">
                    <a href="draft.php" class="btn btn-sm btn-outline-primary mb-1">
                        <i class="fas fa-plus"></i> Draft
                    </a>
                    <a href="detail.php" class="btn btn-sm btn-outline-success mb-1">
                        <i class="fas fa-list"></i> Detail
                    </a>
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
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>GR Number</th>
                                        <th>Requester</th>
                                        <th>Supplier</th>
                                        <th>PO Number</th>
                                        <th>Req Number</th>
                                        <th>Receiving Date</th>
                                        <th>Note</th>
                                        <th>Made By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    // Mengambil data Goods Receipt dengan filter rentang tanggal pembuatan
                                    $query = "
                                        SELECT 
                                            grraw.*,
                                            supplier.nmsupplier,
                                            po.nopo,
                                            po.stat AS po_stat,
                                            request.norequest,
                                            request.iduser AS requester_id,
                                            um.fullname AS made_by,
                                            ur.fullname AS requester_name
                                        FROM grraw
                                        LEFT JOIN po ON grraw.idpo = po.idpo
                                        LEFT JOIN request ON po.idrequest = request.idrequest
                                        JOIN supplier ON grraw.idsupplier = supplier.idsupplier
                                        LEFT JOIN users um ON grraw.idusers = um.idusers
                                        LEFT JOIN users ur ON request.iduser = ur.idusers
                                        WHERE grraw.is_deleted = 0
                                          AND DATE(grraw.creatime) BETWEEN '$awal' AND '$akhir'
                                        ORDER BY grraw.idgr DESC
                                    ";
                                    $ambildata = mysqli_query($conn, $query);
                                    if (!$ambildata) {
                                        die("Query error: " . htmlspecialchars(mysqli_error($conn)));
                                    }

                                    if (mysqli_num_rows($ambildata) === 0) {
                                        echo "<tr><td colspan='10' class='text-center'>No data available</td></tr>";
                                    } else {
                                        while ($tampil = mysqli_fetch_assoc($ambildata)) {

                                            $idgr = (int)$tampil['idgr'];
                                            $idpo = (int)$tampil['idpo'];
                                            $po_stat = (int)($tampil['po_stat'] ?? 0);

                                            $receivedate = $tampil['receivedate'] ?? '';
                                            $requester_name = $tampil['requester_name'] ?: (
                                                !empty($tampil['requester_id']) ? 'User ID: ' . $tampil['requester_id'] : '-'
                                            );
                                    ?>
                                            <tr>
                                                <td class="text-center"><?= $no++; ?></td>
                                                <td class="text-center"><?= htmlspecialchars($tampil['grnumber']); ?></td>
                                                <td><?= htmlspecialchars($requester_name); ?></td>
                                                <td><?= htmlspecialchars($tampil['nmsupplier']); ?></td>
                                                <td class="text-center"><?= htmlspecialchars($tampil['nopo']); ?></td>
                                                <td class="text-center"><?= htmlspecialchars($tampil['norequest']); ?></td>
                                                <td class="text-center">
                                                    <?= $receivedate ? date("d-M-y", strtotime($receivedate)) : ''; ?>
                                                </td>
                                                <td><?= htmlspecialchars($tampil['note']); ?></td>
                                                <td class="text-center"><?= htmlspecialchars($tampil['made_by']); ?></td>
                                                <td class="text-center">
                                                    <a href="view.php?idgr=<?= $idgr; ?>" class="btn btn-sm btn-success" title="Lihat">
                                                        <i class="far fa-eye"></i>
                                                    </a>
                                                    <a href="edit.php?idgr=<?= $idgr; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                        <i class="fas fa-pencil-alt"></i>
                                                    </a>
                                                    <a href="delete.php?idgr=<?= $idgr; ?>&idpo=<?= $idpo ?>"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"
                                                        title="Hapus">
                                                        <i class="far fa-trash-alt"></i>
                                                    </a>

                                                    <?php if ($po_stat === 3) : ?>
                                                        <a href="finish.php?idgr=<?= $idgr; ?>&idpo=<?= $idpo ?>"
                                                            class="btn btn-sm btn-primary"
                                                            onclick="return confirm('Dengan menutup PO ini, GR tidak bisa dilakukan lagi. Lanjutkan?')"
                                                            title="Close PO">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php else : ?>
                                                        <button class="btn btn-sm btn-secondary" disabled title="PO sudah ditutup">
                                                            <i class="fas fa-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                    <?php
                                        }
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
    document.title = "Goods Receipt List";
    $(function() {
        // Menginisialisasi DataTables
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

<?php include "../footer.php"; ?>