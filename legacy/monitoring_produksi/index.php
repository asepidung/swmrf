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
            <div class="card mt-3">
                <div class="card-body">
                    <table id="example1" class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                            <tr>
                                <th>#</th>
                                <th>Customer</th>
                                <th>No SO</th>
                                <th>Status</th>
                                <th>Catatan</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $query = mysqli_query($conn, "SELECT mp.*, c.nama_customer, so.sonumber 
                                                          FROM monitoring_produksi mp
                                                          JOIN customers c ON mp.idcustomer = c.idcustomer
                                                          JOIN salesorder so ON mp.idso = so.idso
                                                          WHERE mp.is_deleted = 0
                                                          ORDER BY mp.idmonitoring DESC");

                            while ($row = mysqli_fetch_array($query)) {
                                $status = $row['status_qc'];
                                // Fix deprecated PHP 8.1+ dan bersihkan string
                                $currentNote = addslashes($row['catatan_qc'] ?? '');
                            ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td><?= $row['nama_customer']; ?></td>
                                    <td class="text-center"><?= $row['sonumber']; ?></td>
                                    <td class="text-center">
                                        <?php
                                        if ($status == 'Pending') echo '<span class="badge badge-warning">Pending</span>';
                                        elseif ($status == 'In Progress') echo '<span class="badge badge-info">In Progress</span>';
                                        elseif ($status == 'Passed') echo '<span class="badge badge-success">Passed</span>';
                                        else echo '<span class="badge badge-danger">Rejected</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($row['catatan_qc']) {
                                            echo '<span class="text-muted">' . htmlspecialchars($row['catatan_qc']) . '</span>';
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($status == 'Pending'): ?>
                                            <button class="btn btn-sm btn-primary" onclick="startProcess(<?= $row['idmonitoring']; ?>)">
                                                Process <i class="fas fa-play"></i>
                                            </button>

                                        <?php elseif ($status == 'In Progress'): ?>
                                            <a href="view.php?id=<?= $row['idmonitoring']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-outline-success" onclick="finishProcess(<?= $row['idmonitoring']; ?>)" title="Finish">
                                                Finish <i class="fas fa-check-double"></i>
                                            </button>

                                        <?php elseif ($status == 'Passed'): ?>
                                            <a href="view.php?id=<?= $row['idmonitoring']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-success" onclick="editNote(<?= $row['idmonitoring']; ?>, '<?= $currentNote; ?>')" title="Edit Catatan">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                        <?php else: ?>
                                            <a href="view.php?id=<?= $row['idmonitoring']; ?>" class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function startProcess(id) {
        Swal.fire({
            title: 'Mulai Monitoring?',
            text: "Status akan berubah menjadi In Progress",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Mulai!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "monitoring_update_status.php?id=" + id;
            }
        })
    }

    function editNote(id, note) {
        Swal.fire({
            title: 'Edit Catatan QC',
            input: 'textarea',
            inputValue: note,
            inputPlaceholder: 'Update catatan di sini...',
            showCancelButton: true,
            confirmButtonText: 'Simpan Perubahan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "monitoring_update_note.php?id=" + id + "&note=" + encodeURIComponent(result.value);
            }
        })
    }

    function finishProcess(id) {
        Swal.fire({
            title: 'Selesaikan Monitoring?',
            text: "Berikan catatan akhir mengenai hasil produksi ini.",
            input: 'textarea',
            inputPlaceholder: 'Contoh: Barang sesuai spesifikasi, siap kirim...',
            icon: 'success',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Passed!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "monitoring_finish.php?id=" + id + "&note=" + encodeURIComponent(result.value);
            }
        })
    }

    document.title = "MONITORING QC";
</script>

<?php include "../footer.php" ?>