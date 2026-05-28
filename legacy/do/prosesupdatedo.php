<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $iddo = isset($_POST['iddo']) ? intval($_POST['iddo']) : 0;
  $deliverydate = $_POST['deliverydate'];
  $po = $_POST['po'];
  $driver = $_POST['driver'];
  $plat = $_POST['plat'];
  $note = $_POST['note'];

  // Validasi atau lakukan penanganan kesalahan jika diperlukan

  // Update data pada tabel 'do'
  $query_do = "UPDATE do SET deliverydate = ?, po = ?, driver = ?, plat = ?, note = ? WHERE iddo = ?";
  $stmt_do = mysqli_prepare($conn, $query_do);
  mysqli_stmt_bind_param($stmt_do, "sssssi", $deliverydate, $po, $driver, $plat, $note, $iddo);

  if (mysqli_stmt_execute($stmt_do)) {
    // Mendapatkan donumber dari tabel do berdasarkan $iddo
    $query_select_donumber = "SELECT donumber FROM do WHERE iddo = ?";
    $stmt_select_donumber = $conn->prepare($query_select_donumber);
    $stmt_select_donumber->bind_param("i", $iddo);
    $stmt_select_donumber->execute();
    $stmt_select_donumber->bind_result($donumber);
    $stmt_select_donumber->fetch();
    $stmt_select_donumber->close();

    // Insert ke tabel logactivity
    $idusers = $_SESSION['idusers'];
    $event = "Edit DO";
    $docnumb = $donumber;
    $waktu = date('Y-m-d H:i:s'); // Waktu saat ini

    $queryLogActivity = "INSERT INTO logactivity (iduser, event, docnumb, waktu) 
                         VALUES ('$idusers', '$event', '$docnumb', '$waktu')";
    $resultLogActivity = mysqli_query($conn, $queryLogActivity);

    if (!$resultLogActivity) {
      echo "Terjadi kesalahan saat memasukkan data log activity: " . mysqli_error($conn);
    }

    // Tutup statement
    mysqli_stmt_close($stmt_do);
    mysqli_close($conn);

    header("location: do.php");
    exit;
  } else {
    echo "Terjadi kesalahan: " . mysqli_error($conn);
    mysqli_stmt_close($stmt_do);
    mysqli_close($conn);
  }
} else {
  // Jika halaman diakses secara langsung tanpa melalui metode POST, arahkan ke halaman yang sesuai
  header("location: do.php");
  exit;
}
