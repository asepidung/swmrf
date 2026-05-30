<?php
// reports/sales_standalone.php
// Standalone report page: hanya require verifications & db connection

require "../verifications/auth.php";
require "../konak/conn.php";

// DEBUG (dev): tampilkan error mysqli; matikan di production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

// Helper aman XSS & format tanggal
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

// Ambil tahun (default = tahun sekarang)
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// default awal/akhir (informasi)
$awal = isset($_GET['awal']) ? $_GET['awal'] : date($year . '-01-01');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : date('Y-m-d');

// month labels
$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// init 12 months
function initMonths()
{
    $a = [];
    for ($m = 1; $m <= 12; $m++) $a[$m] = 0.0;
    return $a;
}

// query monthly sums from invoice.xamount
function getMonthlySales($conn, $year_val)
{
    $sql = "
      SELECT MONTH(`invoice_date`) AS mon, COALESCE(SUM(`xamount`),0) AS total
      FROM `invoice`
      WHERE `is_deleted` = 0
        AND YEAR(`invoice_date`) = {$year_val}
      GROUP BY MONTH(`invoice_date`)
    ";
    $res = mysqli_query($conn, $sql);
    if ($res === false) {
        // return false to indicate error to caller
        return false;
    }
    $months = initMonths();
    while ($r = mysqli_fetch_assoc($res)) {
        $m = intval($r['mon']);
        $months[$m] = floatval($r['total']);
    }
    return $months;
}

$year_cur = intval($year);
$year_prev = $year_cur - 1;

$months_cur_raw = getMonthlySales($conn, $year_cur);
$months_prev = getMonthlySales($conn, $year_prev);

if ($months_cur_raw === false || $months_prev === false) {
    http_response_code(500);
    echo "SQL error fetching monthly sales: " . mysqli_error($conn);
    exit;
}

// if current year, set future months to null (so Chart.js will break the line)
$currentYear = intval(date('Y'));
$currentMonth = intval(date('n'));

$data_cur = [];
for ($m = 1; $m <= 12; $m++) {
    $val = isset($months_cur_raw[$m]) ? round($months_cur_raw[$m], 2) : 0;
    if ($year_cur === $currentYear && $m > $currentMonth) $data_cur[] = null;
    else $data_cur[] = $val;
}

$data_prev = [];
for ($m = 1; $m <= 12; $m++) $data_prev[] = round($months_prev[$m], 2);

// totals (only include non-null months for current)
$total_cur = 0;
for ($i = 0; $i < 12; $i++) if ($data_cur[$i] !== null) $total_cur += $data_cur[$i];
$total_prev = array_sum($data_prev);

// percentage change
if ($total_prev == 0) $change_pct = ($total_cur == 0) ? 0 : 100;
else $change_pct = (($total_cur - $total_prev) / $total_prev) * 100;

// simple number format for PHP display
function fmt_no_dec($v)
{
    return number_format(round($v), 0, ',', '.');
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Sales Yearly Overview - <?= e($year_cur) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- local assets (sesuaikan path proyekmu) -->
    <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 14px;
            margin: 16px;
            color: #222;
        }

        .wrap {
            max-width: 1100px;
            margin: 0 auto;
        }

        .card {
            border: 1px solid #e3e3e3;
            border-radius: 6px;
            margin-bottom: 16px;
            box-shadow: none;
        }

        .card .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card .card-body {
            padding: 16px;
        }

        .muted {
            color: #777;
            font-size: 13px;
        }

        /* print rules: hide controls but show charts */
        @media print {

            .no-print,
            .no-print * {
                display: none !important;
            }

            body {
                margin: 0;
            }

            canvas {
                display: block !important;
                max-width: 100% !important;
                height: auto !important;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table th,
        table td {
            padding: 8px;
            border: 1px solid #eee;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="card-header">
                <div>
                    <h4 style="margin:0">Sales — Yearly Overview</h4>
                    <div class="muted">Per bulan (Jan–Dec). Tahun: <?= e($year_cur) ?> — vs <?= e($year_prev) ?></div>
                </div>
                <div class="no-print">
                    <form method="GET" style="display:inline-block;margin-right:8px;">
                        <select name="year" onchange="this.form.submit()" class="form-control form-control-sm">
                            <?php for ($y = intval(date('Y')); $y >= intval(date('Y')) - 5; $y--): ?>
                                <option value="<?= $y ?>" <?= $y == $year_cur ? 'selected' : ''; ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                        <input type="hidden" name="awal" value="<?= e($awal) ?>">
                        <input type="hidden" name="akhir" value="<?= e($akhir) ?>">
                    </form>
                    <button id="btnPrint" class="btn btn-sm btn-outline-primary"><i class="fas fa-print"></i> Print / PDF</button>
                </div>
            </div>
            <div class="card-body">
                <div style="display:flex; gap:16px; align-items:center;">
                    <div>
                        <div style="font-size:20px;font-weight:700">Rp <?= fmt_no_dec($total_cur) ?></div>
                        <div class="muted">Sales in <?= e($year_cur) ?></div>
                    </div>
                    <div style="margin-left:auto;text-align:right;">
                        <div class="<?= $change_pct >= 0 ? 'text-success' : 'text-danger' ?>" style="font-weight:700">
                            <?php if ($change_pct >= 0): ?><i class="fas fa-arrow-up"></i><?php else: ?><i class="fas fa-arrow-down"></i><?php endif; ?>
                            <?= number_format(abs($change_pct), 2) ?>%
                        </div>
                        <div class="muted">vs <?= e($year_prev) ?></div>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <canvas id="salesChart" height="240" style="width:100%;display:block"></canvas>
                </div>

                <div style="margin-top:12px;display:flex;gap:12px; justify-content:flex-end;">
                    <div><span style="display:inline-block;width:12px;height:12px;background:#007bff;margin-right:6px;vertical-align:middle"></span><?= e($year_cur) ?></div>
                    <div><span style="display:inline-block;width:12px;height:12px;background:#9aa0a6;margin-right:6px;vertical-align:middle"></span><?= e($year_prev) ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Detail Bulanan</strong></div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="text-right">Total <?= e($year_cur) ?></th>
                            <th class="text-right">Total <?= e($year_prev) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < 12; $i++): ?>
                            <tr>
                                <td><?= e($monthLabels[$i]) ?></td>
                                <td class="text-right">Rp <?= fmt_no_dec($data_cur[$i] === null ? 0 : $data_cur[$i]) ?></td>
                                <td class="text-right">Rp <?= fmt_no_dec($data_prev[$i]) ?></td>
                            </tr>
                        <?php endfor; ?>
                        <tr style="font-weight:700">
                            <td>Total</td>
                            <td class="text-right">Rp <?= fmt_no_dec($total_cur) ?></td>
                            <td class="text-right">Rp <?= fmt_no_dec($total_prev) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="text-align:center;margin:20px 0" class="no-print">
            <small class="muted">Generated: <?= date('d-M-Y H:i') ?></small>
        </div>
    </div>

    <!-- Chart.js (sesuaikan path) -->
    <script src="../plugins/chart.js/Chart.min.js"></script>
    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const monthLabels = <?= json_encode($monthLabels) ?>;
        const dataCur = <?= json_encode($data_cur) ?>;
        const dataPrev = <?= json_encode($data_prev) ?>;

        // simple chart (line area)
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [{
                        label: '<?= $year_cur ?>',
                        data: dataCur,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0,123,255,0.12)',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        spanGaps: false,
                        fill: true
                    },
                    {
                        label: '<?= $year_prev ?>',
                        data: dataPrev,
                        borderColor: '#9aa0a6',
                        backgroundColor: 'rgba(160,160,160,0.08)',
                        pointRadius: 3,
                        tension: 0.3,
                        spanGaps: true,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let v = null;
                                if (context.parsed && typeof context.parsed.y !== 'undefined') v = context.parsed.y;
                                else if (typeof context.raw !== 'undefined') v = context.raw;
                                if (v === null || v === undefined) return context.dataset.label + ': -';
                                return context.dataset.label + ': Rp ' + (Math.round(Number(v))).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) {
                                return 'Rp ' + Math.round(Number(v)).toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // print button: ensure chart resize then print
        document.getElementById('btnPrint').addEventListener('click', function() {
            try {
                salesChart.resize();
            } catch (e) {}
            setTimeout(function() {
                window.print();
            }, 150);
        });
    </script>
</body>

</html>