<?php
require "../konak/conn.php";

// DataTables params
$draw   = isset($_POST["draw"])   ? (int)$_POST["draw"]   : 1;
$limit  = isset($_POST["length"]) ? (int)$_POST["length"] : 10;
$start  = isset($_POST["start"])  ? (int)$_POST["start"]  : 0;
$search = isset($_POST["search"]["value"]) ? trim($_POST["search"]["value"]) : "";

// id stock take
$idst = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($idst <= 0) {
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

// total tanpa filter
$sqlCount = "SELECT COUNT(*) FROM stocktakedetail WHERE idst = ?";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param("i", $idst);
$stmtCount->execute();
$total_records = (int)($stmtCount->get_result()->fetch_row()[0] ?? 0);
$stmtCount->close();

// query utama (ikutkan pH)
$sql = "
  SELECT
    s.idstdetail,
    s.kdbarcode,
    b.nmbarang,
    g.nmgrade,
    s.qty,
    s.pcs,
    s.ph,
    s.pod,
    s.origin
  FROM stocktakedetail s
  INNER JOIN barang b ON s.idbarang = b.idbarang
  LEFT  JOIN grade  g ON s.idgrade  = g.idgrade
  WHERE s.idst = ?
";
$types = "i";
$params = [$idst];

if ($search !== "") {
    $sql .= " AND (s.kdbarcode LIKE ? OR b.nmbarang LIKE ? OR g.nmgrade LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= "sss";
}

$sql .= " ORDER BY s.idstdetail DESC LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;
$types   .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// build rows
$data = [];
while ($r = $res->fetch_assoc()) {
    $qty = is_null($r['qty']) ? '' : number_format((float)$r['qty'], 2);
    $pcs = is_null($r['pcs']) ? '' : (string)(int)$r['pcs'];
    $ph  = is_null($r['ph'])  ? '' : number_format((float)$r['ph'], 1);
    $pod = ($r['pod'] && $r['pod'] !== '0000-00-00') ? date('d-M-y', strtotime($r['pod'])) : '';

    $originMap = [
        0 => "Unidentified",
        1 => "BONING",
        2 => "TRADING",
        3 => "REPACK",
        4 => "RELABEL",
        5 => "IMPORT",
        6 => "OTHER",
        7 => "STOCKIN",
    ];
    $originText = $originMap[(int)$r['origin']] ?? "Unknown";

    // tombol hapus (kolom terakhir)
    $deleteHtml = '<a href="deletestdetail.php?iddetail=' . (int)$r['idstdetail'] .
        '&id=' . (int)$idst .
        '" class="text-danger" onclick="return confirm(\'Yakinkan Dirimu?\')">' .
        '<i class="far fa-times-circle"></i></a>';

    $data[] = [
        (int)$r['idstdetail'],                  // 0 -> untuk nomor urut di client
        htmlspecialchars($r['kdbarcode']),      // 1
        htmlspecialchars($r['nmbarang']),       // 2
        htmlspecialchars($r['nmgrade'] ?? ''),  // 3
        $qty,                                   // 4
        $pcs,                                   // 5
        $ph,                                    // 6 (pH)
        htmlspecialchars($pod),                 // 7 (POD)
        htmlspecialchars($originText),          // 8 (Origin)
        $deleteHtml                             // 9 (Hapus)
    ];
}
$stmt->close();

// filtered count
$sqlFiltered = "
  SELECT COUNT(*)
  FROM stocktakedetail s
  INNER JOIN barang b ON s.idbarang = b.idbarang
  LEFT  JOIN grade  g ON s.idgrade  = g.idgrade
  WHERE s.idst = ?
";
$typesF = "i";
$paramsF = [$idst];
if ($search !== "") {
    $sqlFiltered .= " AND (s.kdbarcode LIKE ? OR b.nmbarang LIKE ? OR g.nmgrade LIKE ?)";
    $paramsF[] = $like;
    $paramsF[] = $like;
    $paramsF[] = $like;
    $typesF   .= "sss";
}
$stmtF = $conn->prepare($sqlFiltered);
$stmtF->bind_param($typesF, ...$paramsF);
$stmtF->execute();
$records_filtered = (int)($stmtF->get_result()->fetch_row()[0] ?? 0);
$stmtF->close();

// output
echo json_encode([
    "draw" => $draw,
    "recordsTotal"    => $total_records,
    "recordsFiltered" => $records_filtered,
    "data"            => $data
]);
