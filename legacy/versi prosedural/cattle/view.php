<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../inv/terbilang.php"; // untuk terbilang

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

// Validasi id
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit("Invalid PO id.");
}
$idpo = (int)$_GET['id'];

// ===== HEADER ===== (tanpa kolom status)
$stmtH = $conn->prepare("
  SELECT p.idpo, p.nopo, p.podate, p.arrival_date, p.note, p.creatime,
         p.createby, u.fullname AS prepared_by,
         s.nmsupplier
  FROM pocattle p
  INNER JOIN supplier s ON s.idsupplier = p.idsupplier
  LEFT  JOIN users u    ON u.idusers   = p.createby
  WHERE p.idpo = ? AND p.is_deleted = 0
  LIMIT 1
");
$stmtH->bind_param("i", $idpo);
$stmtH->execute();
$po = $stmtH->get_result()->fetch_assoc();
if (!$po) {
    http_response_code(404);
    exit("PO Cattle not found for the given id.");
}

// ===== DETAIL =====
$stmtD = $conn->prepare("
  SELECT d.idpodetail, d.class, d.qty, d.price, d.notes
  FROM pocattledetail d
  WHERE d.idpo = ? AND d.is_deleted = 0
  ORDER BY d.idpodetail ASC
");
$stmtD->bind_param("i", $po['idpo']);
$stmtD->execute();
$resD = $stmtD->get_result();

$totalQty = 0;
$totalAmount = 0.0;
$details = [];
while ($row = $resD->fetch_assoc()) {
    $qty      = (int)$row['qty'];
    $price    = is_null($row['price']) ? null : (float)$row['price'];
    $subtotal = $price === null ? 0.0 : ($qty * $price);
    $totalQty    += $qty;
    $totalAmount += $subtotal;
    $row['subtotal'] = $subtotal;
    $details[] = $row;
}

// Terbilang total (dibulatkan ke integer rupiah)
$totalInWords = terbilang((int)round($totalAmount)) . " Rupiah";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= e($po['nopo']) . ' - ' . e($po['nmsupplier']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../dist/img/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <style>
        :root {
            --accent: #f0ad4e;
            /* aksen brand opsional */
            --ink: #111;
            /* teks utama */
            --muted: #666;
            /* teks sekunder */
            --line: #e7e7e7;
            /* garis tabel */
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

        /* Meta compact: label-nilai, label-nilai */
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

        table.po-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .po-table thead th {
            background: #fafafa;
            border: 1px solid var(--line);
            font-weight: 600;
            text-align: center;
            padding: 8px;
        }

        .po-table td {
            border: 1px solid var(--line);
            padding: 8px;
        }

        .po-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .po-table td.center {
            text-align: center;
        }

        .po-table tbody tr:nth-child(even) {
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

        /* Signature di kanan */
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
            <h1>PURCHASE ORDER</h1>
            <div class="muted">NPWP: 94.813.835.9-436.000</div>
        </div>

        <!-- Meta (compact) -->
        <dl class="meta">
            <dt>PO Number</dt>
            <dd><?= e($po['nopo']) ?></dd>
            <dt>Arrival Date</dt>
            <dd><?= tgl($po['arrival_date']) ?></dd>

            <dt>PO Date</dt>
            <dd><?= tgl($po['podate']) ?></dd>
            <dt>Supplier</dt>
            <dd><?= e($po['nmsupplier']) ?></dd>
        </dl>

        <!-- Items -->
        <table class="po-table">
            <thead>
                <tr>
                    <th style="width:52px;">#</th>
                    <th>Cattle Class</th>
                    <th style="width:120px;">Qty (Head)</th>
                    <th style="width:140px;">Price / Kg</th>
                    <th style="width:160px;">Subtotal</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($details)): ?>
                    <tr>
                        <td colspan="6" class="center" style="color:#888;">No details</td>
                    </tr>
                    <?php else: $no = 1;
                    foreach ($details as $d): ?>
                        <tr>
                            <td class="center"><?= $no++ ?></td>
                            <td><?= e($d['class']) ?></td>
                            <td class="num"><?= number_format((int)$d['qty'], 0, ',', '.') ?></td>
                            <td class="num"><?= is_null($d['price']) ? '-' : number_format((float)$d['price'], 2, ',', '.') ?></td>
                            <td class="num"><?= number_format((float)$d['subtotal'], 2, ',', '.') ?></td>
                            <td><?= e($d['notes']) ?></td>
                        </tr>
                <?php endforeach;
                endif; ?>
            </tbody>
        </table>

        <!-- Totals + Terbilang -->
        <div class="totals">
            <table>
                <tr>
                    <th>Total Qty</th>
                    <td><?= number_format($totalQty, 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <th>Total Amount</th>
                    <td><?= number_format($totalAmount, 2, ',', '.') ?></td>
                </tr>
            </table>
        </div>

        <div class="note">
            <div class="label">Amount in words</div>
            <div><em><?= e($totalInWords) ?></em></div>
        </div>

        <?php if (!empty($po['note'])): ?>
            <div class="note">
                <div class="label">Note</div>
                <div><?= nl2br(e($po['note'])) ?></div>
            </div>
        <?php endif; ?>

        <!-- Signature: Prepared by (kanan) -->
        <div class="signs">
            <div class="sign-card">
                <div class="muted">Prepared by</div>
                <div class="sign-line"><?= e($po['prepared_by'] ?? '-') ?></div>
            </div>
        </div>

        <!-- Actions -->
        <div class="row mt-4 justify-content-center no-print">
            <div class="col-6 col-sm-4 col-md-3 mb-2">
                <a href="index.php" class="btn btn-success btn-block"><i class="fas fa-undo"></i></a>
            </div>
            <div class="col-6 col-sm-4 col-md-3">
                <button type="button" class="btn btn-warning btn-block" onclick="window.print()"><i class="fas fa-print"></i></button>
            </div>
        </div>
    </div>
</body>

</html>