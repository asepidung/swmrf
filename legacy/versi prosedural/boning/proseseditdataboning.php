<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $idboning = $_POST['idboning'];
  $idsupplier = $_POST['idsupplier'];
  $tglboning = $_POST['tglboning'];
  $qtysapi = $_POST['qtysapi'];
  $keterangan = $_POST['keterangan'];
  $batchboning = $_POST['batchboning'];
  $idusers = $_SESSION['idusers'];

  // Update data boning di database
  $query = "UPDATE boning SET idsupplier = '$idsupplier', tglboning = '$tglboning', qtysapi = $qtysapi, keterangan = '$keterangan' WHERE idboning = '$idboning'";
  $result = mysqli_query($conn, $query);

  if ($result) {
    // Jika data berhasil diupdate, catat aktivitas ke logactivity
    $logSql = "INSERT INTO logactivity (iduser, event, docnumb) VALUES ('$idusers', 'Update Batch Boning', '$batchboning')";
    mysqli_query($conn, $logSql);

    // Redirect ke halaman lain atau lakukan tindakan lainnya
    header("location: databoning.php");
    exit();
  } else {
    // Jika terjadi kesalahan saat mengupdate data, tampilkan pesan error atau lakukan tindakan lainnya
    echo "Error: " . mysqli_error($conn);
  }
}

// Jika halaman ini diakses tanpa melalui metode POST, redirect ke halaman lain atau lakukan tindakan lainnya
header("location: editboning.php");
exit();
