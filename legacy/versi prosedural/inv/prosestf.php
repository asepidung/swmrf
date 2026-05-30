<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   $idinvoice = isset($_POST['idinvoice']) ? intval($_POST['idinvoice']) : 0;
   if ($idinvoice <= 0) {
      die("ID Invoice tidak valid.");
   }

   $tgltf = mysqli_real_escape_string($conn, $_POST['tgltf']);
   $note = mysqli_real_escape_string($conn, $_POST['note']);
   $top = mysqli_real_escape_string($conn, $_POST['top']);
   $tgltf_date_obj = new DateTime($tgltf);
   // Tambahkan TOP (jangka waktu pembayaran) ke invoice_date_obj
   $duedate_obj = clone $tgltf_date_obj; // Duplikasi objek tanggal tgltf_date_obj
   $duedate_obj->modify("+" . $top . " days"); // Tambahkan TOP (jangka waktu pembayaran) ke objek tanggal
   $duedate = $duedate_obj->format('Y-m-d');
   // Update status invoice menjadi "Sudah TF"
   $queryUpdateStatus = "UPDATE invoice SET status = 'Sudah TF' WHERE idinvoice = $idinvoice";
   $resultUpdateStatus = mysqli_query($conn, $queryUpdateStatus);

   if ($resultUpdateStatus) {
      // Update tanggal transfer (termasuk jika kolom 'tgltf' bernilai null)
      $queryUpdateTglTF = "UPDATE invoice SET tgltf = '$tgltf' WHERE idinvoice = $idinvoice";
      $resultUpdateTglTF = mysqli_query($conn, $queryUpdateTglTF);

      if ($resultUpdateTglTF) {
         // Update duedate column
         $queryUpdateDuedate = "UPDATE invoice SET duedate = '$duedate' WHERE idinvoice = $idinvoice";
         $resultUpdateDuedate = mysqli_query($conn, $queryUpdateDuedate);

         // $PiutangUpdateDuedate = "UPDATE piutang SET duedate = '$duedate' WHERE idinvoice = $idinvoice";
         // $resultpiutangUpdateDuedate = mysqli_query($conn, $PiutangUpdateDuedate);


         if ($resultUpdateDuedate) {
            // Tambahkan nilai baru ke kolom 'note' tanpa menggantikan yang sudah ada
            $queryUpdateNote = "UPDATE invoice SET note = CONCAT(note, ' ','|', '$note') WHERE idinvoice = $idinvoice";
            $resultUpdateNote = mysqli_query($conn, $queryUpdateNote);

            if ($resultUpdateNote) {
               header("location: invoice.php");
            } else {
               echo "Gagal menambahkan note.";
            }
         } else {
            echo "Gagal mengupdate duedate.";
         }
      } else {
         echo "Gagal mengupdate tanggal transfer.";
      }
   } else {
      echo "Gagal mengupdate status.";
   }
}
// ...
