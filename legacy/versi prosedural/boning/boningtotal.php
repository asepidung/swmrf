<?php
$query_total_weight = "SELECT SUM(qty) AS total_weight FROM labelboning WHERE idboning = $idboning";
$result_total_weight = mysqli_query($conn, $query_total_weight);
$row_total_weight = mysqli_fetch_assoc($result_total_weight);
$total_weight = $row_total_weight['total_weight'];

$query_total_box = "SELECT COUNT(idbarang) AS total_box FROM labelboning WHERE idboning = $idboning";
$result_total_box = mysqli_query($conn, $query_total_box);
$row_total_box = mysqli_fetch_assoc($result_total_box);
$total_box = $row_total_box['total_box'];

$query_total_pcs = "SELECT SUM(pcs) AS total_pcs FROM labelboning WHERE idboning = $idboning";
$result_total_pcs = mysqli_query($conn, $query_total_pcs);
$row_total_pcs = mysqli_fetch_assoc($result_total_pcs);
$total_pcs = $row_total_pcs['total_pcs'];
