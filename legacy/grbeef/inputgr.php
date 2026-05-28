<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "grnumber.php";

if (isset($_POST['submit'])) {
    $deliveryat = $_POST['duedate']; // Mengambil nilai dari kolom due date
    $idsupplier = $_POST['idsupplier'];
    $note = isset($_POST['note']) && trim($_POST['note']) !== '' ? trim($_POST['note']) : '-';
    $idpo = $_POST['idpo']; // Pastikan nama kolom ini benar di tabel grbeef
    $idusers = $_SESSION['idusers'];
    $suppcode = $_POST['suppcode'];

    // Mulai transaksi
    $conn->autocommit(false);

    try {
        // Query INSERT untuk tabel grbeef
        $query_gr = "INSERT INTO grbeef (grnumber, receivedate, idsupplier, note, idusers, idpo, suppcode) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_gr = $conn->prepare($query_gr);

        if ($stmt_gr === false) {
            throw new Exception("Error preparing insert statement: " . $conn->error);
        }

        $stmt_gr->bind_param("ssisiis", $gr, $deliveryat, $idsupplier, $note, $idusers, $idpo, $suppcode);

        if (!$stmt_gr->execute()) {
            throw new Exception("Error executing insert statement: " . $stmt_gr->error);
        }

        // Update tabel pobeef
        $query_update_pobeef = "UPDATE pobeef SET stat = 1 WHERE idpo = ?";
        $stmt_update_pobeef = $conn->prepare($query_update_pobeef);

        if ($stmt_update_pobeef === false) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }

        $stmt_update_pobeef->bind_param("i", $idpo);

        if (!$stmt_update_pobeef->execute()) {
            throw new Exception("Error executing update statement: " . $stmt_update_pobeef->error);
        }

        // Ambil idrequest dari tabel pobeef
        $query_select_idrequest = "SELECT idrequest FROM pobeef WHERE idpo = ?";
        $stmt_select_idrequest = $conn->prepare($query_select_idrequest);

        if ($stmt_select_idrequest === false) {
            throw new Exception("Error preparing select statement: " . $conn->error);
        }

        $stmt_select_idrequest->bind_param("i", $idpo);

        if (!$stmt_select_idrequest->execute()) {
            throw new Exception("Error executing select statement: " . $stmt_select_idrequest->error);
        }

        $result = $stmt_select_idrequest->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("No idrequest found for the given idpo");
        }

        $row = $result->fetch_assoc();
        $idrequest = $row['idrequest'];

        // Update tabel requestbeef
        $query_update_requestbeef = "UPDATE requestbeef SET stat = 'Confirmed' WHERE idrequest = ?";
        $stmt_update_requestbeef = $conn->prepare($query_update_requestbeef);

        if ($stmt_update_requestbeef === false) {
            throw new Exception("Error preparing update statement for requestbeef: " . $conn->error);
        }

        $stmt_update_requestbeef->bind_param("i", $idrequest);

        if (!$stmt_update_requestbeef->execute()) {
            throw new Exception("Error executing update statement for requestbeef: " . $stmt_update_requestbeef->error);
        }

        // Insert log activity ke tabel logactivity
        $event = "Buat GR Beef";
        $queryLogActivity = "INSERT INTO logactivity (iduser, event, docnumb) VALUES (?, ?, ?)";
        $stmtLogActivity = $conn->prepare($queryLogActivity);

        if (!$stmtLogActivity) {
            throw new Exception("Error preparing log activity statement: " . $conn->error);
        }

        $stmtLogActivity->bind_param('iss', $idusers, $event, $gr);

        if (!$stmtLogActivity->execute()) {
            throw new Exception("Error executing log activity statement: " . $stmtLogActivity->error);
        }

        // Commit transaksi jika semua query berhasil dieksekusi
        $conn->commit();

        // Redirect ke halaman index jika berhasil
        header("location: index.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi kesalahan
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    } finally {
        // Set autocommit kembali ke true setelah selesai
        $conn->autocommit(true);
        $stmt_gr->close();
        if (isset($stmt_update_pobeef)) $stmt_update_pobeef->close();
        if (isset($stmt_select_idrequest)) $stmt_select_idrequest->close();
        if (isset($stmt_update_requestbeef)) $stmt_update_requestbeef->close();
        if (isset($stmtLogActivity)) $stmtLogActivity->close();
        $conn->close();
    }
}
