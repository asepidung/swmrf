<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
   die("Parameter ID tidak valid.");
}

$idst = (int)$_GET['id'];

$conn->begin_transaction();

try {
   // 1) Ambil nomor ST
   $stmt_nost = $conn->prepare("SELECT nost FROM stocktake WHERE idst = ?");
   $stmt_nost->bind_param("i", $idst);
   $stmt_nost->execute();
   $res_nost = $stmt_nost->get_result();
   if ($res_nost->num_rows === 0) throw new Exception("Stock Take tidak ditemukan.");
   $nost = (string)$res_nost->fetch_assoc()['nost'];
   $stmt_nost->close();

   // 2) Cek missing
   $stmt_miss = $conn->prepare("
      SELECT COUNT(*) AS total_missing
      FROM stock
      WHERE kdbarcode NOT IN (SELECT kdbarcode FROM stocktakedetail WHERE idst=?)
   ");
   $stmt_miss->bind_param("i", $idst);
   $stmt_miss->execute();
   $total_missing = (int)$stmt_miss->get_result()->fetch_assoc()['total_missing'];
   $stmt_miss->close();

   // 3) Simpan yang missing (opsional)
   if ($total_missing > 0) {
      $stmt_ins_missing = $conn->prepare("
         INSERT INTO missing_stock (idst, kdbarcode, idgrade, idbarang, qty, pcs, pod, origin)
         SELECT ?, kdbarcode, idgrade, idbarang, qty, pcs, pod, origin
         FROM stock
         WHERE kdbarcode NOT IN (SELECT kdbarcode FROM stocktakedetail WHERE idst=?)
      ");
      $stmt_ins_missing->bind_param("ii", $idst, $idst);
      if (!$stmt_ins_missing->execute()) throw new Exception("Gagal simpan missing.");
      $stmt_ins_missing->close();
   }

   // 4) Kosongkan stock
   if (!$conn->prepare("DELETE FROM stock")->execute()) {
      throw new Exception("Gagal mengosongkan tabel stock.");
   }

   // 5) Ambil dari stocktakedetail (TERMASUK ph)
   $stmt_src = $conn->prepare("
      SELECT kdbarcode, idgrade, idbarang, qty, pcs, pod, origin, ph
      FROM stocktakedetail
      WHERE idst = ?
   ");
   $stmt_src->bind_param("i", $idst);
   $stmt_src->execute();
   $res_src = $stmt_src->get_result();
   if ($res_src->num_rows === 0) throw new Exception("Tidak ada data untuk dipindahkan ke stock.");

   // 6) Insert ke stock (dengan ph)
   $stmt_dst = $conn->prepare("
      INSERT INTO stock (kdbarcode, idgrade, idbarang, qty, pcs, pod, origin, ph)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
   ");
   // Tipe: s i i d i s i d  → "siidisid"
   while ($r = $res_src->fetch_assoc()) {
      $kdbarcode = (string)$r['kdbarcode'];
      $idgrade   = isset($r['idgrade']) ? (int)$r['idgrade'] : null;
      $idbarang  = (int)$r['idbarang'];
      $qty       = isset($r['qty']) ? (float)$r['qty'] : null;
      $pcs       = isset($r['pcs']) ? (int)$r['pcs'] : null;
      $pod       = (string)$r['pod'];           // yyyy-mm-dd
      $origin    = isset($r['origin']) ? (int)$r['origin'] : null;
      $ph        = isset($r['ph']) ? (float)$r['ph'] : null;

      $stmt_dst->bind_param("siidisid", $kdbarcode, $idgrade, $idbarang, $qty, $pcs, $pod, $origin, $ph);
      if (!$stmt_dst->execute()) {
         throw new Exception("Gagal insert stock (barcode: {$kdbarcode}).");
      }
   }
   $stmt_src->close();
   $stmt_dst->close();

   // 7) Log
   $event  = "Stock Take Confirm";
   $iduser = (int)$_SESSION['idusers'];
   $stmt_log = $conn->prepare("INSERT INTO logactivity (iduser, event, docnumb, waktu) VALUES (?, ?, ?, NOW())");
   $stmt_log->bind_param("iss", $iduser, $event, $nost);
   if (!$stmt_log->execute()) throw new Exception("Gagal log aktivitas.");
   $stmt_log->close();

   // 8) Tandai stocked
   $stmt_upd = $conn->prepare("UPDATE stocktake SET stocked = 1 WHERE idst = ?");
   $stmt_upd->bind_param("i", $idst);
   if (!$stmt_upd->execute()) throw new Exception("Gagal update status stocked.");
   $stmt_upd->close();

   // 9) Commit & selesai
   $conn->commit();
   $conn->close();
   header("Location: index.php");
   exit;
} catch (Exception $e) {
   $conn->rollback();
   $conn->close();
   die("Terjadi kesalahan: " . $e->getMessage());
}
