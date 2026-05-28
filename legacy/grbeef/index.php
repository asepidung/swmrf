<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <a href="draft.php"><button type="button" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus"></i> Draft</button></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
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
                                        <th>Request No</th>
                                        <th>Receiving Date</th>
                                        <th>Supplier</th>
                                        <th>ID Number</th>
                                        <th>Note</th>
                                        <th>Made By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $sql = "
                                        SELECT grbeef.*,
                                               supplier.nmsupplier,
                                               pobeef.idpo,
                                               pobeef.nopo,
                                               rbeef.norequest AS reqbeef_norequest,
                                               rbeef.iduser AS reqbeef_userid,
                                               ur.fullname AS requester_name,
                                               um.fullname AS made_by
                                        FROM grbeef
                                        LEFT JOIN pobeef ON grbeef.idpo = pobeef.idpo
                                        LEFT JOIN requestbeef rbeef ON pobeef.idrequest = rbeef.idrequest
                                        JOIN supplier ON grbeef.idsupplier = supplier.idsupplier
                                        LEFT JOIN users um ON grbeef.idusers = um.idusers
                                        LEFT JOIN users ur ON rbeef.iduser = ur.idusers
                                        WHERE grbeef.is_deleted = 0
                                        ORDER BY grbeef.idgr DESC
                                    ";

                                    $ambildata = mysqli_query($conn, $sql);

                                    if (!$ambildata) {
                                        die("Query error: " . htmlspecialchars(mysqli_error($conn)));
                                    }

                                    if (mysqli_num_rows($ambildata) > 0) {
                                        while ($tampil = mysqli_fetch_assoc($ambildata)) {
                                            $idgr = isset($tampil['idgr']) ? (int)$tampil['idgr'] : 0;
                                            $idpo = isset($tampil['idpo']) ? (int)$tampil['idpo'] : 0;
                                            $grnumber = isset($tampil['grnumber']) ? $tampil['grnumber'] : '';
                                            $receivedate = isset($tampil['receivedate']) ? $tampil['receivedate'] : '';
                                            $nmsupplier = isset($tampil['nmsupplier']) ? $tampil['nmsupplier'] : '';
                                            $suppcode = isset($tampil['suppcode']) ? $tampil['suppcode'] : '-';
                                            $note = isset($tampil['note']) ? $tampil['note'] : '-';
                                            $made_by = isset($tampil['made_by']) ? $tampil['made_by'] : '';
                                            $requester_name = isset($tampil['requester_name']) ? $tampil['requester_name'] : '';
                                            $reqbeef_norequest = isset($tampil['reqbeef_norequest']) ? $tampil['reqbeef_norequest'] : '';

                                            // fallback requester jika fullname kosong
                                            if ($requester_name === '' && !empty($tampil['reqbeef_userid'])) {
                                                $requester_name = 'User ID: ' . htmlspecialchars($tampil['reqbeef_userid']);
                                            } elseif ($requester_name === '') {
                                                $requester_name = '-';
                                            }

                                            // format tanggal aman
                                            $receivedate_fmt = '';
                                            if (!empty($receivedate) && $receivedate !== '0000-00-00') {
                                                $receivedate_fmt = htmlspecialchars(date("d-M-y", strtotime($receivedate)));
                                            }
                                    ?>
                                            <tr>
                                                <td class="text-center"><?= $no++; ?></td>
                                                <td class="text-center"><?= htmlspecialchars($grnumber); ?></td>
                                                <td><?= htmlspecialchars($requester_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                                                <td class="text-center"><?= htmlspecialchars($reqbeef_norequest); ?></td>
                                                <td class="text-center"><?= $receivedate_fmt; ?></td>
                                                <td><?= htmlspecialchars($nmsupplier); ?></td>
                                                <td><?= htmlspecialchars($suppcode); ?></td>
                                                <td><?= htmlspecialchars($note); ?></td>
                                                <td class="text-center"><?= htmlspecialchars($made_by); ?></td>
                                                <td class="text-center">
                                                    <a href="grscan.php?idgr=<?= $idgr; ?>" class="btn btn-sm btn-warning" title="Scan">
                                                        <i class="fas fa-barcode"></i>
                                                    </a>
                                                    <a href="grdetail.php?idgr=<?= $idgr; ?>" class="btn btn-sm btn-primary" title="Label">
                                                        <i class="fas fa-tag"></i>
                                                    </a>
                                                    <a href="view.php?idgr=<?= $idgr; ?>" class="btn btn-sm btn-success" title="Lihat">
                                                        <i class="far fa-eye"></i>
                                                    </a>
                                                    <a href="delete.php?idgr=<?= $idgr; ?>&idpo=<?= $idpo; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')" title="Hapus">
                                                        <i class="far fa-trash-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo "<tr><td colspan='10' class='text-center'>Data tidak ditemukan</td></tr>";
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
</script>
<?php
include "../footer.php";
?>