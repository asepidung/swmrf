<?php
require "konak/conn.php";

$today = date('Y-m-d');

// Query untuk menghitung jumlah DO pada hari ini
$sql = "SELECT COUNT(*) AS total_delivery_today FROM do WHERE deliverydate = '$today'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$deliverytoday = $row['total_delivery_today'];

// Query untuk menghitung jumlah DO dengan status "Unapproved"
$sql1 = "SELECT COUNT(*) AS una FROM do WHERE status = 'Unapproved'";
$result1 = mysqli_query($conn, $sql1);
$row1 = mysqli_fetch_assoc($result1);
$unapproved = $row1['una'];

$sql2 = "SELECT COUNT(*) AS x FROM do";
$result2 = mysqli_query($conn, $sql2);
$row2 = mysqli_fetch_assoc($result2);
$x = $row2['x'];

$sql3 = "SELECT COUNT(*) AS kedatangan FROM pobeef WHERE stat = 0 AND duedate >= '$today'";
$result3 = mysqli_query($conn, $sql3);
$row3 = mysqli_fetch_assoc($result3);
$kedatangan = $row3['kedatangan'];
