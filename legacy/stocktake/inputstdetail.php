<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kdbarcode'], $_POST['idst'])) {
   $kdbarcode = trim($_POST['kdbarcode']);
   $idst      = (int)$_POST['idst'];

   if ($kdbarcode === '') {
      header("Location: starttaking.php?id={$idst}&stat=invalid");
      exit;
   }

   mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

   try {
      // --- Ambil data dari STOCK, termasuk pH ---
      $q = $conn->prepare(
         "SELECT idbarang, qty, pcs, pod, idgrade, origin, ph
             FROM stock
             WHERE kdbarcode = ?"
      );
      $q->bind_param("s", $kdbarcode);
      $q->execute();
      $res = $q->get_result();
      $q->close();

      if (!$res || $res->num_rows === 0) {
         // Tidak ada di stock → kirim balik supaya manual input
         $_SESSION['barcode'] = $kdbarcode;
         header("Location: starttaking.php?id={$idst}&stat=unknown");
         exit;
      }

      $row     = $res->fetch_assoc();
      $idgrade = $row['idgrade'];                // bisa NULL
      $idbarang = (int)$row['idbarang'];
      $qty     = (float)$row['qty'];
      $pcs     = is_null($row['pcs']) ? null : (int)$row['pcs'];
      $pod     = $row['pod'];                    // 'YYYY-MM-DD'
      $origin  = (int)$row['origin'];
      $ph      = is_null($row['ph']) ? null : (float)$row['ph']; // bisa NULL

      // --- Cek duplikat di stocktakedetail untuk idst yang sama ---
      $dup = $conn->prepare(
         "SELECT COUNT(*) FROM stocktakedetail WHERE kdbarcode = ? AND idst = ?"
      );
      $dup->bind_param("si", $kdbarcode, $idst);
      $dup->execute();
      $dup->bind_result($cnt);
      $dup->fetch();
      $dup->close();

      if ((int)$cnt > 0) {
         header("Location: starttaking.php?id={$idst}&stat=duplicate");
         exit;
      }

      // --- Insert ke stocktakedetail (termasuk kolom ph) ---
      // Urutan kolom: idst(i), kdbarcode(s), idgrade(i), idbarang(i), qty(d), pcs(i), pod(s), origin(i), ph(d)
      $ins = $conn->prepare(
         "INSERT INTO stocktakedetail
             (idst, kdbarcode, idgrade, idbarang, qty, pcs, pod, origin, ph)
             VALUES (?,?,?,?,?,?,?,?,?)"
      );

      // Tipe bind harus 9 huruf untuk 9 variabel:
      // i s i i d i s i d  => "isiidisid"
      $ins->bind_param(
         "isiidisid",
         $idst,            // i
         $kdbarcode,       // s
         $idgrade,         // i (nullable ok)
         $idbarang,        // i
         $qty,             // d
         $pcs,             // i (nullable ok)
         $pod,             // s
         $origin,          // i
         $ph               // d (nullable ok)
      );
      $ins->execute();
      $ins->close();

      header("Location: starttaking.php?id={$idst}&stat=success");
      exit;
   } catch (Throwable $e) {
      // log internal bila perlu
      // error_log($e->getMessage());
      header("Location: starttaking.php?id={$idst}&stat=error");
      exit;
   }
} else {
   // fallback invalid
   $redir = isset($_POST['idst']) ? (int)$_POST['idst'] : 0;
   header("Location: starttaking.php?id={$redir}&stat=invalid");
   exit;
}
