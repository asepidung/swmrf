<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "skulabel.php"; // Menghasilkan $kodeauto untuk kdbarcode

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idgr = intval($_POST['idgr']);
    $idbarang = intval($_POST['idbarang']);
    $idgrade = intval($_POST['idgrade']);
    $idusers = intval($_SESSION['idusers']);
    $packdate = $_POST['packdate']; // Ambil packdate dari form
    $origin = $_POST['origin'];
    $idgrFormatted = str_pad($idgr, 3, '0', STR_PAD_LEFT);
    $kdbarcode = $origin . $idgrFormatted . $kodeauto;

    // Simpan nilai ke session untuk digunakan kembali di form
    $_SESSION['idbarang'] = $idbarang;
    $_SESSION['idgrade'] = $idgrade;
    $_SESSION['packdate'] = $packdate;
    $_SESSION['origin'] = $origin;

    // Memproses input qty dan pcs
    $qtyPcsInput = $_POST['qty'];
    $qty = null;
    $pcs = null;

    if (strpos($qtyPcsInput, "/") !== false) {
        // Jika ada format "qty/pcs", pisahkan
        list($qty, $pcs) = explode("/", $qtyPcsInput);
        $qty = floatval($qty);
        $pcs = trim($pcs); // Pcs mungkin berupa string, sehingga perlu dipastikan
    } else {
        // Jika hanya ada qty
        $qty = floatval($qtyPcsInput);
        $pcs = null;
    }

    // Mulai transaksi
    $conn->autocommit(false);

    try {
        // Query insert ke tabel grbeefdetail
        $query_insert_detail = "INSERT INTO grbeefdetail (idgr, idbarang, idgrade, qty, pcs, kdbarcode, pod) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_detail = $conn->prepare($query_insert_detail);

        if (!$stmt_insert_detail) {
            throw new Exception("Error preparing insert statement for grbeefdetail: " . $conn->error);
        }

        // Bind parameter dan eksekusi
        $stmt_insert_detail->bind_param("iiidiss", $idgr, $idbarang, $idgrade, $qty, $pcs, $kdbarcode, $packdate);

        if (!$stmt_insert_detail->execute()) {
            throw new Exception("Error executing insert statement for grbeefdetail: " . $stmt_insert_detail->error);
        }

        // Ambil idgrbeefdetail yang baru saja di-insert
        $idgrbeefdetail = $conn->insert_id;

        // Query insert ke tabel stock
        $query_insert_stock = "INSERT INTO stock (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_stock = $conn->prepare($query_insert_stock);

        if (!$stmt_insert_stock) {
            throw new Exception("Error preparing insert statement for stock: " . $conn->error);
        }

        // Bind parameter untuk stock
        $stmt_insert_stock->bind_param("siidisi", $kdbarcode, $idgrade, $idbarang, $qty, $pcs, $packdate, $origin);

        if (!$stmt_insert_stock->execute()) {
            throw new Exception("Error executing insert statement for stock: " . $stmt_insert_stock->error);
        }

        // Commit transaksi jika berhasil
        $conn->commit();
        header("location: printlabel.php?idgr=$idgr&idgrbeefdetail=$idgrbeefdetail");
        exit();
    } catch (Exception $e) {
        // Rollback jika terjadi kesalahan
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    } finally {
        $conn->autocommit(true);
        if (isset($stmt_insert_detail)) {
            $stmt_insert_detail->close();
        }
        if (isset($stmt_insert_stock)) {
            $stmt_insert_stock->close();
        }
        $conn->close();
    }
}
