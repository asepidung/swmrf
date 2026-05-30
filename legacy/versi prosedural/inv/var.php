<?php
require "../verifications/auth.php";
require "../konak/conn.php";

// Check if the form is submitted
if (isset($_POST['submit'])) {
   // Retrieve data from the form and remove commas
   $noinvoice = $_POST['noinvoice'];
   $iddoreceipt = $_POST['iddoreceipt'];
   $idsegment = $_POST['idsegment'];
   $top = $_POST['top'];
   $invoice_date = $_POST['invoice_date'];
   $idcustomer = $_POST['idcustomer'];
   $pocustomer = $_POST['pocustomer'];
   $donumber = $_POST['donumber'];
   $note = $_POST['note'];
   $pajak = $_POST['pajak'];

   $xweight = $_POST['xweight'];
   $xamount = str_replace(',', '', $_POST['xamount']);
   $xdiscount = str_replace(',', '', $_POST['xdiscount']);
   $tax = str_replace(',', '', $_POST['tax']);
   $charge = str_replace(',', '', $_POST['charge']);
   $downpayment = str_replace(',', '', $_POST['downpayment']);
   $balance = str_replace(',', '', $_POST['balance']);
   $tukarfaktur = $_POST['tukarfaktur'];
   if ($tukarfaktur == 'YES') {
      $status = NULL;
      $duedate = NULL;
   } else {
      $status = 'hitung';
      $invoice_date_obj = new DateTime($invoice_date);

      // Tambahkan TOP (jangka waktu pembayaran) ke invoice_date_obj
      $duedate_obj = clone $invoice_date_obj; // Duplikasi objek tanggal invoice_date_obj
      $duedate_obj->modify("+" . $top . " days"); // Tambahkan TOP (jangka waktu pembayaran) ke objek tanggal

      // Format duedate menjadi string dengan format 'Y-m-d' (optional, tergantung kebutuhan)
      $duedate = $duedate_obj->format('Y-m-d');
   }

   // Print data submitted via POST without commas
   var_dump($pajak);
   echo "<br>";
   var_dump($xamount);
   echo "<br>";
   echo "noinvoice: $noinvoice<br>";
   echo "iddoreceipt: $iddoreceipt<br>";
   echo "idsegment: $idsegment<br>";
   echo "top: $top<br>";
   echo "tukarfaktur: $tukarfaktur<br>";
   echo "invoice_date: $invoice_date<br>";
   echo "idcustomer: $idcustomer<br>";
   echo "pocustomer: $pocustomer<br>";
   echo "donumber: $donumber<br>";
   echo "note: $note<br>";
   echo "xweight: $xweight<br>";
   echo "xamount: $xamount<br>";
   echo "xdiscount: $xdiscount<br>";
   echo "tax: $tax<br>";
   echo "charge: $charge<br>";
   echo "downpayment: $downpayment<br>";
   echo "balance: $balance<br>";

   // Retrieve the last inserted invoice ID
   $invoiceID = 1234; // Replace with the actual invoice ID if needed

   // Print data from invoicedetail and remove commas
   $idgrade = $_POST['idgrade'];
   $idbarang = $_POST['idbarang'];
   $weight = $_POST['weight'];
   $price = $_POST['price'];
   $discount = $_POST['discount'];
   $discountrp = $_POST['discountrp'];
   $amount = $_POST['amount'];

   for ($i = 0; $i < count($idgrade); $i++) {
      $idgrade[$i] = $idgrade[$i];
      $idbarang[$i] = $idbarang[$i];
      $weight[$i] = $weight[$i];
      $price[$i] = str_replace(',', '', $price[$i]);
      $discount[$i] = $discount[$i];
      $discountrp[$i] = str_replace(',', '', $discountrp[$i]);
      $amount[$i] = str_replace(',', '', $amount[$i]);

      echo "idgrade[$i]: {$idgrade[$i]}<br>";
      echo "idbarang[$i]: {$idbarang[$i]}<br>";
      echo "weight[$i]: {$weight[$i]}<br>";
      echo "price[$i]: {$price[$i]}<br>";
      echo "discount[$i]: {$discount[$i]}<br>";
      echo "discountrp[$i]: {$discountrp[$i]}<br>";
      echo "amount[$i]: {$amount[$i]}<br>";
   }

   // Redirect to a success page or perform any other actions
   // header("location: success.php");
   // exit();
}
?>
<!-- Your existing HTML and JavaScript code goes here -->