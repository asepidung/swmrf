<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_POST['idcustomer'])) {
   $selectedCustomerId = $_POST['idcustomer'];

   // Mengambil alamat dari tabel customers
   $alamatQuery = "SELECT alamat1, alamat2, alamat3, catatan FROM customers WHERE idcustomer = $selectedCustomerId";
   $alamatResult = $conn->query($alamatQuery);

   // Buat opsi alamat dan ambil catatan berdasarkan data yang ditemukan
   if ($alamatResult->num_rows > 0) {
      $row = $alamatResult->fetch_assoc();
      $alamatOptions = "";
      $alamatOptions .= "<option value=\"" . $row["alamat1"] . "\">" . $row["alamat1"] . "</option>";
      $alamatOptions .= "<option value=\"" . $row["alamat2"] . "\">" . $row["alamat2"] . "</option>";
      $alamatOptions .= "<option value=\"" . $row["alamat3"] . "\">" . $row["alamat3"] . "</option>";

      // Buat array dengan data alamat dan catatan
      $response = array(
         'alamatOptions' => $alamatOptions,
         'catatan' => $row['catatan']
      );

      // Mengembalikan hasil dalam format JSON
      echo json_encode($response);
   } else {
      echo json_encode(array(
         'alamatOptions' => '<option value="">Tidak Ada Alamat Tersedia</option>',
         // 'catatan' => ''
      ));
   }
}

$conn->close();
