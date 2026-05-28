<?php
// reports/fast_moving_standalone.php
// Standalone Fast Moving report (only require auth + conn)

require "../verifications/auth.php";
require "../konak/conn.php";

// Development helpers (tampilkan error saat dev; matikan di production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

// Safe helpers
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

// --- Input & defaults ---
$awal = isset($_GET['awal']) ? $_GET['awal'] : date('Y-m-01');
$queryMaxDate = "SELECT MAX(`deliverydate`) AS max_date FROM `do`";
$resultMaxDate = mysqli_query($conn, $queryMaxDate);
if ($resultMaxDate === false) {
    http_response_code(500);
    echo "SQL Error (max date): " . e(mysqli_error($conn));
    exit;
}
$rowMaxDate = mysqli_fetch_assoc($resultMaxDate);
$maxDate = $rowMaxDate['max_date'] ?: date('Y-m-d');
$akhir = isset($_GET['akhir']) ? $_GET['akhir'] : $maxDate;

$topN = isset($_GET['top']) ? intval($_GET['top']) : 10;
if ($topN <= 0) $topN = 10;

// statuses excluded
$excluded_status_list = "'reject','rejected','cancel','void','rejected_by_customer'";

// sanitize for queries
$awal_esc = mysqli_real_escape_string($conn, $awal);
$akhir_esc = mysqli_real_escape_string($conn, $akhir);

// --- Get cuts that have DO in range, ordered: PRIME CUT, SECONDARY CUT, BONES, then idcut ---
$sqlCuts = "
SELECT c.idcut, COALESCE(c.nmcut, CONCAT('Cut ', c.idcut)) AS nmcut
FROM cuts c
JOIN barang b ON b.idcut = c.idcut
JOIN dodetail dd ON dd.idbarang = b.idbarang
JOIN `do` d ON d.iddo = dd.iddo
WHERE d.is_deleted = 0
  AND (d.deliverydate BETWEEN '{$awal_esc}' AND '{$akhir_esc}')
  AND (d.status IS NULL OR LOWER(d.status) NOT IN ({$excluded_status_list}))
GROUP BY c.idcut, c.nmcut
ORDER BY
  CASE WHEN UPPER(COALESCE(c.nmcut,'')) = 'PRIME CUT' THEN 1
       WHEN UPPER(COALESCE(c.nmcut,'')) = 'SECONDARY CUT' THEN 2
       WHEN UPPER(COALESCE(c.nmcut,'')) = 'BONES' THEN 3
       ELSE 4 END,
  c.idcut
";
$resCuts = mysqli_query($conn, $sqlCuts);
if ($resCuts === false) {
    http_response_code(500);
    echo "SQL Error (cuts): " . e(mysqli_error($conn));
    exit;
}
$cuts = [];
while ($r = mysqli_fetch_assoc($resCuts)) {
    $cuts[] = ['idcut' => (int)$r['idcut'], 'nmcut' => $r['nmcut']];
}

// --- For each cut, get top N items by count of distinct DO and sum weight ---
$charts = [];
foreach ($cuts as $c) {
    $idcut = (int)$c['idcut'];
    $limit = (int)$topN;

    $sqlTop = "
    SELECT b.idbarang, b.nmbarang,
           COUNT(DISTINCT dd.iddo) AS freq_do,
           COALESCE(SUM(dd.weight),0) AS total_qty
    FROM dodetail dd
    JOIN `do` d ON d.iddo = dd.iddo
    JOIN barang b ON b.idbarang = dd.idbarang
    WHERE d.is_deleted = 0
      AND (d.deliverydate BETWEEN '{$awal_esc}' AND '{$akhir_esc}')
      AND (d.status IS NULL OR LOWER(d.status) NOT IN ({$excluded_status_list}))
      AND b.idcut = {$idcut}
    GROUP BY b.idbarang, b.nmbarang
    ORDER BY freq_do DESC
    LIMIT {$limit}
    ";
    $resTop = mysqli_query($conn, $sqlTop);
    if ($resTop === false) {
        http_response_code(500);
        echo "SQL Error (top for idcut {$idcut}): " . e(mysqli_error($conn));
        exit;
    }

    $labels = [];
    $data = [];
    $qtys = [];
    while ($row = mysqli_fetch_assoc($resTop)) {
        $labels[] = $row['nmbarang'];
        $data[]   = (int)$row['freq_do'];
        $qtys[]   = (float)$row['total_qty'];
    }

    $charts[$idcut] = [
        'label' => $c['nmcut'],
        'labels' => $labels,
        'data' => $data,
        'qty' => $qtys
    ];
}

// default active tab: prefer PRIME CUT
$defaultActiveIdcut = null;
foreach ($cuts as $c) {
    if (strtoupper($c['nmcut']) === 'PRIME CUT') {
        $defaultActiveIdcut = $c['idcut'];
        break;
    }
}
if ($defaultActiveIdcut === null && count($cuts) > 0) $defaultActiveIdcut = $cuts[0]['idcut'];

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Produk Fast Moving</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- CDN styles (Bootstrap 4) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: #f5f7fb;
            color: #222;
            padding: 12px;
        }

        .card {
            border-radius: 6px;
            box-shadow: none;
        }

        .nav-tabs .nav-link {
            cursor: pointer;
        }

        .chart-wrap {
            min-height: 320px;
        }

        /* small responsive tweak */
        @media (max-width:576px) {
            .chart-wrap {
                min-height: 260px;
            }
        }

        /* tanda .no-print punya property agar lint tidak complain */
        .no-print {
            display: inline-block;
        }

        @media print {

            /* sembunyikan kontrol yang tidak mau dicetak */
            .no-print,
            .no-print * {
                display: none !important;
            }

            /* kosongkan padding/body */
            body {
                margin: 0;
                padding: 0;
                background: #fff;
            }

            /* pastikan canvas terlihat di print */
            canvas {
                display: block !important;
                max-width: 100% !important;
                height: auto !important;
            }

            /* bila mau sembunyikan tabs yang tidak aktif, biarkan bootstrap menangani; aktif tab akan tampil */
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12 d-flex align-items-center">
                <h3 class="mb-0">Produk Fast Moving</h3>
                <small class="text-muted ml-3">Berdasarkan frekuensi pemesanan (DO)</small>
                <div class="ml-auto no-print">
                    <!-- Back and Print (HANYA di header, satu tombol) -->
                    <a href="../index.php" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i> Back</a>
                    <button id="btnPrint" class="btn btn-sm btn-outline-primary"><i class="fas fa-print"></i> Print / PDF</button>
                </div>
            </div>
        </div>

        <!-- FILTER FORM: beri kelas no-print agar tidak tercetak -->
        <form class="form-inline mb-3 no-print" method="GET" action="">
            <input type="date" name="awal" class="form-control form-control-sm mr-2" value="<?= e($awal) ?>">
            <input type="date" name="akhir" class="form-control form-control-sm mr-2" value="<?= e($akhir) ?>">
            <select name="top" class="form-control form-control-sm mr-2">
                <?php for ($i = 5; $i <= 50; $i += 5): ?>
                    <option value="<?= $i ?>" <?= $i == $topN ? 'selected' : ''; ?>>Top <?= $i ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search"></i> Apply</button>
        </form>

        <div class="card">
            <div class="card-body">
                <div class="d-flex">
                    <h5 class="card-title mb-0">Hasil</h5>
                    <small class="text-muted ml-auto">Periode: <?= e($awal) ?> — <?= e($akhir) ?> · Top <?= e($topN) ?></small>
                </div>

                <?php if (count($cuts) === 0): ?>
                    <div class="alert alert-info mt-3">Tidak ada data DO pada rentang tanggal ini.</div>
                <?php else: ?>
                    <ul class="nav nav-tabs mt-3" id="cutTabs" role="tablist">
                        <?php foreach ($cuts as $c):
                            $active = ($c['idcut'] == $defaultActiveIdcut) ? 'active' : '';
                        ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $active ?>" id="tab-<?= $c['idcut'] ?>" data-toggle="tab" href="#cut-<?= $c['idcut'] ?>" role="tab"><?= e($c['nmcut']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="tab-content mt-3" id="cutTabsContent">
                        <?php foreach ($cuts as $c):
                            $paneActive = ($c['idcut'] == $defaultActiveIdcut) ? 'active show' : '';
                        ?>
                            <div class="tab-pane fade <?= $paneActive ?>" id="cut-<?= $c['idcut'] ?>" role="tabpanel">
                                <div class="card card-outline card-secondary">

                                    <div class="card-body">
                                        <?php
                                        $ch = $charts[$c['idcut']] ?? null;
                                        if (!$ch || count($ch['labels']) === 0) {
                                            echo '<div class="alert alert-light">Tidak ada item di kategori ini untuk periode & filter saat ini.</div>';
                                        } else {
                                            echo '<div class="chart-wrap position-relative mb-3"><canvas id="chart-' . $c['idcut'] . '" style="width:100%;height:320px;"></canvas></div>';
                                            echo '<div class="table-responsive">';
                                            echo '<table class="table table-sm table-bordered mb-0">';
                                            echo '<thead class="thead-light"><tr><th style="width:36px">#</th><th>Nama Item</th><th class="text-right">Jumlah Pemesanan</th><th class="text-right">Total Qty</th></tr></thead><tbody>';
                                            for ($k = 0; $k < count($ch['labels']); $k++) {
                                                $no = $k + 1;
                                                $name = e($ch['labels'][$k]);
                                                $f = (int)$ch['data'][$k];
                                                $q = number_format((float)$ch['qty'][$k], 2, ',', '.');
                                                echo "<tr><td class='text-center'>{$no}</td><td>{$name}</td><td class='text-right'>{$f}</td><td class='text-right'>{$q}</td></tr>";
                                            }
                                            echo '</tbody></table></div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Dependencies: jQuery, Popper, Bootstrap 4, Chart.js (CDN) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <script>
        document.title = "Produk Fast Moving";

        const chartsData = <?= json_encode($charts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?> || {};

        function genColors(n) {
            const out = [];
            for (let i = 0; i < n; i++) {
                const h = Math.floor((i * 47) % 360);
                out.push(`hsl(${h} 60% 55% / 0.95)`);
            }
            return out;
        }
        const chartInstances = {};

        function renderChartForIdcut(idcut) {
            const cfg = chartsData[idcut];
            if (!cfg) return;
            const canvasId = 'chart-' + idcut;
            const el = document.getElementById(canvasId);
            if (!el) return;
            const ctx = el.getContext('2d');

            if (chartInstances[canvasId]) {
                try {
                    chartInstances[canvasId].destroy();
                } catch (e) {}
                delete chartInstances[canvasId];
            }

            const labels = cfg.labels || [];
            const dataFreq = (cfg.data || []).map(v => Number(v) || 0);
            const dataQty = (cfg.qty || []).map(v => Number(v) || 0);
            const colors = genColors(labels.length);

            chartInstances[canvasId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Pemesanan',
                        data: dataFreq,
                        backgroundColor: colors,
                        borderColor: colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        y: {
                            ticks: {
                                autoSkip: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                boxWidth: 12
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    const idx = ctx.dataIndex;
                                    const f = dataFreq[idx] || 0;
                                    const q = dataQty[idx] || 0;
                                    return `${ctx.dataset.label}: ${f} pemesanan — Total Qty: ${q.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // on load: render active tab(s)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tab-pane').forEach(function(pane) {
                if (pane.classList.contains('active') || pane.classList.contains('show')) {
                    const id = pane.id.replace('cut-', '');
                    renderChartForIdcut(id);
                }
            });

            // handle tab shown -> render chart
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                const href = $(e.target).attr('href');
                if (!href) return;
                const idcut = href.replace('#cut-', '');
                renderChartForIdcut(idcut);
            });

            // print button (header)
            const btnPrint = document.getElementById('btnPrint');
            if (btnPrint) {
                btnPrint.addEventListener('click', function() {
                    // ensure visible canvas size correct
                    Object.values(chartInstances).forEach(c => {
                        try {
                            c.resize();
                        } catch (e) {}
                    });
                    setTimeout(() => window.print(), 150);
                });
            }
        });
    </script>
</body>

</html>