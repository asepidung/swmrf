<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "ponumber.php";

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$idrequest = (int) $_GET['id'];

// ===================================================
// CEK: APAKAH REQUEST MASIH PUNYA PO AKTIF (stat = 0)
// ===================================================
$cekPo = $conn->prepare(
    "SELECT idpo 
     FROM pobeef 
     WHERE idrequest = ? 
       AND is_deleted = 0
       AND stat = 0
     LIMIT 1"
);
$cekPo->bind_param("i", $idrequest);
$cekPo->execute();
$cekPo->store_result();

if ($cekPo->num_rows > 0) {
    die("PO aktif untuk request ini sudah ada.");
}

// ==========================
// MULAI TRANSAKSI
// ==========================
$conn->begin_transaction();

try {

    // ==========================
    // AMBIL REQUEST (LOCK ROW)
    // ==========================
    $sql_request = "SELECT * FROM requestbeef WHERE idrequest = ? FOR UPDATE";
    $stmt_request = $conn->prepare($sql_request);
    $stmt_request->bind_param("i", $idrequest);
    $stmt_request->execute();
    $result_request = $stmt_request->get_result();
    $request = $result_request->fetch_assoc();

    if (!$request) {
        throw new Exception("Request not found.");
    }

    // ==========================
    // HITUNG NILAI
    // ==========================
    $xamount = (float) $request['xamount'];
    $tax     = (float) $request['tax'];
    $taxrp   = ($tax > 0) ? ($xamount * $tax / 100) : 0;

    // ==========================
    // INSERT PO
    // ==========================
    $sql_po = "
        INSERT INTO pobeef
        (nopo, idrequest, idsupplier, xamount, taxrp, tax, duedate, note, top)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt_po = $conn->prepare($sql_po);

    $stmt_po->bind_param(
        "siiddssss",
        $kodeauto,
        $idrequest,
        $request['idsupplier'],
        $xamount,
        $taxrp,
        $tax,
        $request['duedate'],
        $request['note'],
        $request['top']
    );

    $stmt_po->execute();
    $idpo = $conn->insert_id;

    // ==========================
    // AMBIL DETAIL REQUEST
    // ==========================
    $sql_requestdetail = "SELECT * FROM requestbeefdetail WHERE idrequest = ?";
    $stmt_requestdetail = $conn->prepare($sql_requestdetail);
    $stmt_requestdetail->bind_param("i", $idrequest);
    $stmt_requestdetail->execute();
    $result_requestdetail = $stmt_requestdetail->get_result();

    // ==========================
    // INSERT PO DETAIL
    // ==========================
    $sql_podetail = "
        INSERT INTO pobeefdetail
        (idpo, idbarang, qty, price, notes)
        VALUES (?, ?, ?, ?, ?)
    ";
    $stmt_podetail = $conn->prepare($sql_podetail);

    while ($detail = $result_requestdetail->fetch_assoc()) {
        $stmt_podetail->bind_param(
            "iiids",
            $idpo,
            $detail['idbarang'],
            $detail['qty'],
            $detail['price'],
            $detail['notes']
        );
        $stmt_podetail->execute();
    }

    // ==========================
    // UPDATE STATUS REQUEST
    // ==========================
    $sql_update_request = "
        UPDATE requestbeef
        SET stat = 'PO Created'
        WHERE idrequest = ?
    ";
    $stmt_update_request = $conn->prepare($sql_update_request);
    $stmt_update_request->bind_param("i", $idrequest);
    $stmt_update_request->execute();

    // ==========================
    // COMMIT
    // ==========================
    $conn->commit();

    header("Location: index.php");
    exit;
} catch (Exception $e) {

    $conn->rollback();
    echo "Error: " . $e->getMessage();
}

$conn->close();
