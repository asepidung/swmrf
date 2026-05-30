<?php
require "../verifications/auth.php";
require "../konak/conn.php";

/* =========================
   Ambil input
========================= */
$tipebarang = $_POST['tipebarang'] ?? '';
$nmbarang   = strtoupper(trim($_POST['nmbarang'] ?? ''));
$cut        = $_POST['cut'] ?? '';
$kodeinduk  = isset($_POST['kodeinduk']) && $_POST['kodeinduk'] !== ''
   ? (int)$_POST['kodeinduk']
   : null;

/* =========================
   Validasi dasar
========================= */
if (!$tipebarang || !$nmbarang || !$cut) {
   echo "<script>alert('Mohon lengkapi data wajib diisi.'); window.history.back();</script>";
   exit;
}

/* =========================
   CEK DUPLIKAT NAMA
========================= */
$cek = $conn->prepare("SELECT 1 FROM barang WHERE nmbarang = ?");
$cek->bind_param("s", $nmbarang);
$cek->execute();
$cek->store_result();
if ($cek->num_rows > 0) {
   echo "<script>alert('Nama barang sudah ada dalam database.'); window.history.back();</script>";
   $cek->close();
   $conn->close();
   exit;
}
$cek->close();

/* =========================
   BARANG UTAMA
========================= */
if ($tipebarang === 'utama') {

   // Ambil digit kategori (idcut)
   $stmt = $conn->prepare("SELECT idcut FROM cuts WHERE idcut = ?");
   $stmt->bind_param("i", $cut);
   $stmt->execute();
   $stmt->bind_result($kategoriDigit);
   $stmt->fetch();
   $stmt->close();

   // Cari urutan terbesar di kategori ini
   $stmt = $conn->prepare("
      SELECT kdbarang 
      FROM barang 
      WHERE idcut = ? AND kodeinduk IS NULL
   ");
   $stmt->bind_param("i", $cut);
   $stmt->execute();
   $result = $stmt->get_result();

   $maxUrut = 0;
   while ($row = $result->fetch_assoc()) {
      $kode = str_pad($row['kdbarang'], 6, "0", STR_PAD_LEFT);
      $urut = (int)substr($kode, 1, 3);
      if ($urut > $maxUrut) {
         $maxUrut = $urut;
      }
   }
   $stmt->close();

   // Nomor urut baru
   $nomorUrut  = $maxUrut + 1;
   $kdbarang_db = ($kategoriDigit * 100000) + ($nomorUrut * 100);

   // Insert barang utama
   $stmt = $conn->prepare("
      INSERT INTO barang (kdbarang, nmbarang, idcut, kodeinduk)
      VALUES (?, ?, ?, NULL)
   ");
   $stmt->bind_param("isi", $kdbarang_db, $nmbarang, $cut);

   if ($stmt->execute()) {
      echo "<script>
         alert('Barang utama berhasil disimpan dengan kode: $kdbarang_db');
         window.location='barang.php';
      </script>";
   } else {
      echo "<script>alert('Gagal menyimpan barang utama.'); window.history.back();</script>";
   }
   $stmt->close();

   /* =========================
   BARANG TURUNAN
========================= */
} elseif ($tipebarang === 'turunan') {

   if (!$kodeinduk) {
      echo "<script>alert('Barang induk harus dipilih untuk produk turunan.'); window.history.back();</script>";
      exit;
   }

   // Hitung jumlah turunan dari induk
   $stmt = $conn->prepare("SELECT COUNT(*) FROM barang WHERE kodeinduk = ?");
   $stmt->bind_param("i", $kodeinduk);
   $stmt->execute();
   $stmt->bind_result($jumlahTurunan);
   $stmt->fetch();
   $stmt->close();

   $kdbarang_db = $kodeinduk + $jumlahTurunan + 1;

   // Pastikan kode belum dipakai
   $cekKode = $conn->prepare("SELECT 1 FROM barang WHERE kdbarang = ?");
   $cekKode->bind_param("i", $kdbarang_db);
   $cekKode->execute();
   $cekKode->store_result();
   if ($cekKode->num_rows > 0) {
      echo "<script>alert('Kode barang turunan sudah ada, silakan ulangi proses.'); window.history.back();</script>";
      $cekKode->close();
      $conn->close();
      exit;
   }
   $cekKode->close();

   // Insert barang turunan
   $stmt = $conn->prepare("
      INSERT INTO barang (kdbarang, nmbarang, idcut, kodeinduk)
      VALUES (?, ?, ?, ?)
   ");
   $stmt->bind_param("isii", $kdbarang_db, $nmbarang, $cut, $kodeinduk);

   if ($stmt->execute()) {
      echo "<script>
         alert('Barang turunan berhasil disimpan dengan kode: $kdbarang_db');
         window.location='barang.php';
      </script>";
   } else {
      echo "<script>alert('Gagal menyimpan barang turunan.'); window.history.back();</script>";
   }
   $stmt->close();
} else {
   echo "<script>alert('Tipe barang tidak valid.'); window.history.back();</script>";
}

$conn->close();
