<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   // Mendapatkan data dari form
   $iddo = isset($_POST['iddo']) ? intval($_POST['iddo']) : 0;
   $deliverydate = $_POST['deliverydate'];
   $idcustomer = isset($_POST['idcustomer']) ? intval($_POST['idcustomer']) : 0;
   $po = $_POST['po'];
   $donumber = $_POST['donumber'];
   $note = $_POST['note'];
   $xbox = $_POST['xbox'];
   $xweight = $_POST['xweight'];
   $idusers = $_SESSION['idusers'];
   $status = "Approved";
   $idso = $_POST['idso'];

   // **Pengecekan Status di Tabel DO**
   $stmtCheckDO = $conn->prepare("SELECT status, is_deleted FROM do WHERE iddo = ?");
   if (!$stmtCheckDO) {
      die("Error: Prepare failed (" . $conn->errno . ") " . $conn->error);
   }
   $stmtCheckDO->bind_param("i", $iddo);
   $stmtCheckDO->execute();
   $resultCheckDO = $stmtCheckDO->get_result();
   $rowCheckDO = $resultCheckDO->fetch_assoc();

   // **Jika DO sudah Approved dan is_deleted = 0, hentikan eksekusi**
   if ($rowCheckDO['status'] === "Approved" && $rowCheckDO['is_deleted'] == 0) {
      header("location: do.php?message=DO Sudah di Approved");
      exit();
   }

   // **Jika DO sudah Invoiced dan is_deleted = 0, hentikan eksekusi**
   if ($rowCheckDO['status'] === "Invoiced" && $rowCheckDO['is_deleted'] == 0) {
      header("location: do.php?message=Invoiced Sudah Terbuat");
      exit();
   }

   // **Jika tidak dalam kondisi di atas, lanjutkan proses**
   $conn->autocommit(false); // Mulai transaksi

   try {
      // Insert data ke tabel doreceipt
      $queryInsertDoreceipt = "INSERT INTO doreceipt (iddo, idso, donumber, deliverydate, idcustomer, po, note, xbox, xweight, idusers, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
      $stmtInsertDoreceipt = $conn->prepare($queryInsertDoreceipt);
      if (!$stmtInsertDoreceipt) {
         throw new Exception("Prepare failed: " . $conn->error);
      }
      $stmtInsertDoreceipt->bind_param("iisssssddss", $iddo, $idso, $donumber, $deliverydate, $idcustomer, $po, $note, $xbox, $xweight, $idusers, $status);
      $stmtInsertDoreceipt->execute();
      $iddoreceipt = $conn->insert_id;

      // Insert data ke tabel doreceiptdetail
      if (isset($_POST['idbarang']) && isset($_POST['box']) && isset($_POST['weight'])) {
         $idbarang = $_POST['idbarang'];
         $boxes = $_POST['box'];
         $weights = $_POST['weight'];
         $notes = $_POST['notes'];

         $queryInsertDoreceiptDetail = "INSERT INTO doreceiptdetail (iddoreceipt, idbarang, box, weight, notes) VALUES (?, ?, ?, ?, ?)";
         $stmtInsertDoreceiptDetail = $conn->prepare($queryInsertDoreceiptDetail);

         for ($i = 0; $i < count($idbarang); $i++) {
            $box = intval($boxes[$i]);
            $weight = floatval($weights[$i]);
            $note = $notes[$i];

            $stmtInsertDoreceiptDetail->bind_param("iidss", $iddoreceipt, $idbarang[$i], $box, $weight, $note);
            $stmtInsertDoreceiptDetail->execute();
         }
      }

      // Update status pada tabel do
      $queryUpdateDo = "UPDATE do SET status = 'Approved', rweight = ? WHERE iddo = ?";
      $stmtUpdateDo = $conn->prepare($queryUpdateDo);
      $stmtUpdateDo->bind_param("di", $xweight, $iddo);
      $stmtUpdateDo->execute();

      // Update status pada tabel salesorder
      $queryUpdateSo = "UPDATE salesorder SET progress = 'Delivered' WHERE idso = ?";
      $stmtUpdateSo = $conn->prepare($queryUpdateSo);
      $stmtUpdateSo->bind_param("i", $idso);
      $stmtUpdateSo->execute();

      // Insert ke tabel logactivity
      $event = "Approve DO";
      $waktu = date('Y-m-d H:i:s');
      $queryLogActivity = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, ?)";
      $stmtLogActivity = $conn->prepare($queryLogActivity);
      $stmtLogActivity->bind_param("isss", $idusers, $event, $donumber, $waktu);
      $stmtLogActivity->execute();

      // Commit transaksi jika semua query berhasil
      $conn->commit();

      header("Location: do.php?message=success");
      exit();
   } catch (Exception $e) {
      // Rollback jika terjadi kesalahan
      $conn->rollback();
      echo "Terjadi kesalahan: " . $e->getMessage();
      exit();
   } finally {
      $conn->autocommit(true);
   }
}
