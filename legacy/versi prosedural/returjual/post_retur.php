<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$idreturjual = intval($_GET['idreturjual'] ?? 0);
if ($idreturjual <= 0) {
    die("ID retur tidak valid");
}

/* =====================
   CEK HEADER RETUR
   ===================== */
$qHeader = mysqli_query($conn, "
    SELECT status 
    FROM returjual 
    WHERE idreturjual = $idreturjual 
      AND is_deleted = 0
    LIMIT 1
");

$header = mysqli_fetch_assoc($qHeader);
if (!$header) {
    die("Data retur tidak ditemukan");
}

if ($header['status'] === 'POSTED') {
    die("Retur ini sudah diproses ke stock");
}

/* =====================
   AMBIL DETAIL RETUR
   ===================== */
$qDetail = mysqli_query($conn, "
    SELECT 
        kdbarcode,
        idbarang,
        idgrade,
        qty,
        pcs,
        pod,
        ph
    FROM returjualdetail
    WHERE idreturjual = $idreturjual
      AND is_deleted = 0
");

if (mysqli_num_rows($qDetail) === 0) {
    die("Tidak ada item retur untuk diproses");
}

/* =====================
   TRANSAKSI DB
   ===================== */
mysqli_begin_transaction($conn);

try {

    while ($d = mysqli_fetch_assoc($qDetail)) {

        $kdbarcode = mysqli_real_escape_string($conn, $d['kdbarcode']);

        // 🔑 ORIGIN = digit pertama barcode
        $origin = (int) substr($kdbarcode, 0, 1);

        $idbarang = (int)$d['idbarang'];
        $idgrade  = (int)$d['idgrade'];
        $qty      = (float)$d['qty'];
        $pcsSql   = is_null($d['pcs']) ? "NULL" : (int)$d['pcs'];
        $pod      = mysqli_real_escape_string($conn, $d['pod']);
        $phSql    = is_null($d['ph']) ? "NULL" : number_format($d['ph'], 1, '.', '');

        $sqlStock = "
            INSERT INTO stock
                (kdbarcode, idbarang, idgrade, qty, pcs, pod, origin, ph)
            VALUES
                ('$kdbarcode', $idbarang, $idgrade, $qty, $pcsSql, '$pod', $origin, $phSql)
        ";

        if (!mysqli_query($conn, $sqlStock)) {
            throw new Exception("Gagal insert ke stock: " . mysqli_error($conn));
        }
    }

    /* =====================
       UPDATE STATUS RETUR
       ===================== */
    if (!mysqli_query($conn, "
        UPDATE returjual 
        SET status = 'POSTED' 
        WHERE idreturjual = $idreturjual
    ")) {
        throw new Exception("Gagal update status retur");
    }

    mysqli_commit($conn);

    header("Location: index.php?stat=posted");
    exit;
} catch (Exception $e) {
    mysqli_rollback($conn);
    die("ERROR: " . $e->getMessage());
}
