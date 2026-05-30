<?php
require "../verifications/auth.php";
require "../konak/conn.php";
$iddo = intval($_GET['iddo']);

// Pengecekan awal: Pastikan `iddo` ada dalam tabel `do`
$sqlCheckDO = "SELECT status, donumber FROM do WHERE iddo = ?";
$stmtCheckDO = $conn->prepare($sqlCheckDO);
$stmtCheckDO->bind_param("i", $iddo);
$stmtCheckDO->execute();
$stmtCheckDO->store_result();

// Jika tidak ditemukan DO dengan `iddo`
if ($stmtCheckDO->num_rows == 0) {
   die("Error: Delivery Order tidak ditemukan.");
}

// Ambil status dan donumber DO
$stmtCheckDO->bind_result($status, $donumber);
$stmtCheckDO->fetch();
$stmtCheckDO->close();

// Pengecekan jika status DO bukan "Unapproved"
if ($status !== "Unapproved") {
   echo "<script>alert('Gagal menghapus! Silahkan Unapproved terlebih dahulu!.'); window.location='do.php';</script>";
   exit();
}

// Mulai transaksi
$conn->begin_transaction();

try {
   // Update kolom stat di tabel tally menjadi 'Approved'
   $sqlUpdateTally = "UPDATE tally 
                       INNER JOIN do ON tally.idtally = do.idtally
                       SET tally.stat = 'Approved' 
                       WHERE do.iddo = ?";
   $stmtUpdateTally = $conn->prepare($sqlUpdateTally);
   $stmtUpdateTally->bind_param("i", $iddo);
   if (!$stmtUpdateTally->execute()) {
      throw new Exception("Error updating tally: " . $stmtUpdateTally->error);
   }

   // Soft delete data dari tabel do
   $sqlSoftDeleteDO = "UPDATE do SET is_deleted = 1 WHERE iddo = ?";
   $stmtSoftDeleteDO = $conn->prepare($sqlSoftDeleteDO);
   $stmtSoftDeleteDO->bind_param("i", $iddo);
   if (!$stmtSoftDeleteDO->execute()) {
      throw new Exception("Error soft deleting do: " . $stmtSoftDeleteDO->error);
   }

   // Log activity untuk Hapus DO
   $idusers = $_SESSION['idusers'];
   $event = "Delete DO";
   $query_log = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
   $stmt_log = $conn->prepare($query_log);
   $stmt_log->bind_param("iss", $idusers, $event, $donumber);
   $stmt_log->execute();
   $stmt_log->close();

   // Commit transaksi
   $conn->commit();
   echo "<script>alert('Delivery Order berhasil dihapus.'); window.location='do.php';</script>";
} catch (Exception $e) {
   // Rollback transaksi jika terjadi kesalahan
   $conn->rollback();
   echo "Terjadi kesalahan: " . $e->getMessage();
}

// Menutup prepared statements dan koneksi database
$stmtUpdateTally->close();
$stmtSoftDeleteDO->close();
$conn->close();
