<?php
// finish.php – Close PO dari GR
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require "../verifications/auth.php";
require "../konak/conn.php";

$idgr = isset($_GET['idgr']) ? intval($_GET['idgr']) : 0;
$idpo = isset($_GET['idpo']) ? intval($_GET['idpo']) : 0;
$iduser = intval($_SESSION['idusers'] ?? 0);

if ($idgr <= 0 || $idpo <= 0 || $iduser <= 0) {
    die("Invalid parameter.");
}

// mulai transaksi
$conn->autocommit(false);

try {

    /* 1️⃣ VALIDASI PO */
    $stmt = $conn->prepare("
        SELECT p.stat, p.idrequest
        FROM po p
        WHERE p.idpo = ? AND p.is_deleted = 0
        FOR UPDATE
    ");
    if (!$stmt) throw new Exception($conn->error);

    $stmt->bind_param("i", $idpo);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        throw new Exception("PO tidak ditemukan.");
    }

    $po = $res->fetch_assoc();
    $po_stat = (int)$po['stat'];
    $idrequest = (int)$po['idrequest'];
    $stmt->close();

    // hanya boleh close jika PARTIAL (3)
    if ($po_stat !== 3) {
        throw new Exception("PO tidak dalam status PARTIAL atau sudah ditutup.");
    }

    /* 2️⃣ UPDATE PO → CLOSED */
    $stmt = $conn->prepare("UPDATE po SET stat = 1 WHERE idpo = ?");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param("i", $idpo);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    /* 3️⃣ UPDATE REQUEST → COMPLETED */
    if ($idrequest > 0) {
        $stmt = $conn->prepare("UPDATE request SET stat = 'Completed' WHERE idrequest = ?");
        if (!$stmt) throw new Exception($conn->error);
        $stmt->bind_param("i", $idrequest);
        if (!$stmt->execute()) throw new Exception($stmt->error);
        $stmt->close();
    }

    /* 4️⃣ LOG ACTIVITY */
    $event = "Close PO from GR";
    $doc = "PO ID: " . $idpo;

    $stmt = $conn->prepare("
        INSERT INTO logactivity (iduser, event, docnumb)
        VALUES (?, ?, ?)
    ");
    if (!$stmt) throw new Exception($conn->error);
    $stmt->bind_param("iss", $iduser, $event, $doc);
    if (!$stmt->execute()) throw new Exception($stmt->error);
    $stmt->close();

    /* COMMIT */
    $conn->commit();

    // kembali ke index GR
    header("Location: index.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
} finally {
    $conn->autocommit(true);
    $conn->close();
}
