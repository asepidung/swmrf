<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id'])) {
   $id = $_GET['id'];
   $iditem = $_GET['iditem'];

   // Hapus data dari tabel stock berdasarkan id
   $deleteSql = "DELETE FROM stock WHERE id = ?";
   $deleteStmt = $conn->prepare($deleteSql);
   $deleteStmt->bind_param("i", $id);
   $deleteStmt->execute();
   $deleteStmt->close();

   // Kembalikan ke halaman index.php
   header("Location: detailitem.php?id=$iditem");
   exit();
} else {
   echo "Parameter id tidak valid.";
   exit();
}
