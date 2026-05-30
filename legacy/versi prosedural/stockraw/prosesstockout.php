<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "idtransaksi.php"; // Untuk mendapatkan $idtransaksi dari idtransaksi.php

// Fungsi untuk menghapus format digit grouping (koma) untuk perhitungan
function unformatNumber($num)
{
    return floatval(str_replace(',', '', $num)); // Menghapus koma dan mengubah ke float
}

if (isset($_POST['submit'])) {
    $date = $_POST['date']; // Tanggal pemakaian
    $note = $_POST['note']; // Catatan pemakaian
    $idrawmate = $_POST['idrawmate']; // Array idrawmate
    $weight = $_POST['weight']; // Array qty yang diterima
    $notes = $_POST['notes']; // Array catatan untuk setiap item

    // Mulai transaksi
    $conn->autocommit(false); // Mulai transaksi

    try {
        // Loop untuk memasukkan setiap item ke tabel stockraw
        foreach ($idrawmate as $index => $idraw) {
            $qty = -abs(unformatNumber($weight[$index])); // Mengubah qty menjadi negatif (minus)

            // Insert data ke tabel stockraw
            $query_stockraw = "INSERT INTO stockraw (idtransaksi, idrawmate, qty) VALUES (?, ?, ?)";
            $stmt_stockraw = $conn->prepare($query_stockraw);

            if (!$stmt_stockraw) {
                throw new Exception("Error preparing stockraw insert query: " . $conn->error);
            }

            // Bind parameter dan eksekusi insert
            $stmt_stockraw->bind_param("sii", $idtransaksi, $idraw, $qty); // Menggunakan idtransaksi dan qty negatif
            if (!$stmt_stockraw->execute()) {
                throw new Exception("Error executing stockraw insert: " . $stmt_stockraw->error);
            }
        }

        // Setelah berhasil memasukkan data, lakukan commit transaksi
        $conn->commit();

        // Redirect ke halaman lain dengan pesan sukses
        echo "<script>alert('Data berhasil diproses.'); window.location='index.php';</script>";
        exit();
    } catch (Exception $e) {
        // Rollback transaksi jika terjadi kesalahan
        $conn->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='index.php';</script>";
    } finally {
        $conn->autocommit(true); // Kembalikan autocommit ke true

        if (isset($stmt_stockraw)) {
            $stmt_stockraw->close();
        }

        $conn->close();
    }
}
