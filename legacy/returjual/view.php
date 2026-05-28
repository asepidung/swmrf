<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// tampilkan error saat dev
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// helper
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

// validasi id
if (!isset($_GET['idreturjual']) || !ctype_digit($_GET['idreturjual'])) {
    http_response_code(400);
    exit("Invalid retur id");
}
$idreturjual = (int)$_GET['idreturjual'];

/* ======================
   HEADER RETUR
   ====================== */
$stmtH = $conn->prepare("
    SELECT 
        r.idreturjual,
        r.returnnumber,
        r.returdate,
        r.donumber,
        r.note,
        r.status,
        c.nama_customer,
        u.fullname,
        COUNT(d.idreturjualdetail)          AS items,
        COALESCE(SUM(d.qty),0)              AS total_qty
    FROM returjual r
    JOIN customers c ON r.idcustomer = c.idcustomer
    LEFT JOIN users u ON r.idusers = u.idusers
    LEFT JOIN returjualdetail d 
        ON d.idreturjual = r.idreturjual AND d.is_deleted = 0
    WHERE r.idreturjual = ?
      AND r.is_deleted = 0
    GROUP BY r.idreturjual
    LIMIT 1
");
$stmtH->bind_param("i", $idreturjual);
$stmtH->execute();
$hdr = $stmtH->get_result()->fetch_assoc();
$stmtH->close();

if (!$hdr) {
    http_response_code(404);
    exit("Data retur tidak ditemukan");
}

/* ======================
   DETAIL RETUR
   ====================== */
$stmtD = $conn->prepare("
    SELECT 
        d.kdbarcode,
        b.nmbarang,
        g.nmgrade,
        d.qty,
        d.pcs,
        d.ph,
        d.pod
    FROM returjualdetail d
    LEFT JOIN barang b ON d.idbarang = b.idbarang
    LEFT JOIN grade g ON d.idgrade = g.idgrade
    WHERE d.idreturjual = ?
      AND d.is_deleted = 0
    ORDER BY d.idreturjualdetail DESC
");
$stmtD->bind_param("i", $idreturjual);
$stmtD->execute();
$resD = $stmtD->get_result();
$details = $resD->fetch_all(MYSQLI_ASSOC);
$stmtD->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= e($hdr['returnnumber']) ?> - Retur Jual</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="../dist/img/favicon.png">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">

    <style>
        body {
            font-size: 13px;
            color: #111;
        }

        .doc {
            max-width: 960px;
            margin: 24px auto 48px;
            padding: 0 16px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header img {
            height: 50px;
        }

        .brand .name {
            font-size: 20px;
            font-weight: 700;
        }

        .brand .tag {
            font-size: 12px;
            color: #666;
        }

        .title {
            margin: 18px 0 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .title h1 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .meta {
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

        table.tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .tbl th,
        .tbl td {
            border: 1px solid #e5e5e5;
            padding: 7px;
        }

        .tbl th {
            background: #fafafa;
            text-align: center;
            font-weight: 600;
        }

        .tbl td.num {
            text-align: right;
        }

        .tbl td.center {
            text-align: center;
        }

        .totals {
            margin-top: 8px;
            display: flex;
            justify-content: flex-end;
        }

        .totals table td {
            padding: 6px 10px;
            text-align: right;
        }

        .note {
            margin-top: 14px;
        }

        .note .label {
            font-weight: 600;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                -webkit-print-color-adjust: exact;
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

        <!-- HEADER -->
        <div class="header">
            <img src="../dist/img/logoSWM.png" alt="Logo">
            <div class="brand">
                <div class="name">PT. SANTI WIJAYA MEAT</div>
                <div class="tag">Committed to Meeting Your Need</div>
            </div>
        </div>

        <div class="title">
            <h1>Sales Return</h1>
            <div>Status : <strong><?= e($hdr['status']) ?></strong></div>
        </div>

        <!-- META -->
        <dl class="meta">
            <dt>Return No</dt>
            <dd><?= e($hdr['returnnumber']) ?></dd>
            <dt>Tanggal</dt>
            <dd><?= tgl($hdr['returdate']) ?></dd>

            <dt>Customer</dt>
            <dd><?= e($hdr['nama_customer']) ?></dd>
            <dt>DO Number</dt>
            <dd><?= e($hdr['donumber'] ?? '-') ?></dd>

            <dt>Dibuat Oleh</dt>
            <dd><?= e($hdr['fullname'] ?? '-') ?></dd>
            <dt>Item</dt>
            <dd><?= (int)$hdr['items'] ?> Baris</dd>
        </dl>

        <!-- DETAIL -->
        <table class="tbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Barcode</th>
                    <th>Item</th>
                    <th>Grade</th>
                    <th>Qty</th>
                    <th>PCS</th>
                    <th>pH</th>
                    <th>POD</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="8" class="center text-muted">Tidak ada data</td>
                    </tr>
                    <?php else: $no = 1;
                    foreach ($details as $d): ?>
                        <tr>
                            <td class="center"><?= $no++ ?></td>
                            <td><?= e($d['kdbarcode']) ?></td>
                            <td><?= e($d['nmbarang']) ?></td>
                            <td class="center"><?= e($d['nmgrade']) ?></td>
                            <td class="num"><?= number_format($d['qty'], 2) ?></td>
                            <td class="center"><?= e($d['pcs'] ?? '') ?></td>
                            <td class="center"><?= e($d['ph'] ?? '') ?></td>
                            <td class="center"><?= tgl($d['pod']) ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>

        <!-- TOTAL -->
        <div class="totals">
            <table>
                <tr>
                    <td><strong>Total Qty</strong></td>
                    <td><strong><?= number_format($hdr['total_qty'], 2) ?></strong></td>
                </tr>
            </table>
        </div>

        <?php if (!empty($hdr['note'])): ?>
            <div class="note">
                <div class="label">Catatan</div>
                <div><?= nl2br(e($hdr['note'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- ACTION -->
        <div class="row mt-4 justify-content-center no-print">
            <div class="col-6 col-md-3 mb-2">
                <a href="index.php" class="btn btn-success btn-block">
                    <i class="fas fa-undo"></i> Kembali
                </a>
            </div>
            <div class="col-6 col-md-3">
                <button onclick="window.print()" class="btn btn-warning btn-block">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

    </div>
</body>

</html>