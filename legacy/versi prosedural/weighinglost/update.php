<?php
require "../verifications/auth.php";
require "../konak/conn.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$idloss    = isset($_POST['idloss'])    ? (int)$_POST['idloss']    : 0;
$idweigh   = isset($_POST['idweigh'])   ? (int)$_POST['idweigh']   : 0;
$idreceive = isset($_POST['idreceive']) ? (int)$_POST['idreceive'] : 0;
$loss_date = $_POST['loss_date'] ?? date('Y-m-d');
$note      = $_POST['note'] ?? '';
$iduser    = $_SESSION['idusers'] ?? 0;

if ($idloss <= 0 || $idweigh <= 0 || $idreceive <= 0) {
    die("Data tidak valid (idloss/idweigh/idreceive).");
}

// Ambil arrays detail
$idlossdetail    = $_POST['idlossdetail']    ?? [];
$idreceivedetail = $_POST['idreceivedetail'] ?? [];
$idweighdetail   = $_POST['idweighdetail']   ?? [];
$eartagArr       = $_POST['eartag']          ?? [];
$classArr        = $_POST['class']           ?? [];
$recvArr         = $_POST['receive_weight']  ?? [];
$actArr          = $_POST['actual_weight']   ?? [];
$lossArr         = $_POST['loss_weight']     ?? [];
$priceArr        = $_POST['price_perkg']     ?? [];

if (count($idlossdetail) === 0) {
    die("Tidak ada data detail yang dikirim.");
}

$rows = [];
$total_receive_weight = 0.0;
$total_actual_weight  = 0.0;
$total_loss_weight    = 0.0;
$total_loss_cost      = 0.0;

$count = count($idlossdetail);
for ($i = 0; $i < $count; $i++) {

    $idl  = (int)$idlossdetail[$i];
    $idr  = (int)$idreceivedetail[$i];
    $idw  = (int)$idweighdetail[$i];
    $ear  = trim($eartagArr[$i] ?? '');
    $cls  = trim($classArr[$i] ?? '');

    $recv = (float)($recvArr[$i] ?? 0);
    $act  = (float)($actArr[$i] ?? 0);

    // LOSS = receive - actual  (BISA NEGATIF)
    $loss = $recv - $act;

    // Price & Loss Cost
    $priceRaw = trim($priceArr[$i] ?? '');
    $price = ($priceRaw === '') ? null : (float)$priceRaw;

    $lossCost = null;
    if ($price !== null) {
        $lossCost = $loss * $price;   // tetap negatif jika loss negatif
        $total_loss_cost += $lossCost;
    }

    $total_receive_weight += $recv;
    $total_actual_weight  += $act;
    $total_loss_weight    += $loss;

    $rows[] = [
        'idlossdetail'    => $idl,
        'idreceivedetail' => $idr,
        'idweighdetail'   => $idw,
        'eartag'          => $ear,
        'class'           => $cls,
        'receive_weight'  => $recv,
        'actual_weight'   => $act,
        'loss_weight'     => $loss,
        'price_perkg'     => $price,
        'loss_cost'       => $lossCost
    ];
}

$conn->begin_transaction();
try {

    // UPDATE HEADER
    $sqlHeader = "
        UPDATE cattle_loss_receive
        SET loss_date = ?,
            note      = ?,
            total_receive_weight = ?,
            total_actual_weight  = ?,
            total_loss_weight    = ?,
            total_loss_cost      = ?
        WHERE idloss = ?
          AND idreceive = ?
          AND idweigh   = ?
          AND is_deleted = 0
    ";

    $stmtH = $conn->prepare($sqlHeader);
    $stmtH->bind_param(
        'ssddddiii',
        $loss_date,
        $note,
        $total_receive_weight,
        $total_actual_weight,
        $total_loss_weight,
        $total_loss_cost,
        $idloss,
        $idreceive,
        $idweigh
    );
    $stmtH->execute();
    $stmtH->close();

    // UPDATE DETAIL
    $sqlDet = "
        UPDATE cattle_loss_receive_detail
        SET receive_weight = ?,
            actual_weight  = ?,
            loss_weight    = ?,
            price_perkg    = ?,
            loss_cost      = ?
        WHERE idlossdetail = ?
          AND idloss       = ?
    ";

    $stmtD = $conn->prepare($sqlDet);

    foreach ($rows as $row) {
        $stmtD->bind_param(
            'dddddii',
            $row['receive_weight'],
            $row['actual_weight'],
            $row['loss_weight'],
            $row['price_perkg'],
            $row['loss_cost'],
            $row['idlossdetail'],
            $idloss
        );
        $stmtD->execute();
    }
    $stmtD->close();

    $conn->commit();

    header("Location: view.php?id=" . $idloss);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("Gagal mengupdate data loss: " . e($e->getMessage()));
}
