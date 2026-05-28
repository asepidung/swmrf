<?php
// inputgr.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../verifications/auth.php";
require "../konak/conn.php";
include "grnumber.php";      // menghasilkan $gr
include "idtransaksi.php";  // menghasilkan $idtransaksi

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

/* ================= AMBIL INPUT ================= */
$deliveryat   = trim($_POST['deliveryat'] ?? '');
$idsupplier   = intval($_POST['idsupplier'] ?? 0);
$note         = trim($_POST['note'] ?? '-');
$idpo         = intval($_POST['idpo'] ?? 0);
$idusers      = intval($_SESSION['idusers'] ?? 0);
$suppcode     = trim($_POST['suppcode'] ?? '');
$idrawmate    = $_POST['idrawmate'] ?? [];
$received_qty = $_POST['received_qty'] ?? [];

/* ================= VALIDASI DASAR ================= */
if ($deliveryat === '' || $idsupplier <= 0 || $idpo <= 0 || $idusers <= 0) {
    die("Error: Missing required header fields.");
}
if (!is_array($idrawmate) || !is_array($received_qty) || count($idrawmate) !== count($received_qty)) {
    die("Error: Invalid detail data.");
}
if (empty($gr) || empty($idtransaksi)) {
    die("Error: GR number / idtransaksi missing.");
}

/* ================= TRANSAKSI ================= */
$conn->autocommit(false);

try {

    /* 1️⃣ INSERT GR HEADER */
    $stmt = $conn->prepare("
        INSERT INTO grraw 
        (grnumber, receivedate, idsupplier, note, idusers, idpo, suppcode)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssisiis", $gr, $deliveryat, $idsupplier, $note, $idusers, $idpo, $suppcode);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $idgr = $conn->insert_id;
    $stmt->close();

    /* 2️⃣ AMBIL ORDER QTY PO */
    $stmt = $conn->prepare("SELECT idrawmate, qty FROM podetail WHERE idpo = ?");
    $stmt->bind_param("i", $idpo);
    $stmt->execute();
    $res = $stmt->get_result();
    $order_qty = [];
    while ($r = $res->fetch_assoc()) {
        $order_qty[(int)$r['idrawmate']] = (float)$r['qty'];
    }
    $stmt->close();

    /* 3️⃣ PREPARE DETAIL & STOCK */
    $stmtDetail = $conn->prepare("
        INSERT INTO grrawdetail 
        (idgr, idrawmate, qty, orderqty, idtransaksi)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmtStock = $conn->prepare("
        INSERT INTO stockraw 
        (idrawmate, qty, idtransaksi)
        VALUES (?, ?, ?)
    ");

    foreach ($idrawmate as $i => $rawid) {
        $idraw = intval($rawid);
        $qty   = str_replace(',', '.', trim($received_qty[$i]));
        if (!is_numeric($qty)) {
            throw new Exception("Invalid qty for rawmate {$idraw}");
        }
        $qty = (float)$qty;
        $oq  = $order_qty[$idraw] ?? 0;

        $stmtDetail->bind_param("iidis", $idgr, $idraw, $qty, $oq, $idtransaksi);
        if (!$stmtDetail->execute()) throw new Exception($stmtDetail->error);

        $stmtStock->bind_param("ids", $idraw, $qty, $idtransaksi);
        if (!$stmtStock->execute()) throw new Exception($stmtStock->error);
    }

    $stmtDetail->close();
    $stmtStock->close();

    /* 4️⃣ UPDATE PO → PARTIAL (stat = 2) */
    $stmt = $conn->prepare("UPDATE po SET stat = 3 WHERE idpo = ?");
    $stmt->bind_param("i", $idpo);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    /* 5️⃣ UPDATE REQUEST → PARTIAL */
    $stmt = $conn->prepare("SELECT idrequest FROM po WHERE idpo = ?");
    $stmt->bind_param("i", $idpo);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        throw new Exception("Request not found for PO");
    }
    $idrequest = (int)$res->fetch_assoc()['idrequest'];
    $stmt->close();

    $stmt = $conn->prepare("UPDATE request SET stat = 'Partial' WHERE idrequest = ?");
    $stmt->bind_param("i", $idrequest);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    /* 6️⃣ LOG */
    $event = "Buat GR RAW (PARTIAL)";
    $stmt = $conn->prepare("
        INSERT INTO logactivity (iduser, event, docnumb)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $idusers, $event, $gr);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    /* COMMIT */
    $conn->commit();
    header("Location: index.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . htmlspecialchars($e->getMessage());
} finally {
    $conn->autocommit(true);
    $conn->close();
}
