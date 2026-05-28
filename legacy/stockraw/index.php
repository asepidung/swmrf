<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<style>
    /* =========================
   PRINT STYLE
   ========================= */
    @media print {
        body * {
            visibility: hidden;
        }

        .content-wrapper,
        .content-wrapper * {
            visibility: visible;
        }

        .content-wrapper {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        .btn-print {
            display: none;
        }
    }
</style>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12 mt-2">
                    <div class="card">
                        <div class="card-header d-flex justify-content-end">
                            <button onclick="window.print()" class="btn btn-sm btn-primary btn-print">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>

                        <div class="card-body">
                            <div class="col">
                                <table id="example1" class="table table-bordered table-striped table-sm">
                                    <thead class="text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>Kode</th>
                                            <th>Item Description</th>
                                            <th>Unit</th>
                                            <th>BSM</th>
                                            <th>Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $sql = "
                                            SELECT 
                                                rc.idrawcategory,
                                                rc.nmcategory,
                                                rm.kdrawmate AS kode,
                                                rm.nmrawmate AS description,
                                                rm.unit,
                                                rm.barmin,
                                                (
                                                    COALESCE(msk.qty_in, 0) 
                                                    - COALESCE(klr.qty_out, 0)
                                                ) AS stock
                                            FROM rawmate rm
                                            JOIN rawcategory rc 
                                                ON rc.idrawcategory = rm.idrawcategory

                                            LEFT JOIN (
                                                SELECT 
                                                    gd.idrawmate, 
                                                    SUM(gd.qty) AS qty_in
                                                FROM grrawdetail gd
                                                JOIN grraw g 
                                                    ON g.idgr = gd.idgr
                                                WHERE gd.is_deleted = 0
                                                  AND g.is_deleted = 0
                                                GROUP BY gd.idrawmate
                                            ) msk 
                                                ON msk.idrawmate = rm.idrawmate

                                            LEFT JOIN (
                                                SELECT 
                                                    d.idrawmate,
                                                    SUM(d.qty) AS qty_out
                                                FROM raw_stock_out_detail d
                                                JOIN raw_stock_out h 
                                                    ON h.idstockout = d.idstockout
                                                WHERE h.is_deleted = 0
                                                GROUP BY d.idrawmate
                                            ) klr 
                                                ON klr.idrawmate = rm.idrawmate

                                            WHERE rm.stock = 1
                                            ORDER BY rc.nmcategory ASC, rm.nmrawmate ASC
                                        ";

                                        $result = $conn->query($sql);

                                        if (!$result) {
                                            echo "<tr>
                                                    <td colspan='6' class='text-center text-danger'>
                                                        Query Error: " . htmlspecialchars($conn->error) . "
                                                    </td>
                                                  </tr>";
                                        } elseif ($result->num_rows > 0) {

                                            $no = 1;
                                            $lastCategory = null;

                                            while ($row = $result->fetch_assoc()) {

                                                if ($lastCategory !== $row['idrawcategory']) {
                                                    echo "
                                                        <tr style='background:#6c757d;color:#fff;font-weight:bold'>
                                                            <td colspan='6'>
                                                                " . htmlspecialchars($row['nmcategory']) . "
                                                            </td>
                                                        </tr>
                                                    ";
                                                    $lastCategory = $row['idrawcategory'];
                                                    $no = 1;
                                                }

                                                $stockVal = (float)$row['stock'];
                                                $barmin   = (int)$row['barmin'];

                                                if ($stockVal < 0) {
                                                    $stockStyle = "style='color:#6f42c1;font-weight:bold'";
                                                } elseif ($stockVal <= $barmin && $barmin > 0) {
                                                    $stockStyle = "style='color:#dc3545;font-weight:bold'";
                                                } else {
                                                    $stockStyle = "";
                                                }

                                                echo "
                                                    <tr>
                                                        <td class='text-center'>{$no}</td>
                                                        <td class='text-center'>" . htmlspecialchars($row['kode']) . "</td>
                                                        <td>" . htmlspecialchars($row['description']) . "</td>
                                                        <td class='text-center'>" . htmlspecialchars((string)($row['unit'] ?? '-')) . "</td>
                                                        <td class='text-center'>" . ($barmin > 0 ? $barmin : '-') . "</td>
                                                        <td class='text-right' {$stockStyle}>
                                                            " . number_format($stockVal, 2) . "
                                                        </td>
                                                    </tr>
                                                ";

                                                $no++;
                                            }
                                        } else {
                                            echo "<tr>
                                                    <td colspan='6' class='text-center'>No data available</td>
                                                  </tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
    document.title = "DATA STOCK RAW";
</script>

<?php include "../footer.php"; ?>