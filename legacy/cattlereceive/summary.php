<?php
require "../verifications/auth.php";
require "../konak/conn.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// helper
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function tgl($d)
{
    return $d ? date('d-m-Y', strtotime($d)) : '-';
}

/* ================= VALIDASI ================= */
if (empty($_GET['idreceive']) || !ctype_digit($_GET['idreceive'])) {
    exit("Invalid receive id");
}
$idreceive = (int)$_GET['idreceive'];

/* ================= HEADER ================= */
$stmtH = $conn->prepare("
    SELECT 
        r.idreceive,
        r.receipt_date,
        r.createby,
        p.nopo,
        s.nmsupplier,
        u.fullname AS created_name
    FROM cattle_receive r
    JOIN pocattle p ON p.idpo = r.idpo
    JOIN supplier s ON s.idsupplier = p.idsupplier
    LEFT JOIN users u ON u.idusers = r.createby
    WHERE r.idreceive = ?
    LIMIT 1
");
$stmtH->bind_param("i", $idreceive);
$stmtH->execute();
$head = $stmtH->get_result()->fetch_assoc();

if (!$head) {
    exit("Data not found");
}

/* ================= SUMMARY ================= */
$stmt = $conn->prepare("
    SELECT 
        class,
        COUNT(*) AS qty,
        SUM(weight) AS total_weight
    FROM cattle_receive_detail
    WHERE idreceive = ?
    GROUP BY class
    ORDER BY class
");
$stmt->bind_param("i", $idreceive);
$stmt->execute();
$data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ================= TOTAL ================= */
$grandQty = 0;
$grandWeight = 0;
foreach ($data as $d) {
    $grandQty += (int)$d['qty'];
    $grandWeight += (float)$d['total_weight'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Summary Cattle Receive</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #000;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 5px;
            text-align: center;
        }

        .header-info {
            margin-bottom: 15px;
        }

        .header-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-info td {
            padding: 3px 5px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
        }

        table.report th,
        table.report td {
            border: 1px solid #000;
            padding: 6px;
        }

        table.report th {
            background: #f2f2f2;
            text-align: center;
        }

        table.report td {
            vertical-align: middle;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .signature {
            margin-top: 40px;
            width: 100%;
        }

        .signature td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            height: 80px;
        }

        .no-print {
            margin-bottom: 10px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">Print</button>
    <a href="view.php?id=<?= $idreceive ?>">Kembali</a>
</div>

<h1>SUMMARY CATTLE RECEIVE</h1>

<div class="header-info">
    <table>
        <tr>
            <td width="15%"><strong>PO Number</strong></td>
            <td width="35%">: <?= e($head['nopo']) ?></td>
            <td width="15%"><strong>Supplier</strong></td>
            <td width="35%">: <?= e($head['nmsupplier']) ?></td>
        </tr>
        <tr>
            <td><strong>Receipt Date</strong></td>
            <td>: <?= tgl($head['receipt_date']) ?></td>
            <td><strong>Printed Date</strong></td>
            <td>: <?= date('d-m-Y') ?></td>
        </tr>
    </table>
</div>

<table class="report">
    <thead>
        <tr>
            <th width="5%">No</th>
            <th>Class</th>
            <th width="15%">Qty</th>
            <th width="25%">Total Weight (Kg)</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($data)): ?>
            <tr>
                <td colspan="4" class="text-center">No data</td>
            </tr>
        <?php else: $no = 1; foreach ($data as $r): ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= e($r['class']) ?></td>
                <td class="text-center"><?= number_format($r['qty'], 0) ?></td>
                <td class="text-right"><?= number_format($r['total_weight'], 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="2" class="text-right">TOTAL</th>
            <th class="text-center"><?= number_format($grandQty, 0) ?></th>
            <th class="text-right"><?= number_format($grandWeight, 2, ',', '.') ?></th>
        </tr>
    </tfoot>
</table>

<table class="signature">
    <tr>
        <td>
            Diterima / Diinput oleh,<br><br><br><br><br><br>
            <strong><?= e($head['created_name'] ?? '-') ?></strong>
        </td>
        <td>
            Diketahui,<br><br><br><br><br><br>
            _______________________
        </td>
    </tr>
</table>

</body>
</html>
