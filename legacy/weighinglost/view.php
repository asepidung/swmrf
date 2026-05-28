<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Saat dev: munculkan error MySQLi (matikan di produksi)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helper anti XSS
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('tgl')) {
    function tgl($d)
    {
        return $d ? date('d-M-Y', strtotime($d)) : '-';
    }
}

// Validasi id loss
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit("Invalid loss id.");
}

$idloss = (int)$_GET['id'];

// ===== HEADER LOSS =====
$stmtH = $conn->prepare("
    SELECT 
        l.idloss,
        l.idreceive,
        l.idweigh,
        l.loss_no,
        l.loss_date,
        l.note,
        l.total_receive_weight,
        l.total_actual_weight,
        l.total_loss_weight,
        l.total_loss_cost,
        l.creatime,
        u.fullname AS createuser,

        w.weigh_no,
        w.weigh_date,

        r.receipt_date,
        r.doc_no,
        p.nopo,
        s.nmsupplier

    FROM cattle_loss_receive l
    JOIN weight_cattle w
          ON w.idweigh = l.idweigh AND w.is_deleted = 0
    JOIN cattle_receive r
          ON r.idreceive = l.idreceive AND r.is_deleted = 0
    JOIN pocattle p
          ON p.idpo = r.idpo AND p.is_deleted = 0
    JOIN supplier s
          ON s.idsupplier = p.idsupplier
    LEFT JOIN users u
          ON u.idusers = l.createby

    WHERE l.idloss = ? 
      AND l.is_deleted = 0
    LIMIT 1
");

$stmtH->bind_param("i", $idloss);
$stmtH->execute();
$loss = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$loss) {
    http_response_code(404);
    exit("Loss data not found for the given id.");
}

// Ringkasan header
$totalReceive = (float)$loss['total_receive_weight'];
$totalActual  = (float)$loss['total_actual_weight'];
$totalLoss    = (float)$loss['total_loss_weight'];
$totalCost    = (float)$loss['total_loss_cost'];

// ===== FIX: PERSENTASE SELALU NEGATIF =====
$lossPercentage = 0;
if ($totalReceive > 0) {
    $lossPercentage = - (abs($totalLoss) / $totalReceive) * 100;
}

// ===== DETAIL LOSS =====
$stmtD = $conn->prepare("
    SELECT 
        d.idlossdetail,
        d.eartag,
        d.cattle_class,
        d.receive_weight,
        d.actual_weight,
        d.loss_weight,
        d.price_perkg,
        d.loss_cost,
        d.notes
    FROM cattle_loss_receive_detail d
    WHERE d.idloss = ?
    ORDER BY d.eartag
");
$stmtD->bind_param("i", $loss['idloss']);
$stmtD->execute();
$resD = $stmtD->get_result();

$details = [];
while ($row = $resD->fetch_assoc()) {
    $details[] = $row;
}
$stmtD->close();

$heads = count($details);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= e($loss['loss_no']) . ' - ' . e($loss['nmsupplier']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../dist/img/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <style>
        :root {
            --accent: #f0ad4e;
            --ink: #111;
            --muted: #666;
            --line: #e7e7e7;
        }

        body {
            color: var(--ink);
            font-size: 13px;
        }

        .doc {
            max-width: 960px;
            margin: 24px auto 48px;
            padding: 0 16px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 12px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand img {
            height: 52px;
            width: auto;
        }

        .brand .name {
            font-size: 20px;
            font-weight: 700;
        }

        .brand .tag {
            font-size: 12px;
            color: var(--muted);
        }

        .title {
            margin: 18px 0 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .title h1 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .meta {
            margin-top: 12px;
            display: grid;
            grid-template-columns: auto 1fr auto 1fr;
            gap: 6px 16px;
        }

        .meta dt {
            font-weight: 600;
        }

        .meta dd {
            margin: 0;
        }

        table.loss-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .loss-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }

        .loss-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .loss-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .loss-table td.center {
            text-align: center;
        }

        .loss-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .note {
            margin-top: 12px;
        }

        .note .label {
            font-weight: 600;
        }

        .signs {
            margin-top: 34px;
            display: flex;
            justify-content: flex-end;
        }

        .sign-card {
            width: 260px;
            text-align: center;
        }

        .sign-card .muted {
            margin-bottom: 56px;
            color: var(--muted);
        }

        .sign-line {
            border-top: 1px dashed var(--line);
            padding-top: 6px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="doc">

        <div class="header">
            <div class="brand">
                <img src="../dist/img/logoSWM.png" alt="Logo">
                <div>
                    <div class="name">PT. SANTI WIJAYA MEAT</div>
                    <div class="tag">Committed to Meeting Your Need</div>
                </div>
            </div>
        </div>

        <div class="title">
            <h1>Cattle Weight Loss Report (Receiving)</h1>
            <div class="muted">Loss No: <?= e($loss['loss_no']); ?></div>
        </div>

        <!-- Meta -->
        <dl class="meta">

            <dt>Dibuat Oleh</dt>
            <dd><?= e($loss['createuser'] ?? '-'); ?></dd>

            <dt>Weigh No</dt>
            <dd><?= e($loss['weigh_no']); ?></dd>

            <dt>Tgl Timbang</dt>
            <dd><?= tgl($loss['weigh_date']); ?></dd>

            <dt>PO Number</dt>
            <dd><?= e($loss['nopo']); ?></dd>

            <dt>Supplier</dt>
            <dd><?= e($loss['nmsupplier']); ?></dd>

            <dt>Doc Receive</dt>
            <dd><?= e($loss['doc_no'] ?? '-'); ?></dd>

            <dt>Ekor</dt>
            <dd><?= number_format((int)$heads, 0, ',', '.'); ?></dd>

            <dt>Loss Percentage</dt>
            <dd><?= number_format($lossPercentage, 2, ',', '.') ?> %</dd>

        </dl>

        <!-- Items -->
        <table class="loss-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Eartag</th>
                    <th>Class</th>
                    <th>Harga / Kg</th>
                    <th>Berat Receive (Kg)</th>
                    <th>Berat Timbang (Kg)</th>
                    <th>Selisih (Kg)</th>
                    <th>Loss Cost (Rp)</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="9" class="center" style="color:#888;">No details</td>
                    </tr>
                    <?php else: $no = 1;
                    foreach ($details as $d):
                        $rw   = (float)$d['receive_weight'];
                        $aw   = (float)$d['actual_weight'];
                        $diff = (float)$d['loss_weight'];
                        $price = $d['price_perkg'];
                        $cost  = $d['loss_cost'];
                    ?>
                        <tr>
                            <td class="center"><?= $no++ ?></td>
                            <td><?= e($d['eartag']) ?></td>
                            <td class="center"><?= e($d['cattle_class']) ?></td>
                            <td class="num"><?= $price === null ? '-' : number_format((float)$price, 0, ',', '.') ?></td>
                            <td class="num"><?= number_format($rw, 2, ',', '.') ?></td>
                            <td class="num"><?= number_format($aw, 2, ',', '.') ?></td>
                            <td class="num"><?= number_format($diff, 2, ',', '.') ?></td>
                            <td class="num"><?= $cost === null ? '-' : number_format((float)$cost, 0, ',', '.') ?></td>
                            <td><?= e($d['notes'] ?? '') ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
            <tfoot class="text-right">
                <tr>
                    <th colspan="4">JUMLAH</th>
                    <th class="num"><?= number_format($totalReceive, 2, ',', '.'); ?></th>
                    <th class="num"><?= number_format($totalActual, 2, ',', '.'); ?></th>
                    <th class="num"><?= number_format($totalLoss, 2, ',', '.'); ?></th>
                    <th class="num"><?= number_format($totalCost, 0, ',', '.'); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <?php if (!empty($loss['note'])): ?>
            <div class="note">
                <div class="label">Catatan</div>
                <div><?= nl2br(e($loss['note'])) ?></div>
            </div>
        <?php endif; ?>

        <div class="signs">
            <div class="sign-card">
                <div class="muted">Prepared By</div>
                <div class="sign-line"><?= e($loss['createuser'] ?? '-'); ?></div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row mt-4 justify-content-center no-print">
            <div class="col-6 col-sm-4 col-md-3 mb-2">
                <a href="index.php" class="btn btn-success btn-block"><i class="fas fa-undo"></i></a>
            </div>
            <div class="col-6 col-sm-4 col-md-3">
                <button type="button" class="btn btn-warning btn-block" onclick="window.print()">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>

    </div>
</body>

</html>