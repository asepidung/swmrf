<?php
require "../verifications/auth.php";
require "../konak/conn.php";

$idso = $_GET['idso'];

/* 1. Ambil data idcustomer dulu dari tabel salesorder */
$query_ambil_so = mysqli_query($conn, "SELECT idcustomer FROM salesorder WHERE idso = '$idso'");
$data_so = mysqli_fetch_assoc($query_ambil_so);
$idcustomer = $data_so['idcustomer'];

/* 2. Insert ke tabel header monitoring_produksi (sekarang bawa idcustomer) */
$query_header = "INSERT INTO monitoring_produksi (idso, idcustomer, status_qc) 
                 VALUES ('$idso', '$idcustomer', 'Pending')";

if (mysqli_query($conn, $query_header)) {
    $id_monitoring = mysqli_insert_id($conn);

    /* 3. Ambil data item dari salesorderdetail */
    $query_items = "SELECT idbarang, weight, notes FROM salesorderdetail WHERE idso = '$idso'";
    $result_items = mysqli_query($conn, $query_items);

    /* 4. Masukkan item ke tabel detail monitoring */
    while ($row = mysqli_fetch_assoc($result_items)) {
        $idbarang = $row['idbarang'];
        $weight   = $row['weight'];
        $notes    = mysqli_real_escape_string($conn, $row['notes']);

        $query_detail = "INSERT INTO monitoring_produksidetail (idmonitoring, idbarang, weight, notes) 
                         VALUES ('$id_monitoring', '$idbarang', '$weight', '$notes')";
        mysqli_query($conn, $query_detail);
    }
}

header("location: newtally.php?idso=$idso");
exit();
