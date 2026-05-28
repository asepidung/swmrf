<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Validasi parameter
if (!isset($_GET['iddo']) || !isset($_GET['idso'])) {
   die("<script>alert('Parameter tidak valid'); window.location='do.php';</script>");
}

$iddo = intval($_GET['iddo']);
$idso = intval($_GET['idso']);

// Debugging: Log parameter yang diterima
error_log("[REJECT DO] Memulai proses - iddo: $iddo, idso: $idso");

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
   // 1. Dapatkan data tally terkait
   $tally_query = "SELECT t.idtally, t.stat, d.donumber 
                   FROM tally t
                   JOIN do d ON t.idso = d.idso
                   WHERE t.idso = ? AND d.iddo = ?
                   LIMIT 1";
   $stmt = $conn->prepare($tally_query);
   $stmt->bind_param("ii", $idso, $iddo);
   $stmt->execute();
   $tally_result = $stmt->get_result();

   if ($tally_result->num_rows === 0) {
      throw new Exception("Data Tally tidak ditemukan untuk SO dan DO ini");
   }

   $tally_data = $tally_result->fetch_assoc();
   $idtally = $tally_data['idtally'];
   $tally_stat = $tally_data['stat'];
   $donumber = $tally_data['donumber'];
   $stmt->close();

   // Debugging: Log data yang ditemukan
   error_log("[REJECT DO] Data ditemukan - idtally: $idtally, status: $tally_stat, DO: $donumber");

   // 2. Update status di semua tabel terkait
   $update_queries = [
      "UPDATE do SET status = 'Rejected' WHERE iddo = ?",
      "UPDATE tally SET stat = 'Rejected' WHERE idtally = ?",
      "UPDATE salesorder SET progress = 'Rejected' WHERE idso = ?"
   ];

   foreach ($update_queries as $query) {
      $stmt = $conn->prepare($query);
      if (strpos($query, 'do') !== false) {
         $stmt->bind_param("i", $iddo);
      } elseif (strpos($query, 'tally') !== false) {
         $stmt->bind_param("i", $idtally);
      } else {
         $stmt->bind_param("i", $idso);
      }

      if (!$stmt->execute()) {
         throw new Exception("Gagal update status: " . $stmt->error);
      }
      $stmt->close();
   }

   // 3. Proses pengembalian stock dengan penanganan ambiguity
   // 3a. Cek keberadaan item di tallydetail
   $count_query = "SELECT COUNT(*) AS item_count FROM tallydetail WHERE idtally = ?";
   $stmt = $conn->prepare($count_query);
   $stmt->bind_param("i", $idtally);
   $stmt->execute();
   $count_result = $stmt->get_result()->fetch_assoc();
   $item_count = $count_result['item_count'];
   $stmt->close();

   error_log("[REJECT DO] Jumlah item di tallydetail: $item_count");

   $affected_rows = 0;
   if ($item_count > 0) {
      // 3b. Query pengembalian stock dengan alias tabel yang jelas
      $insert_query = "INSERT INTO stock 
                        (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin)
                        SELECT 
                            td.barcode AS kdbarcode,
                            td.idgrade AS idgrade,
                            td.idbarang AS idbarang,
                            td.weight AS qty,
                            td.pcs AS pcs,
                            td.pod AS pod,
                            td.origin AS origin
                        FROM tallydetail td
                        WHERE td.idtally = ?
                        ON DUPLICATE KEY UPDATE 
                            qty = stock.qty + VALUES(qty),
                            pcs = stock.pcs + VALUES(pcs)";

      $stmt = $conn->prepare($insert_query);
      $stmt->bind_param("i", $idtally);

      if (!$stmt->execute()) {
         throw new Exception("Gagal memproses pengembalian stock: " . $stmt->error);
      }

      $affected_rows = $stmt->affected_rows;
      $stmt->close();

      // 3c. Verifikasi data yang dipindahkan
      $verify_query = "SELECT COUNT(*) AS verified 
                        FROM tallydetail td
                        INNER JOIN stock s ON td.barcode = s.kdbarcode
                        WHERE td.idtally = ?";
      $stmt = $conn->prepare($verify_query);
      $stmt->bind_param("i", $idtally);
      $stmt->execute();
      $verify_result = $stmt->get_result()->fetch_assoc();
      $verified_count = $verify_result['verified'];
      $stmt->close();

      error_log("[REJECT DO] Barang berhasil diverifikasi: $verified_count items");
   }

   // 4. Catat log activity
   $log_query = "INSERT INTO logactivity (iduser, event, docnumb, waktu) 
                 VALUES (?, 'Reject DO', ?, NOW())";
   $stmt = $conn->prepare($log_query);
   $stmt->bind_param("is", $_SESSION['idusers'], $donumber);
   $stmt->execute();
   $stmt->close();

   // Commit transaksi
   mysqli_commit($conn);

   error_log("[REJECT DO] Proses berhasil - DO $donumber ditolak, $affected_rows item dikembalikan");

   echo "<script>
            alert('DO $donumber berhasil ditolak. $affected_rows barang dikembalikan ke stock.');
            window.location='do.php';
         </script>";
} catch (Exception $e) {
   // Rollback transaksi jika ada error
   mysqli_rollback($conn);

   // Log error
   error_log("[REJECT DO ERROR] " . $e->getMessage());
   error_log("[REJECT DO DEBUG] Trace: " . $e->getTraceAsString());

   echo "<script>
            alert('Gagal menolak DO: " . addslashes($e->getMessage()) . "');
            window.location='do.php';
         </script>";
} finally {
   $conn->close();
}
