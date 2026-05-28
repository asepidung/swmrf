<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Saat dev: munculkan error MySQLi (matikan di produksi)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helper (aman XSS)
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

// Validasi id weigh
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit("Invalid weigh id.");
}
$idweigh = (int)$_GET['id'];

// ===== HEADER =====
$stmtH = $conn->prepare("
    SELECT 
        w.idweigh,
        w.weigh_no,
        w.weigh_date,
        w.note,
        w.idreceive,
        r.receipt_date,
        r.doc_no,
        p.nopo,
        s.nmsupplier,
        u.fullname AS weigher_name,
        COUNT(d.idweighdetail)                      AS heads,
        COALESCE(SUM(crd.weight), 0)                AS total_receive_weight,
        COALESCE(SUM(d.weight), 0)                  AS total_actual_weight
    FROM weight_cattle w
    JOIN cattle_receive r
          ON r.idreceive = w.idreceive
    JOIN pocattle p
          ON p.idpo = r.idpo
    JOIN supplier s
          ON s.idsupplier = p.idsupplier
    LEFT JOIN users u
          ON u.idusers = w.idweigher
    LEFT JOIN weight_cattle_detail d
          ON d.idweigh = w.idweigh
    LEFT JOIN cattle_receive_detail crd
          ON crd.idreceivedetail = d.idreceivedetail
    WHERE w.idweigh = ? AND w.is_deleted = 0
    GROUP BY
        w.idweigh,
        w.weigh_no,
        w.weigh_date,
        w.note,
        w.idreceive,
        r.receipt_date,
        r.doc_no,
        p.nopo,
        s.nmsupplier,
        u.fullname
    LIMIT 1
");
$stmtH->bind_param("i", $idweigh);
$stmtH->execute();
$wgh = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$wgh) {
    http_response_code(404);
    exit("Weighing data not found for the given id.");
}

$totalReceive = (float)$wgh['total_receive_weight'];
$totalActual  = (float)$wgh['total_actual_weight'];
// Perbaikan: totalDiff = actual - receive (berat timbangan dikurangi berat penerimaan)
$totalDiff    = $totalActual - $totalReceive;

// ===== DETAIL =====
$stmtD = $conn->prepare("
    SELECT 
        d.idweighdetail,
        d.eartag,
        crd.weight AS receive_weight,
        d.weight   AS actual_weight,
        d.notes    AS detail_notes
    FROM weight_cattle_detail d
    JOIN cattle_receive_detail crd
          ON crd.idreceivedetail = d.idreceivedetail
    WHERE d.idweigh = ?
    ORDER BY d.eartag
");
$stmtD->bind_param("i", $wgh['idweigh']);
$stmtD->execute();
$resD = $stmtD->get_result();

$details = [];
while ($row = $resD->fetch_assoc()) {
    $rw = (float)$row['receive_weight'];
    $aw = (float)$row['actual_weight'];
    // Perbaikan: diff = actual - receive (berat timbangan dikurangi berat penerimaan)
    $row['diff'] = $aw - $rw;
    $details[] = $row;
}
$stmtD->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= e($wgh['weigh_no']) . ' - ' . e($wgh['nmsupplier']) ?></title>
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
            letter-spacing: .3px;
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
            letter-spacing: .5px;
        }

        .meta {
            margin-top: 12px;
            display: grid;
            grid-template-columns: auto 1fr auto 1fr;
            column-gap: 16px;
            row-gap: 6px;
            align-items: center;
        }

        .meta dt {
            font-weight: 600;
            margin: 0;
        }

        .meta dd {
            margin: 0;
        }

        table.wgh-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .wgh-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }

        .wgh-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .wgh-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .wgh-table td.center {
            text-align: center;
        }

        .wgh-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .totals {
            margin-top: 6px;
            display: flex;
            justify-content: flex-end;
        }

        .totals table {
            border-collapse: collapse;
        }

        .totals th,
        .totals td {
            padding: 6px 10px;
        }

        .totals th {
            text-align: right;
            color: var(--muted);
            font-weight: 600;
        }

        .totals td {
            text-align: right;
            min-width: 140px;
            border-bottom: 1px solid var(--line);
        }

        .note {
            margin-top: 12px;
        }

        .note .label {
            font-weight: 600;
            margin-bottom: 4px;
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

            .doc {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="doc">
        <!-- Header -->
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
            <h1>Cattle Weighing Report</h1>
            <div class="muted">Weight No: <?= e($wgh['weigh_no']); ?></div>
        </div>

        <!-- Meta -->
        <dl class="meta">
            <dt>Tgl Timbang</dt>
            <dd><?= tgl($wgh['weigh_date']); ?></dd>

            <dt>Petugas</dt>
            <dd><?= e($wgh['weigher_name'] ?? '-'); ?></dd>

            <dt>PO Number</dt>
            <dd><?= e($wgh['nopo']); ?></dd>

            <dt>Supplier</dt>
            <dd><?= e($wgh['nmsupplier']); ?></dd>

            <dt>Doc No</dt>
            <dd><?= e($wgh['doc_no'] ?? '-'); ?></dd>

            <dt>Ekor</dt>
            <dd><?= number_format((int)$wgh['heads'], 0, ',', '.'); ?></dd>
        </dl>

        <!-- Items -->
        <table class="wgh-table">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Eartag</th>
                    <th style="width:140px;">Berat Receive (Kg)</th>
                    <th style="width:140px;">Berat Timbang (Kg)</th>
                    <th style="width:140px;">Selisih (Kg)</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="6" class="center" style="color:#888;">No details</td>
                    </tr>
                    <?php else: $no = 1;
                    foreach ($details as $d):
                        $rw = (float)$d['receive_weight'];
                        $aw = (float)$d['actual_weight'];
                        $df = (float)$d['diff'];
                    ?>
                        <tr>
                            <td class="center"><?= $no++ ?></td>
                            <td><?= e($d['eartag']) ?></td>
                            <td class="num"><?= number_format($rw, 2, ',', '.') ?></td>
                            <td class="num"><?= number_format($aw, 2, ',', '.') ?></td>
                            <td class="num"><?= number_format($df, 2, ',', '.') ?></td>
                            <td><?= e($d['detail_notes']) ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <table>
                <tr>
                    <th>Total Receive</th>
                    <td><?= number_format($totalReceive, 2, ',', '.') ?> Kg</td>
                </tr>
                <tr>
                    <th>Total Timbang</th>
                    <td><?= number_format($totalActual, 2, ',', '.') ?> Kg</td>
                </tr>
                <tr>
                    <th>Total Selisih</th>
                    <td><?= number_format($totalDiff, 2, ',', '.') ?> Kg</td>
                </tr>
            </table>
        </div>

        <?php if (!empty($wgh['note'])): ?>
            <div class="note">
                <div class="label">Catatan</div>
                <div><?= nl2br(e($wgh['note'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- Signature: Weigher (kanan) -->
        <div class="signs">
            <div class="sign-card">
                <div class="muted">Weigher</div>
                <div class="sign-line"><?= e($wgh['weigher_name'] ?? '-'); ?></div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row mt-4 justify-content-center no-print">
            <div class="col-6 col-sm-4 col-md-3 mb-2">
                <a href="index.php" class="btn btn-success btn-block">
                    <i class="fas fa-undo"></i>
                </a>
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