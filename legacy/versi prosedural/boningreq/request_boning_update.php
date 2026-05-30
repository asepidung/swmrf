<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    // 1. Tangkap data Header
    $idrequest      = (int)$_POST['idrequest'];
    $idboning       = (int)$_POST['idboning'];
    $spesifications = mysqli_real_escape_string($conn, $_POST['spesifications'] ?? '');

    // Validasi basic
    if ($idrequest == 0 || $idboning == 0) {
        echo "<script>alert('Data gagal diupdate: ID tidak valid!'); window.history.back();</script>";
        exit;
    }

    // Mulai Transaksi Database
    mysqli_begin_transaction($conn);

    try {
        // 2. Update Tabel Header (production_requests)
        $queryUpdateHeader = "UPDATE production_requests 
                              SET spesifications = '$spesifications' 
                              WHERE idrequest = $idrequest";

        if (!mysqli_query($conn, $queryUpdateHeader)) {
            throw new Exception("Error Update Header: " . mysqli_error($conn));
        }

        // 3. Hapus semua detail item lama dari database (production_request_items)
        // Ini cara paling bersih untuk handle item dinamis (ada yang dihapus/ditambah dari UI)
        $queryDeleteItems = "DELETE FROM production_request_items WHERE idrequest = $idrequest";
        if (!mysqli_query($conn, $queryDeleteItems)) {
            throw new Exception("Error Menghapus Detail Lama: " . mysqli_error($conn));
        }

        // 4. Tangkap data Array untuk Detail baru yang disubmit
        $idbarang_arr = $_POST['idbarang'] ?? [];
        $qty_arr      = $_POST['qty'] ?? [];
        $satuan_arr   = $_POST['satuan'] ?? [];
        $notes_arr    = $_POST['notes'] ?? [];

        // 5. Looping untuk insert ulang setiap item ke Tabel Detail
        for ($i = 0; $i < count($idbarang_arr); $i++) {
            $idbarang = (int)$idbarang_arr[$i];

            // Skip baris jika idbarang kosong
            if ($idbarang == 0) {
                continue;
            }

            $qty    = (float)$qty_arr[$i];
            $satuan = mysqli_real_escape_string($conn, $satuan_arr[$i]);
            $notes  = mysqli_real_escape_string($conn, $notes_arr[$i] ?? '');

            $queryInsertDetail = "INSERT INTO production_request_items (idrequest, idbarang, qty, satuan, notes) 
                                  VALUES ($idrequest, $idbarang, $qty, '$satuan', '$notes')";

            if (!mysqli_query($conn, $queryInsertDetail)) {
                throw new Exception("Error Insert Detail Baru Baris ke-" . ($i + 1) . ": " . mysqli_error($conn));
            }
        }

        // Eksekusi permanen ke database jika semua step di atas berhasil
        mysqli_commit($conn);

        // Redirect ke halaman yang direquest
        echo "<script>alert('Request Marketing Berhasil Diupdate!'); window.location='../boning/databoning.php';</script>";
    } catch (Exception $e) {
        // Batalkan semua perubahan jika terjadi error
        mysqli_rollback($conn);
        $errorMessage = $e->getMessage();
        echo "<script>alert('Gagal mengupdate data: $errorMessage'); window.history.back();</script>";
    }
} else {
    // Kembalikan user jika akses langsung tanpa lewat metode POST
    echo "<script>window.location='../boning/databoning.php';</script>";
}
