<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_POST['submit'])) {
    $idgr = isset($_POST['idgr']) ? intval($_POST['idgr']) : 0;
    $deliveryat = $_POST['deliveryat'];
    $idsupplier = $_POST['idsupplier'];
    $note = isset($_POST['note']) && trim($_POST['note']) !== '' ? trim($_POST['note']) : '-';
    $idpo = $_POST['idpo'];
    $idtransaksi = $_POST['idtransaksi']; // Array idtransaksi
    $received_qty = $_POST['received_qty'];   // Array qty diterima
    $idusers = $_SESSION['idusers'];

    if ($idgr <= 0) {
        echo "<script>alert('Invalid GR ID!'); window.location='index.php';</script>";
        exit();
    }

    // Mulai transaksi
    $conn->autocommit(false);

    try {
        // Update tabel grraw
        $query_update_gr = "UPDATE grraw SET receivedate = ?, note = ?, idsupplier = ?, idpo = ? WHERE idgr = ?";
        $stmt_update_gr = $conn->prepare($query_update_gr);

        if (!$stmt_update_gr) {
            throw new Exception("Error preparing grraw update query: " . $conn->error);
        }

        $stmt_update_gr->bind_param("ssiii", $deliveryat, $note, $idsupplier, $idpo, $idgr);

        if (!$stmt_update_gr->execute()) {
            throw new Exception("Error executing grraw update query: " . $stmt_update_gr->error);
        }

        // Update tabel grrawdetail menggunakan idtransaksi
        $query_update_grdetail = "UPDATE grrawdetail SET qty = ? WHERE idtransaksi = ?";
        $stmt_update_grdetail = $conn->prepare($query_update_grdetail);

        if (!$stmt_update_grdetail) {
            throw new Exception("Error preparing grrawdetail update query: " . $conn->error);
        }

        // Update tabel stockraw dengan menggunakan idtransaksi
        $query_update_stockraw = "UPDATE stockraw SET qty = ? WHERE idtransaksi = ?";
        $stmt_update_stockraw = $conn->prepare($query_update_stockraw);

        if (!$stmt_update_stockraw) {
            throw new Exception("Error preparing stockraw update query: " . $conn->error);
        }

        foreach ($idtransaksi as $index => $transaksi_id) {
            $qty_received = $received_qty[$index];

            // Update grrawdetail berdasarkan idtransaksi
            $stmt_update_grdetail->bind_param("is", $qty_received, $transaksi_id);
            if (!$stmt_update_grdetail->execute()) {
                throw new Exception("Error updating grrawdetail: " . $stmt_update_grdetail->error);
            }

            // Update stockraw menggunakan idtransaksi
            $stmt_update_stockraw->bind_param("is", $qty_received, $transaksi_id);
            if (!$stmt_update_stockraw->execute()) {
                throw new Exception("Error updating stockraw: " . $stmt_update_stockraw->error);
            }
        }

        // Insert log activity
        $event = "Edit GR RAW";
        $queryLogActivity = "INSERT INTO logactivity (iduser, event, docnumb) VALUES (?, ?, ?)";
        $stmtLogActivity = $conn->prepare($queryLogActivity);
        if (!$stmtLogActivity) {
            throw new Exception("Error preparing log activity statement: " . $conn->error);
        }

        $stmtLogActivity->bind_param('iss', $idusers, $event, $idgr);

        if (!$stmtLogActivity->execute()) {
            throw new Exception("Error executing log activity statement: " . $stmtLogActivity->error);
        }

        // Commit transaksi jika semua berhasil
        $conn->commit();

        echo "<script>alert('Data berhasil diperbarui.'); window.location='index.php';</script>";
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi kesalahan
        $conn->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='index.php';</script>";
    } finally {
        $conn->autocommit(true);

        if (isset($stmt_update_gr)) {
            $stmt_update_gr->close();
        }
        if (isset($stmt_update_grdetail)) {
            $stmt_update_grdetail->close();
        }
        if (isset($stmt_update_stockraw)) {
            $stmt_update_stockraw->close();
        }
        if (isset($stmtLogActivity)) {
            $stmtLogActivity->close();
        }
        $conn->close();
    }
} else {
    echo "<script>alert('Invalid request!'); window.location='index.php';</script>";
}
