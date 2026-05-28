<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id']) && isset($_GET['idso']) && is_numeric($_GET['id']) && is_numeric($_GET['idso'])) {
    $idtally = $_GET['id'];
    $idso = $_GET['idso'];

    // Mulai transaksi
    mysqli_begin_transaction($conn);

    try {
        // Ambil data dari tabel tally
        $query_select_tally = "SELECT notally FROM tally WHERE idtally = ?";
        $stmt_select_tally = $conn->prepare($query_select_tally);
        $stmt_select_tally->bind_param("i", $idtally);
        $stmt_select_tally->execute();
        $result_tally = $stmt_select_tally->get_result();
        if ($result_tally->num_rows === 0) {
            throw new Exception("Data Tally tidak ditemukan.");
        }
        $row_tally = $result_tally->fetch_assoc();
        $notally = $row_tally['notally'];
        $stmt_select_tally->close();

        // Ambil semua data dari tabel tallydetail untuk idtally terkait
        $query_tallydetail = "SELECT * FROM tallydetail WHERE idtally = ?";
        $stmt_tallydetail = $conn->prepare($query_tallydetail);
        $stmt_tallydetail->bind_param("i", $idtally);
        $stmt_tallydetail->execute();
        $result_tallydetail = $stmt_tallydetail->get_result();

        // Pindahkan data kembali ke tabel stock
        $query_insert_stock = "INSERT INTO stock (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert_stock = $conn->prepare($query_insert_stock);

        while ($row = $result_tallydetail->fetch_assoc()) {
            $stmt_insert_stock->bind_param(
                "siidisi",
                $row['barcode'],
                $row['idgrade'],
                $row['idbarang'],
                $row['weight'],
                $row['pcs'],
                $row['pod'],
                $row['origin']
            );
            $stmt_insert_stock->execute();
        }

        $stmt_insert_stock->close();
        $stmt_tallydetail->close();

        // Hapus data dari tabel tallydetail
        $query_delete_tallydetail = "DELETE FROM tallydetail WHERE idtally = ?";
        $stmt_delete_tallydetail = $conn->prepare($query_delete_tallydetail);
        $stmt_delete_tallydetail->bind_param("i", $idtally);
        $stmt_delete_tallydetail->execute();
        $stmt_delete_tallydetail->close();

        // Soft delete data dari tabel tally
        $query_soft_delete_tally = "UPDATE tally SET is_deleted = 1 WHERE idtally = ?";
        $stmt_soft_delete_tally = $conn->prepare($query_soft_delete_tally);
        $stmt_soft_delete_tally->bind_param("i", $idtally);
        $stmt_soft_delete_tally->execute();
        $stmt_soft_delete_tally->close();

        // Update status di tabel salesorder kembali ke Waiting
        $query_update_salesorder = "UPDATE salesorder SET progress = 'Waiting' WHERE idso = ?";
        $stmt_update_salesorder = $conn->prepare($query_update_salesorder);
        $stmt_update_salesorder->bind_param("i", $idso);
        $stmt_update_salesorder->execute();
        $stmt_update_salesorder->close();

        // Soft delete data monitoring_produksi terkait SO ini
        $query_delete_monitoring = "UPDATE monitoring_produksi SET is_deleted = 1 WHERE idso = ?";
        $stmt_delete_monitoring = $conn->prepare($query_delete_monitoring);
        $stmt_delete_monitoring->bind_param("i", $idso);
        $stmt_delete_monitoring->execute();
        $stmt_delete_monitoring->close();

        // Masukkan log ke tabel logactivity
        $iduser = $_SESSION['idusers'];
        $event = "Soft Delete Data Tally & Monitoring";
        $query_insert_log = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, CURRENT_TIMESTAMP())";
        $stmt_insert_log = $conn->prepare($query_insert_log);
        $stmt_insert_log->bind_param("iss", $iduser, $event, $notally);
        $stmt_insert_log->execute();
        $stmt_insert_log->close();

        // Commit semua perubahan jika berhasil
        mysqli_commit($conn);

        // Redirect ke halaman index dengan status sukses
        header("location: index.php?stat=soft_deleted");
        exit();
    } catch (Exception $e) {
        // Batalkan semua perubahan jika terjadi kesalahan
        mysqli_rollback($conn);

        // Tampilkan pesan error
        die("Terjadi kesalahan sistem: " . $e->getMessage());
    }
} else {
    // Redirect jika parameter URL tidak lengkap atau tidak valid
    header("location: index.php?stat=invalid_params");
    exit();
}
