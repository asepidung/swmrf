<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (
   $_SERVER['REQUEST_METHOD'] !== 'POST' ||
   empty($_POST['items']) ||
   empty($_POST['iddo'])
) {
   die("Data tidak lengkap");
}

$iddo = (int)$_POST['iddo'];
$idso = isset($_POST['idso']) ? (int)$_POST['idso'] : 0;
$items = $_POST['items']; // array idtallydetail

$conn->begin_transaction();

try {

   /* =========================
       PREPARE STATEMENT
    ========================= */

   // Ambil data tallydetail
   $stmtGet = $conn->prepare("
        SELECT barcode, idbarang, idgrade, weight, pcs, pod, origin
        FROM tallydetail
        WHERE idtallydetail = ?
    ");

   // Insert ke stock
   $stmtInsert = $conn->prepare("
        INSERT INTO stock
        (kdbarcode, idbarang, idgrade, qty, pcs, pod, origin)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

   // Delete dari tallydetail (berdasarkan idtallydetail)
   $stmtDelete = $conn->prepare("
        DELETE FROM tallydetail
        WHERE idtallydetail = ?
    ");

   /* =========================
       LOOP ITEM
    ========================= */
   foreach ($items as $idtallydetail) {

      $idtallydetail = (int)$idtallydetail;

      // Ambil data sumber
      $stmtGet->bind_param("i", $idtallydetail);
      $stmtGet->execute();
      $res = $stmtGet->get_result();
      $row = $res->fetch_assoc();

      if (!$row) {
         throw new Exception("Data tallydetail tidak ditemukan: ID $idtallydetail");
      }

      // Pastikan FK aman
      if (empty($row['idgrade']) || empty($row['idbarang'])) {
         throw new Exception("Data barang / grade tidak valid");
      }

      // Insert ke stock
      $stmtInsert->bind_param(
         "siidisi",
         $row['barcode'],
         $row['idbarang'],
         $row['idgrade'],
         $row['weight'],
         $row['pcs'],
         $row['pod'],
         $row['origin']
      );
      $stmtInsert->execute();

      // Hapus dari tallydetail
      $stmtDelete->bind_param("i", $idtallydetail);
      $stmtDelete->execute();
   }

   $conn->commit();

   header("Location: approvedo.php?iddo=$iddo");
   exit();
} catch (Exception $e) {

   $conn->rollback();
   echo "ERROR: " . $e->getMessage();
   exit();
}
