<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "ponumber.php";

if (isset($_GET['id'])) {
    $idrequest = (int) $_GET['id'];

    // Mulai transaksi
    $conn->begin_transaction();

    try {
        // Ambil data dari tabel request
        $sql_request = "SELECT * FROM request WHERE idrequest = ?";
        $stmt_request = $conn->prepare($sql_request);
        $stmt_request->bind_param("i", $idrequest);
        $stmt_request->execute();
        $result_request = $stmt_request->get_result();
        $request = $result_request->fetch_assoc();

        if (!$request) {
            throw new Exception("Request not found.");
        }

        // Hitung nilai pajak
        $xamount = floatval($request['xamount']); // Pastikan xamount adalah angka
        $tax = floatval($request['tax']); // Pastikan tax adalah angka

        // Debug untuk memastikan nilai numerik
        var_dump($xamount, $tax); // Debugging, hapus jika tidak diperlukan

        $taxrp = ($tax > 0) ? ($xamount * $tax / 100) : 0; // Jumlah pajak
        $totalWithTax = $xamount + $taxrp; // Total setelah pajak

        // Insert ke tabel po
        $sql_po = "INSERT INTO po (nopo, idrequest, idsupplier, xamount, taxrp, tax, duedate, note, top) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_po = $conn->prepare($sql_po);
        $nopo = $kodeauto; // Nomor PO dihasilkan otomatis
        $idsupplier = $request['idsupplier'];
        $duedate = $request['duedate'];
        $note = $request['note'];
        $top = $request['top']; // Ambil nilai TOP dari tabel request

        $stmt_po->bind_param("siiddssss", $nopo, $idrequest, $idsupplier, $xamount, $taxrp, $tax, $duedate, $note, $top);
        if (!$stmt_po->execute()) {
            throw new Exception("Error inserting into PO: " . $stmt_po->error);
        }

        // Ambil idpo yang baru dibuat
        $idpo = $conn->insert_id;

        // Ambil data dari tabel requestdetail
        $sql_requestdetail = "SELECT * FROM requestdetail WHERE idrequest = ?";
        $stmt_requestdetail = $conn->prepare($sql_requestdetail);
        $stmt_requestdetail->bind_param("i", $idrequest);
        $stmt_requestdetail->execute();
        $result_requestdetail = $stmt_requestdetail->get_result();

        // Insert data ke tabel podetail
        $sql_podetail = "INSERT INTO podetail (idpo, idrawmate, qty, price, notes) 
                         VALUES (?, ?, ?, ?, ?)";
        $stmt_podetail = $conn->prepare($sql_podetail);

        while ($detail = $result_requestdetail->fetch_assoc()) {
            $idrawmate = $detail['idrawmate'];
            $qty = $detail['qty'];
            $price = $detail['price'];
            $notes = $detail['notes'];

            // Debug untuk memastikan data detail benar
            var_dump($idrawmate, $qty, $price, $notes);

            $stmt_podetail->bind_param("iiids", $idpo, $idrawmate, $qty, $price, $notes);
            if (!$stmt_podetail->execute()) {
                throw new Exception("Error inserting into PODetail: " . $stmt_podetail->error);
            }
        }

        // Update status di tabel request
        $sql_update_request = "UPDATE request SET stat = 'PO Created' WHERE idrequest = ?";
        $stmt_update_request = $conn->prepare($sql_update_request);
        $stmt_update_request->bind_param("i", $idrequest);
        if (!$stmt_update_request->execute()) {
            throw new Exception("Error updating Request status: " . $stmt_update_request->error);
        }

        // Commit transaksi
        $conn->commit();

        header("Location: index.php");
    } catch (Exception $e) {
        // Rollback transaksi jika ada error
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid request.";
}

$conn->close();
