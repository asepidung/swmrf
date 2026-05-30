<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";

$idrequest = $_GET['id'] ?? null;

if (!$idrequest) {
    die("Error: Missing request ID.");
}

// Ambil data dari tabel `request`
$query_request = "SELECT r.*, s.nmsupplier, u.fullname
                  FROM request r
                  LEFT JOIN supplier s ON r.idsupplier = s.idsupplier
                  LEFT JOIN users u ON r.iduser = u.idusers
                  WHERE r.idrequest = ?";
$stmt_request = mysqli_prepare($conn, $query_request);
mysqli_stmt_bind_param($stmt_request, "i", $idrequest);
mysqli_stmt_execute($stmt_request);
$result_request = mysqli_stmt_get_result($stmt_request);

if ($result_request->num_rows === 0) {
    die("Error: Request not found.");
}

$request = mysqli_fetch_assoc($result_request);

// Ambil data detail dari tabel `requestdetail`
$query_details = "SELECT rd.*, rm.nmrawmate
                  FROM requestdetail rd
                  LEFT JOIN rawmate rm ON rd.idrawmate = rm.idrawmate
                  WHERE rd.idrequest = ?";
$stmt_details = mysqli_prepare($conn, $query_details);
mysqli_stmt_bind_param($stmt_details, "i", $idrequest);
mysqli_stmt_execute($stmt_details);
$result_details = mysqli_stmt_get_result($stmt_details);

// Ambil semua data detail ke dalam array
$request_details = [];
while ($detail = mysqli_fetch_assoc($result_details)) {
    $request_details[] = $detail;
}

mysqli_stmt_close($stmt_request);
mysqli_stmt_close($stmt_details);
?>

<style>
    body {
        font-size: 16px;
    }

    @media (max-width: 768px) {
        body {
            font-size: 14px;
        }

        .container {
            padding-left: 5px;
            padding-right: 5px;
        }
    }

    @media print {
        .no-print {
            display: none;
        }
    }
</style>

<div class="container mt-4">
    <div class="col text-center">
        <strong>REQUEST DETAILS</strong>
        <h5 class="mb-n1">PT. SANTI WIJAYA MEAT</h5>
        <p>
            RPHR Jonggol Jl. SMPN 1 Jonggol Kp. Menan Rt 04/01 Ds. Sukamaju Kec. Jonggol Kab. Bogor
        </p>
    </div>
    <hr>
    <div class="col text-right">
        <h5><strong><?= htmlspecialchars($request['norequest']) ?></strong></h5>
    </div>
    <div class="row mt-2">
        <div class="col-sm-6">
            <table class="table table-borderless table-sm">
                <tr>
                    <td>Request Date</td>
                    <td>:</td>
                    <th><?= date('d-M-Y', strtotime($request['creatime'])) ?></th>
                </tr>
                <tr>
                    <td>Requested By</td>
                    <td>:</td>
                    <th><?= htmlspecialchars($request['fullname']) ?></th>
                </tr>
                <tr>
                    <td>Note</td>
                    <td>:</td>
                    <th><?= htmlspecialchars($request['note'] ?? 'N/A') ?></th>
                </tr>
            </table>
        </div>
        <div class="col-sm-6">
            <table class="table table-borderless table-sm">
                <tr>
                    <td>Due Date</td>
                    <td>:</td>
                    <th><?= date('d-M-Y', strtotime($request['duedate'])) ?></th>
                </tr>
                <tr>
                    <td>Supplier</td>
                    <td>:</td>
                    <th><?= htmlspecialchars($request['nmsupplier'] ?? 'N/A') ?></th>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>:</td>
                    <th><?= htmlspecialchars($request['stat']) ?></th>
                </tr>
            </table>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered">
            <thead class="thead-dark">
                <tr class="text-center">
                    <th>#</th>
                    <th>Product Desc</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Total</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $total_before_tax = 0;  // Total sebelum pajak
                foreach ($request_details as $detail):
                    $total = $detail['qty'] * $detail['price'];
                    $total_before_tax += $total; // Akumulasi total sebelum pajak
                ?>
                    <tr>
                        <td class="text-center"><?= $no++; ?></td>
                        <td><?= htmlspecialchars($detail['nmrawmate']) ?></td>
                        <td class="text-right"><?= number_format(htmlspecialchars($detail['qty'])) ?></td>
                        <td class="text-right"><?= number_format($detail['price'], 2) ?></td>
                        <td class="text-right"><?= number_format($total, 2) ?></td>
                        <td><?= htmlspecialchars($detail['notes'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <?php if ($request['taxrp'] > 0): ?>
                    <tr>
                        <th colspan="4" class="text-right">Total</th>
                        <th class="text-right"><?= number_format($total_before_tax, 2) ?></th>
                        <th></th>
                    </tr>
                    <tr>
                        <th colspan="4" class="text-right">Tax</th>
                        <th class="text-right"><?= number_format($request['taxrp'], 2) ?></th>
                        <th></th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th colspan="4" class="text-right">Total</th>
                        <th class="text-right"><?= number_format($total_before_tax, 2) ?></th>
                        <th></th>
                    </tr>
                <?php endif; ?>

                <tr>
                    <th colspan="4" class="text-right">Grand Total</th>
                    <th class="text-right"><?= number_format($total_before_tax + $request['taxrp'], 2) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

    </div>
    <div class="col">Note :</div>
    <div class="col-md-6">
        <?= htmlspecialchars($request['note'] ?? 'N/A') ?>
    </div>

    <div class="row mt-3 justify-content-center no-print">
        <div class="col-6 col-sm-4 col-md-3 mb-2">
            <a href="javascript:history.back()">
                <button type="button" class="btn btn-block btn-success"><i class="fas fa-undo"></i></button>
            </a>
        </div>
        <div class="col-6 col-sm-4 col-md-3">
            <button type="button" class="btn btn-block btn-warning" onclick="window.print()">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </div>
</div>

<script>
    document.title = "<?= htmlspecialchars($request['norequest']) ?>";
</script>

<?php include "../footer.php"; ?>