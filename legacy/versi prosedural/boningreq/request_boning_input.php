<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // 1. Tangkap dan bersihkan data Header
    $idboning       = (int)$_POST['idboning'];
    $idusers        = (int)$_POST['idusers'];
    $spesifications = mysqli_real_escape_string($conn, $_POST['spesifications'] ?? '');

    // Validasi basic idboning
    if ($idboning == 0) {
        echo "<script>alert('Data gagal disimpan: ID Boning tidak valid!'); window.history.back();</script>";
        exit;
    }

    // Mulai Transaksi Database (Biar aman, kalau error satu batal semua)
    mysqli_begin_transaction($conn);

    try {
        // 2. Insert ke Tabel Header (production_requests)
        $queryHeader = "INSERT INTO production_requests (idboning, idusers, spesifications) 
                        VALUES ($idboning, $idusers, '$spesifications')";

        if (!mysqli_query($conn, $queryHeader)) {
            throw new Exception("Error Insert Header: " . mysqli_error($conn));
        }

        // Ambil ID Request yang baru aja tercipta dari query header di atas
        $idrequest = mysqli_insert_id($conn);

        // 3. Tangkap data Array untuk Detail (production_request_items)
        $idbarang_arr = $_POST['idbarang'] ?? [];
        $qty_arr      = $_POST['qty'] ?? [];
        $satuan_arr   = $_POST['satuan'] ?? [];
        $notes_arr    = $_POST['notes'] ?? [];

        // 4. Looping untuk insert setiap item ke Tabel Detail
        for ($i = 0; $i < count($idbarang_arr); $i++) {
            $idbarang = (int)$idbarang_arr[$i];

            // Skip baris jika idbarang kosong (jaga-jaga kalau ada elemen select kosong yg lolos)
            if ($idbarang == 0) {
                continue;
            }

            $qty    = (float)$qty_arr[$i];
            $satuan = mysqli_real_escape_string($conn, $satuan_arr[$i]);
            $notes  = mysqli_real_escape_string($conn, $notes_arr[$i] ?? '');

            $queryDetail = "INSERT INTO production_request_items (idrequest, idbarang, qty, satuan, notes) 
                            VALUES ($idrequest, $idbarang, $qty, '$satuan', '$notes')";

            if (!mysqli_query($conn, $queryDetail)) {
                throw new Exception("Error Insert Detail Baris ke-" . ($i + 1) . ": " . mysqli_error($conn));
            }
        }

        // Jika semua lancar, eksekusi permanen ke database
        mysqli_commit($conn);

        // Balikin ke halaman index Boning
        echo "<script>alert('Request Marketing Berhasil Disimpan!'); window.location='../boning/databoning.php';</script>";
    } catch (Exception $e) {
        // Jika ada error di tengah jalan, batalkan semua insert
        mysqli_rollback($conn);
        $errorMessage = $e->getMessage();
        echo "<script>alert('Gagal menyimpan data: $errorMessage'); window.history.back();</script>";
    }
} else {
    // Kalau ada yang coba akses langsung tanpa lewat form POST
    echo "<script>window.location='../boning/databoning.php';</script>";
}
