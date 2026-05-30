<?php
require "../verifications/auth.php";
require "../konak/conn.php";

if (isset($_GET['id'])) {
   $id = $_GET['id'];

   // Query DELETE
   $queryDelete = "DELETE FROM relabel WHERE idrelabel = ?";
   $stmt = $conn->prepare($queryDelete);

   if (!$stmt) {
      // Handle the error, display it, or log it
      die('Error in preparing statement: ' . $conn->error);
   }

   $stmt->bind_param('i', $id);

   // Eksekusi query DELETE
   if ($stmt->execute()) {
      // Jika berhasil dihapus, kembalikan ke halaman index.php
      header("location: index.php");
      exit();
   } else {
      // Jika gagal, tampilkan pesan error atau lakukan tindakan lain
      echo "Error in execution: " . $stmt->error;
   }

   // Tutup statement
   $stmt->close();
}

// Tutup koneksi
$conn->close();
