<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "invnumber.php"; // Pastikan file ini menghasilkan variabel $noinvoice

// Fungsi untuk menormalkan angka
function normalizeNumber($number)
{
   // Jika kosong atau null, kembalikan 0
   if (empty($number)) return 0;

   $lastCommaPos = strrpos($number, ',');
   $lastDotPos = strrpos($number, '.');

   $decimalSeparator = $lastCommaPos > $lastDotPos ? ',' : '.';
   $thousandSeparator = $lastCommaPos < $lastDotPos ? ',' : '.';

   $number = str_replace($thousandSeparator, '', $number);
   if ($decimalSeparator != '.') {
      $number = str_replace($decimalSeparator, '.', $number);
   }

   return (float) $number;
}

if (isset($_POST['submit'])) {
   // 1. Ambil & Amankan Input String (Mencegah Error Tanda Petik)
   $iddo = mysqli_real_escape_string($conn, $_POST['iddo']);
   $iddoreceipt = mysqli_real_escape_string($conn, $_POST['iddoreceipt']);
   $idsegment = mysqli_real_escape_string($conn, $_POST['idsegment']);
   $top = (int) $_POST['top']; // Pastikan integer
   $invoice_date = mysqli_real_escape_string($conn, $_POST['invoice_date']);
   $idcustomer = mysqli_real_escape_string($conn, $_POST['idcustomer']);
   $idgroup = mysqli_real_escape_string($conn, $_POST['idgroup']);
   $pocustomer = mysqli_real_escape_string($conn, $_POST['pocustomer']);
   $donumber = mysqli_real_escape_string($conn, $_POST['donumber']);
   $note = mysqli_real_escape_string($conn, $_POST['note']);
   $tukarfaktur = mysqli_real_escape_string($conn, $_POST['tukarfaktur']);

   // 2. Normalisasi Angka Header
   $xweight = normalizeNumber($_POST['xweight']);
   $xamount = normalizeNumber($_POST['xamount']);
   $xdiscount = normalizeNumber($_POST['xdiscount']);
   $tax = normalizeNumber($_POST['tax']);
   $charge = normalizeNumber($_POST['charge']);
   $downpayment = normalizeNumber($_POST['downpayment']);
   $balance = normalizeNumber($_POST['balance']);

   // Cek duplikasi Invoice
   $checkInvoiceQuery = "SELECT COUNT(*) AS invoiceCount FROM invoice WHERE iddoreceipt = '$iddoreceipt' AND is_deleted = 0";
   $checkInvoiceResult = mysqli_query($conn, $checkInvoiceQuery);
   $checkInvoiceRow = mysqli_fetch_assoc($checkInvoiceResult);

   if ($checkInvoiceRow['invoiceCount'] > 0) {
      header("location: invoice.php?message=Invoice Sudah Dibuat");
      exit();
   }

   // Hitung Due Date
   try {
      $invoice_date_obj = new DateTime($invoice_date);
      $duedate_obj = clone $invoice_date_obj;
      $duedate_obj->modify("+" . $top . " days");
      $duedate = $duedate_obj->format('Y-m-d');
   } catch (Exception $e) {
      // Fallback jika format tanggal salah
      $duedate = date('Y-m-d');
   }

   $status = ($tukarfaktur == 'YES') ? 'Belum TF' : '-';

   // --- MULAI TRANSAKSI DATABASE ---
   mysqli_begin_transaction($conn);

   try {
      // A. Insert Header Invoice
      $sql = "INSERT INTO invoice (noinvoice, iddoreceipt, idsegment, top, duedate, invoice_date, status, tgltf, idcustomer, pocustomer, donumber, note, xweight, xamount, xdiscount, tax, charge, downpayment, balance) 
                VALUES ('$noinvoice', '$iddoreceipt', '$idsegment', '$top', '$duedate', '$invoice_date', '$status', NULL, '$idcustomer', '$pocustomer', '$donumber', '$note', '$xweight', '$xamount', '$xdiscount', '$tax', '$charge', '$downpayment', '$balance')";

      if (!mysqli_query($conn, $sql)) {
         throw new Exception("Gagal Insert Invoice: " . mysqli_error($conn));
      }

      $invoiceID = mysqli_insert_id($conn);

      // B. Insert Piutang
      $sql2 = "INSERT INTO piutang (idgroup, idinvoice, idcustomer) 
                 VALUES ('$idgroup', '$invoiceID', '$idcustomer')";
      if (!mysqli_query($conn, $sql2)) {
         throw new Exception("Gagal Insert Piutang");
      }

      // C. Insert Detail Barang
      $idbarang = $_POST['idbarang'];
      $weight = $_POST['weight'];
      $price = $_POST['price'];
      $discount = $_POST['discount'];
      $discountrp = $_POST['discountrp'];
      $amount = $_POST['amount'];

      // Menggunakan count di luar loop agar lebih cepat
      $totalItems = count($idbarang);

      for ($i = 0; $i < $totalItems; $i++) {
         // Ambil dan bersihkan data per baris
         $curr_idbarang = mysqli_real_escape_string($conn, $idbarang[$i]);

         // PERBAIKAN PENTING: Normalize Weight di sini!
         $curr_weight = normalizeNumber($weight[$i]);

         $curr_price = normalizeNumber($price[$i]);
         $curr_discount = mysqli_real_escape_string($conn, $discount[$i]); // Biasanya persen berupa string/angka
         $curr_discountrp = normalizeNumber($discountrp[$i]);
         $curr_amount = normalizeNumber($amount[$i]);

         $sqlDetail = "INSERT INTO invoicedetail (idinvoice, idbarang, weight, price, discount, discountrp, amount) 
                          VALUES ('$invoiceID', '$curr_idbarang', '$curr_weight', '$curr_price', '$curr_discount', '$curr_discountrp', '$curr_amount')";

         if (!mysqli_query($conn, $sqlDetail)) {
            throw new Exception("Gagal Insert Detail Barang ke-" . ($i + 1));
         }
      }

      // D. Update Status DO & Receipt
      $updateSql1 = "UPDATE doreceipt SET status = 'Invoiced' WHERE iddoreceipt = '$iddoreceipt'";
      mysqli_query($conn, $updateSql1);

      $updateSql = "UPDATE do SET status = 'Invoiced' WHERE iddo = '$iddo'";
      mysqli_query($conn, $updateSql);

      // E. Log Activity (Menggunakan Prepared Statement yang sudah benar)
      $idusers = $_SESSION['idusers'];
      $event = "Buat Invoice";
      $logQuery = "INSERT INTO logactivity (iduser, docnumb, event, waktu) VALUES (?, ?, ?, NOW())";
      $stmt_log = $conn->prepare($logQuery);
      $stmt_log->bind_param("iss", $idusers, $noinvoice, $event);
      $stmt_log->execute();
      $stmt_log->close();

      // --- COMMIT TRANSAKSI (Simpan Permanen) ---
      mysqli_commit($conn);

      // Redirect Sukses
      header("location: invoice.php");
      exit();
   } catch (Exception $e) {
      // --- ROLLBACK (Batalkan semua jika ada error) ---
      mysqli_rollback($conn);

      // Tampilkan Error (Untuk Debugging) atau Redirect Error
      echo "Terjadi Kesalahan: " . $e->getMessage();
      // Atau: header("location: invoice.php?status=error&msg=" . urlencode($e->getMessage()));
      exit();
   }
}
