<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Filter tanggal
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');
?>
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row g-2 align-items-center">
                <div class="col-lg-2 col-md-3 col-6">
                    <form method="GET" action="">
                        <input type="date" class="form-control form-control-sm" name="awal" value="<?= $awal; ?>">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <input type="date" class="form-control form-control-sm" name="akhir" value="<?= $akhir; ?>">
                </div>
                <div class="col-lg-1 col-md-2 col-12 d-grid">
                    <button type="submit" class="btn btn-sm btn-primary" name="search">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
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
                                        <th>Customer</th>
                                        <th>No Invoice</th>
                                        <th>Invoice Date</th>
                                        <th>T.O.P</th>
                                        <th>Due Date</th>
                                        <th>Total Amount</th>
                                        <th>Balance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $no = 1;
                                    $query = "
                                        SELECT 
                                            p.*, 
                                            i.noinvoice, 
                                            i.invoice_date, 
                                            i.top,
                                            i.tgltf,
                                            i.xamount AS total_amount,
                                            i.status AS inv_status,
                                            c.nama_customer,
                                            c.tukarfaktur
                                        FROM piutang p
                                        INNER JOIN invoice i ON p.idinvoice = i.idinvoice
                                        INNER JOIN customers c ON p.idcustomer = c.idcustomer
                                        WHERE i.invoice_date BETWEEN '$awal' AND '$akhir'
                                        ORDER BY p.idpiutang DESC
                                    ";
                                    $result = mysqli_query($conn, $query);

                                    while ($row = mysqli_fetch_assoc($result)) {
                                        // Status warna
                                        $statusClass = 'text-secondary';
                                        $statusText = htmlspecialchars($row['inv_status']);
                                        if (strtolower($statusText) == 'belum tf') $statusClass = 'text-danger';
                                        else if (strtolower($statusText) == 'sudah tf') $statusClass = 'text-success';
                                        else if (strtolower($statusText) == 'lunas') $statusClass = 'text-primary';

                                        // Base data
                                        $tukarfaktur = strtoupper(trim($row['tukarfaktur'] ?? 'NO')); // YES / NO
                                        $top = (int)$row['top'];
                                        $invoiceDate = $row['invoice_date'];
                                        $tgltf = $row['tgltf'];
                                        $today = date('Y-m-d');

                                        // Due date logic
                                        if ($tukarfaktur === 'NO') {
                                            $dueDate = date('Y-m-d', strtotime($invoiceDate . " +{$top} days"));
                                            $jatuhTempoFormatted = date('d-M-y', strtotime($dueDate));
                                        } else {
                                            if (empty($tgltf)) {
                                                $dueDate = null;
                                                $jatuhTempoFormatted = '<span class="text-secondary fw-bold">BTF</span>';
                                            } else {
                                                $dueDate = date('Y-m-d', strtotime($tgltf . " +{$top} days"));
                                                $jatuhTempoFormatted = date('d-M-y', strtotime($dueDate));
                                            }
                                        }

                                        // Highlight overdue or due today
                                        if (!empty($dueDate) && strtolower($statusText) != 'lunas') {
                                            if ($dueDate < $today) {
                                                $jatuhTempoFormatted = '<span class="text-danger fw-bold">' . $jatuhTempoFormatted . '</span>';
                                            } elseif ($dueDate == $today) {
                                                $jatuhTempoFormatted = '<span style="color:#ffc107; font-weight:bold;">' . $jatuhTempoFormatted . '</span>';
                                            }
                                        }
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td><?= htmlspecialchars($row['nama_customer']); ?></td>

                                            <!-- Invoice Number -->
                                            <td class="text-center">
                                                <a href="../inv/lihatinvoice.php?idinvoice=<?= $row['idinvoice']; ?>"
                                                    target="_blank"
                                                    title="View Invoice"
                                                    style="color:#007bff; text-decoration:none;">
                                                    <?= htmlspecialchars($row['noinvoice']); ?>
                                                </a>
                                            </td>

                                            <!-- Invoice Date -->
                                            <td class="text-center"><?= date('d-M-y', strtotime($invoiceDate)); ?></td>

                                            <!-- T.O.P -->
                                            <td class="text-center"><?= htmlspecialchars($row['top']); ?></td>

                                            <!-- Due Date -->
                                            <td class="text-center"><?= $jatuhTempoFormatted; ?></td>

                                            <!-- Total Amount -->
                                            <td class="text-right"><?= number_format($row['total_amount'] ?? 0, 2); ?></td>

                                            <!-- Balance (sementara 0) -->
                                            <td class="text-right"><?= number_format(0, 2); ?></td>

                                        </tr>
                                    <?php } ?>
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
    document.title = "Daftar Piutang";
</script>
<?php include "../footer.php"; ?>