<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../dist/vendor/autoload.php"; // Pastikan sudah menginstal Barcode Generator

// Ambil idtally dari parameter GET
$idtally = $_GET['id'];

// Query untuk mengambil data barang dan berat dari tabel tallydetail
$query = "SELECT tallydetail.*, barang.nmbarang 
          FROM tallydetail
          INNER JOIN barang ON tallydetail.idbarang = barang.idbarang
          WHERE tallydetail.idtally = $idtally";
$result = mysqli_query($conn, $query);

// Pastikan ada data
if (mysqli_num_rows($result) == 0) {
    die("No data found for the given idtally.");
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Barcode</title>

    <style>
        /* Pengaturan halaman saat dicetak */
        @media print {

            /* Set ukuran halaman cetak 6cm x 3cm */
            @page {
                size: 6cm 3cm;
                margin: 0;
            }

            /* Mengatur body agar sesuai dengan ukuran cetak */
            body {
                width: 6cm;
                height: 3cm;
                margin: 0;
                font-family: 'Verdana', sans-serif;
                padding: 0;
                text-align: center;
            }

            /* Kontainer barcode dan informasi barang */
            .barcode-container {
                width: 100%;
                height: 100%;
                padding: 5px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            }

            /* Menampilkan gambar barcode */
            .barcode-container img {
                width: 90%;
                height: auto;
                margin: 5px 0;
            }

            /* Teks seperti Nama Barang dan Berat */
            .barcode-container div {
                margin-bottom: 5px;
                font-size: 12px;
                line-height: 1.5;
            }

            .barcode-container strong {
                font-size: 14px;
                font-weight: bold;
            }
        }
    </style>
</head>

<body>
    <?php
    // Loop untuk setiap baris data dari tabel tallydetail
    while ($row = mysqli_fetch_assoc($result)) {
        $nmbarang = $row['nmbarang'];
        $weight = $row['weight'];
        $barcode = $row['barcode'];
        $generator = new Picqer\Barcode\BarcodeGeneratorJPG();
        // Menghasilkan barcode berdasarkan berat
        $barcodeImage = $generator->getBarcode($weight, $generator::TYPE_CODE_128);
    ?>

        <div class="barcode-container">
            <!-- Menampilkan Nama Barang -->
            <div>
                <strong><?= $nmbarang; ?></strong>
            </div>

            <!-- Menampilkan Barcode -->
            <div>
                <img src="data:image/jpeg;base64,<?= base64_encode($barcodeImage); ?>" alt="Barcode" />
            </div>

            <!-- Menampilkan Berat -->
            <div>
                <strong><?= number_format($weight, 2); ?> KG</strong>
            </div>
        </div>

    <?php } // End while 
    ?>
</body>

</html>