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

$idweigh   = isset($_POST['idweigh'])   ? (int)$_POST['idweigh']   : 0;
$idreceive = isset($_POST['idreceive']) ? (int)$_POST['idreceive'] : 0;
$loss_date = $_POST['loss_date'] ?? date('Y-m-d');
$note      = $_POST['note'] ?? '';
$iduser    = $_SESSION['idusers'] ?? 0;

if ($idweigh <= 0 || $idreceive <= 0) {
    die("Data tidak valid (idweigh/idreceive).");
}

$idreceivedetail = $_POST['idreceivedetail'] ?? [];
$idweighdetail   = $_POST['idweighdetail']   ?? [];
$eartagArr       = $_POST['eartag']          ?? [];
$classArr        = $_POST['class']           ?? [];
$recvArr         = $_POST['receive_weight']  ?? [];
$actArr          = $_POST['actual_weight']   ?? [];
$priceArr        = $_POST['price_perkg']     ?? [];

if (count($idreceivedetail) === 0) {
    die("Tidak ada data detail yang dikirim.");
}

$rows = [];
$total_receive_weight = 0.0;
$total_actual_weight  = 0.0;
$total_loss_weight    = 0.0;
$total_loss_cost      = 0.0;

$count = count($idreceivedetail);
for ($i = 0; $i < $count; $i++) {

    $idr  = (int)$idreceivedetail[$i];
    $idw  = (int)$idweighdetail[$i];
    $ear  = trim($eartagArr[$i] ?? '');
    $cls  = trim($classArr[$i] ?? '');
    $recv = (float)($recvArr[$i] ?? 0);
    $act  = (float)($actArr[$i] ?? 0);

    // *** PERBAIKAN RUMUS LOSS ***
    $loss = $act - $recv;

    $priceRaw = trim($priceArr[$i] ?? '');
    $price = ($priceRaw === '') ? null : (float)$priceRaw;

    $lossCost = null;
    if ($price !== null) {
        $lossCost = $loss * $price;
        $total_loss_cost += $lossCost;
    }

    $total_receive_weight += $recv;
    $total_actual_weight  += $act;
    $total_loss_weight    += $loss;

    $rows[] = [
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

$loss_no = 'LRC-' . date('ymdHis');

$conn->begin_transaction();
try {
    $sqlHeader = "
        INSERT INTO cattle_loss_receive
            (idreceive, idweigh, loss_no, loss_date, note,
             total_receive_weight, total_actual_weight, total_loss_weight, total_loss_cost,
             createby)
        VALUES
            (?,?,?,?,?,?,?,?,?,?)
    ";
    $stmtH = $conn->prepare($sqlHeader);
    $stmtH->bind_param(
        'iisssddddi',
        $idreceive,
        $idweigh,
        $loss_no,
        $loss_date,
        $note,
        $total_receive_weight,
        $total_actual_weight,
        $total_loss_weight,
        $total_loss_cost,
        $iduser
    );
    $stmtH->execute();
    $idloss = $stmtH->insert_id;
    $stmtH->close();

    $sqlDet = "
        INSERT INTO cattle_loss_receive_detail
            (idloss, idreceivedetail, idweighdetail, eartag, cattle_class,
             receive_weight, actual_weight, loss_weight, price_perkg, loss_cost,
             notes, createby)
        VALUES
            (?,?,?,?,?,?,?,?,?,?,?,?)
    ";
    $stmtD = $conn->prepare($sqlDet);

    foreach ($rows as $row) {
        $priceParam = $row['price_perkg'];
        $lcostParam = $row['loss_cost'];
        $notes = '';

        $stmtD->bind_param(
            'iiissdddddsi',
            $idloss,
            $row['idreceivedetail'],
            $row['idweighdetail'],
            $row['eartag'],
            $row['class'],
            $row['receive_weight'],
            $row['actual_weight'],
            $row['loss_weight'],
            $priceParam,
            $lcostParam,
            $notes,
            $iduser
        );
        $stmtD->execute();
    }
    $stmtD->close();

    $conn->commit();

    header("Location: index.php");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die("Gagal menyimpan data loss: " . e($e->getMessage()));
}
