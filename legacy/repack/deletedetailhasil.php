<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!isset($_GET['id'], $_GET['iddetail'])) {
   // Parameter tidak lengkap → kembali ke halaman sebelumnya kalau ada
   $fallback = isset($_GET['id']) ? "detailhasil.php?id=" . (int)$_GET['id'] : "index.php";
   header("Location: {$fallback}");
   exit;
}

$id       = (int)$_GET['id'];          // idrepack
$iddetail = (int)$_GET['iddetail'];    // iddetailhasil
$iduser   = (int)($_SESSION['idusers'] ?? 0);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
   $conn->begin_transaction();

   // 1) Ambil kdbarcode dari detailhasil (pastikan barisnya ada & belum soft delete)
   $sqlGet = "SELECT kdbarcode, is_deleted FROM detailhasil WHERE iddetailhasil = ? LIMIT 1 FOR UPDATE";
   $stmtGet = $conn->prepare($sqlGet);
   $stmtGet->bind_param('i', $iddetail);
   $stmtGet->execute();
   $resGet = $stmtGet->get_result();
   if ($resGet->num_rows === 0) {
      // Tidak ada baris detail → batalkan
      $conn->rollback();
      echo "<script>alert('Data detail tidak ditemukan.'); window.location='detailhasil.php?id={$id}';</script>";
      exit;
   }
   $rowDet    = $resGet->fetch_assoc();
   $kdbarcode = (string)$rowDet['kdbarcode'];
   $isDeleted = (int)$rowDet['is_deleted'];

   // Kalau sudah dihapus, langsung kembali (idempotent)
   if ($isDeleted === 1) {
      $conn->rollback();
      echo "<script>alert('Data sudah dihapus sebelumnya.'); window.location='detailhasil.php?id={$id}&stat=deleted';</script>";
      exit;
   }

   // 2) Cek apakah barang masih ada di STOCK (kuncikan baris supaya konsisten)
   $sqlChk = "SELECT COUNT(*) AS cnt FROM stock WHERE kdbarcode = ? FOR UPDATE";
   $stmtChk = $conn->prepare($sqlChk);
   $stmtChk->bind_param('s', $kdbarcode);
   $stmtChk->execute();
   $cnt = (int)$stmtChk->get_result()->fetch_assoc()['cnt'];

   if ($cnt === 0) {
      // Barang sudah pindah/dipakai proses lain → batalkan penghapusan
      $conn->rollback();
      echo "<script>alert('Barang sudah digunakan oleh proses lain.'); window.location='detailhasil.php?id={$id}';</script>";
      exit;
   }

   // 3) Soft delete detailhasil
   $sqlSoft = "UPDATE detailhasil SET is_deleted = 1 WHERE iddetailhasil = ? LIMIT 1";
   $stmtSoft = $conn->prepare($sqlSoft);
   $stmtSoft->bind_param('i', $iddetail);
   $stmtSoft->execute();
   if ($stmtSoft->affected_rows < 1) {
      // Tidak ada baris yang berubah (harusnya ada) → batalkan
      $conn->rollback();
      echo "<script>alert('Gagal menghapus detail (soft delete).'); window.location='detailhasil.php?id={$id}';</script>";
      exit;
   }

   // 4) Hard delete di stock
   $sqlHard = "DELETE FROM stock WHERE kdbarcode = ? LIMIT 1";
   $stmtHard = $conn->prepare($sqlHard);
   $stmtHard->bind_param('s', $kdbarcode);
   $stmtHard->execute();
   if ($stmtHard->affected_rows < 1) {
      // Baris stock tidak terhapus (harusnya ada karena barusan dicek) → batalkan
      $conn->rollback();
      echo "<script>alert('Gagal menghapus dari stock.'); window.location='detailhasil.php?id={$id}';</script>";
      exit;
   }

   // 5) Catat log
   if ($iduser > 0) {
      $event    = "Hapus Hasil Repack";
      $logQuery = "INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())";
      $stmtLog  = $conn->prepare($logQuery);
      $stmtLog->bind_param('iss', $iduser, $event, $kdbarcode);
      $stmtLog->execute();
   }

   // 6) Commit dan kembali
   $conn->commit();
   header("Location: detailhasil.php?id={$id}&stat=deleted");
   exit;
} catch (Throwable $e) {
   if ($conn->errno) {
      // Jika koneksi masih terbuka dan dalam transaksi, rollback
      try {
         $conn->rollback();
      } catch (Throwable $ignored) {
      }
   }
   // Tampilkan pesan aman
   echo "<script>alert('Terjadi kesalahan ketika menghapus data.'); window.location='detailhasil.php?id={$id}';</script>";
   // Opsional: log error server-side
   error_log('Delete detailhasil error: ' . $e->getMessage());
   exit;
}
