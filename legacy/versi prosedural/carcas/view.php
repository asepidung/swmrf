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

// Validasi idcarcase
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit("Invalid carcase id.");
}
$idcarcase = (int)$_GET['id'];

// ===== HEADER =====
$stmtH = $conn->prepare("
    SELECT 
        c.idcarcase,
        c.killdate,
        c.note,
        c.idweight,
        c.idsupplier,
        s.nmsupplier,
        w.weigh_no,
        w.weigh_date,
        u.fullname AS user_name,
        COUNT(cd.iddetail)                                       AS heads,
        COALESCE(SUM(cd.berat), 0)                               AS total_live,
        COALESCE(SUM(cd.carcase1 + cd.carcase2), 0)              AS total_carcass,
        COALESCE(SUM(cd.hides), 0)                               AS total_hides,
        COALESCE(SUM(cd.tail), 0)                                AS total_tails
    FROM carcase c
    LEFT JOIN supplier s      ON s.idsupplier = c.idsupplier
    LEFT JOIN weight_cattle w ON w.idweigh    = c.idweight
    LEFT JOIN users u         ON u.idusers    = c.idusers
    LEFT JOIN carcasedetail cd
           ON cd.idcarcase    = c.idcarcase
    WHERE c.idcarcase = ? AND c.is_deleted = 0
    GROUP BY
        c.idcarcase,
        c.killdate,
        c.note,
        c.idweight,
        c.idsupplier,
        s.nmsupplier,
        w.weigh_no,
        w.weigh_date,
        u.fullname
    LIMIT 1
");
$stmtH->bind_param("i", $idcarcase);
$stmtH->execute();
$carc = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$carc) {
    http_response_code(404);
    exit("Carcas data not found for the given id.");
}

$totalLive    = (float)$carc['total_live'];
$totalCarcass = (float)$carc['total_carcass'];
$totalHides   = (float)$carc['total_hides'];
$totalTails   = (float)$carc['total_tails'];
$heads        = (int)$carc['heads'];

$yield = $totalLive > 0 ? ($totalCarcass / $totalLive * 100) : 0;

// Offal = total carcase + total tails
$offal = $totalCarcass + $totalTails;


// ===== DETAIL =====
$stmtD = $conn->prepare("
    SELECT 
        cd.iddetail,
        cd.eartag,
        cd.breed,
        cd.berat,
        cd.carcase1,
        cd.carcase2,
        cd.hides,
        cd.tail
    FROM carcasedetail cd
    WHERE cd.idcarcase = ?
    ORDER BY cd.eartag
");
$stmtD->bind_param("i", $idcarcase);
$stmtD->execute();
$resD = $stmtD->get_result();

$details = [];
while ($row = $resD->fetch_assoc()) {
    $live  = (float)$row['berat'];
    $c1    = (float)$row['carcase1'];
    $c2    = (float)$row['carcase2'];
    $total = $c1 + $c2;
    $row['total_carc'] = $total;
    $row['yield'] = $live > 0 ? ($total / $live * 100) : 0;
    $details[] = $row;
}
$stmtD->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= e('Carcas - ' . $carc['nmsupplier'] . ' - ' . tgl($carc['killdate'])) ?></title>
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

        table.car-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .car-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }

        .car-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .car-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .car-table td.center {
            text-align: center;
        }

        .car-table tbody tr:nth-child(even) {
            background: #fcfcfc;
        }

        .totals {
            margin-top: 8px;
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
            <h1>Carcas Report</h1>
            <div class="muted">
                Kill Date: <?= tgl($carc['killdate']); ?>
            </div>
        </div>

        <!-- Meta -->
        <dl class="meta">
            <dt>Supplier</dt>
            <dd><?= e($carc['nmsupplier'] ?? '-'); ?></dd>

            <dt>No Weighing</dt>
            <dd><?= e($carc['weigh_no'] ?? '-'); ?></dd>

            <dt>Tgl Timbang</dt>
            <dd><?= tgl($carc['weigh_date']); ?></dd>

            <dt>Heads</dt>
            <dd><?= number_format($heads, 0, ',', '.'); ?></dd>
        </dl>


        <!-- Items -->
        <table class="car-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th style="width:90px;">Eartag</th>
                    <th style="width:80px;">Class</th>
                    <th style="width:110px;">Receive Wt (Kg)</th>
                    <th style="width:110px;">Carcase A (Kg)</th>
                    <th style="width:110px;">Carcase B (Kg)</th>
                    <th style="width:120px;">Total Carcase (Kg)</th>
                    <th style="width:90px;">Hides (Kg)</th>
                    <th style="width:90px;">Tail (Kg)</th>
                    <th style="width:90px;">Yield (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="10" class="center" style="color:#888;">No details</td>
                    </tr>
                    <?php else: $no = 1;
                    foreach ($details as $d): ?>
                        <tr>
                            <td class="center"><?= $no++; ?></td>
                            <td class="center"><?= e($d['eartag']); ?></td>
                            <td class="center"><?= e($d['breed']); ?></td>
                            <td class="num"><?= number_format((float)$d['berat'], 2, ',', '.'); ?></td>
                            <td class="num"><?= number_format((float)$d['carcase1'], 2, ',', '.'); ?></td>
                            <td class="num"><?= number_format((float)$d['carcase2'], 2, ',', '.'); ?></td>
                            <td class="num"><?= number_format((float)$d['total_carc'], 2, ',', '.'); ?></td>
                            <td class="num"><?= number_format((float)$d['hides'], 2, ',', '.'); ?></td>
                            <td class="num"><?= number_format((float)$d['tail'], 2, ',', '.'); ?></td>
                            <td class="num">
                                <?= ((float)$d['yield'] > 0) ? number_format((float)$d['yield'], 2, ',', '.') : '-'; ?>
                            </td>
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
                    <td><?= number_format($totalLive, 2, ',', '.'); ?> Kg</td>
                </tr>
                <tr>
                    <th>Offal</th>
                    <td><?= number_format($offal, 2, ',', '.'); ?> Kg</td>
                </tr>
                <tr>
                    <th>Total Hides</th>
                    <td><?= number_format($totalHides, 2, ',', '.'); ?> Kg</td>
                </tr>
                <tr>
                    <th>Total Tails</th>
                    <td><?= number_format($totalTails, 2, ',', '.'); ?> Kg</td>
                </tr>
                <tr>
                    <th>Carcase Yield</th>
                    <td><?= $totalLive > 0 ? number_format($yield, 2, ',', '.') . ' %' : '-'; ?></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($carc['note'])): ?>
            <div class="note">
                <div class="label">Catatan</div>
                <div><?= nl2br(e($carc['note'])); ?></div>
            </div>
        <?php endif; ?>

        <!-- Signature -->
        <div class="signs">
            <div class="sign-card">
                <div class="muted">Prepared by</div>
                <div class="sign-line"><?= e($carc['user_name'] ?? '-'); ?></div>
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