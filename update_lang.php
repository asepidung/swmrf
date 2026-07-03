<?php
$file = 'lang/id.json';
$translations = json_decode(file_get_contents($file), true);

$newTranslations = [
    'Mutation' => 'Mutasi',
    'Mutations' => 'Mutasi',
    'Mutation Date' => 'Tanggal Mutasi',
    'From Warehouse' => 'Dari Gudang (Asal)',
    'To Warehouse' => 'Tujuan Gudang',
    'Mutation Number' => 'No. Mutasi',
    'Status' => 'Status',
    'Note' => 'Catatan',
    'Date' => 'Tanggal',
    'Mutation Header' => 'Header Mutasi',
    'Item Summary' => 'Summary Barang',
    'Scan Barcode' => 'Scan Barcode',
    'Barcode' => 'Barcode',
    'Product' => 'Produk',
    'Qty' => 'Qty',
    'Pcs' => 'Pcs',
    'Grade' => 'Grade',
    'pH' => 'pH',
    'POD' => 'Tgl. Potong',
    'Origin' => 'Asal',
    'Back' => 'Kembali',
    'Receive All' => 'Terima Semua',
    'Complete Receiving' => 'Selesai Penerimaan',
    'Are you sure you want to receive all mutation items at once?' => 'Apakah Anda yakin ingin menerima semua barang mutasi ini seketika?',
    'Are you sure you want to complete the receiving? Unscanned items will not be added to the destination warehouse and will be considered as discrepancy.' => 'Apakah Anda yakin ingin menyelesaikan penerimaan? Barang yang belum di-scan tidak akan dimasukkan ke gudang tujuan dan akan dianggap selisih.',
    'All items received successfully.' => 'Semua barang berhasil diterima.',
    'Receiving completed successfully.' => 'Penerimaan berhasil diselesaikan.',
    'No items have been scanned yet!' => 'Belum ada barang yang di-scan!',
    'Receive Mutation: MT#' => 'Penerimaan Mutasi: MT#',
    'Item scanned successfully' => 'Barang berhasil di-scan',
    'Item has already been scanned' => 'Barang sudah di-scan sebelumnya',
    'Barcode not found in this mutation' => 'Barcode tidak ditemukan dalam mutasi ini',
    'Complete Scan' => 'Selesai Scan',
    'Receive Mutation' => 'Terima Mutasi',
    'Are you sure you want to complete scanning? Items not in this list will be removed from this mutation.' => 'Apakah Anda yakin ingin menyelesaikan proses scan? Barang yang tidak ada di daftar ini akan dihapus dari mutasi ini.',
    'Scan completed successfully.' => 'Scan berhasil diselesaikan.',
    'Item added successfully' => 'Barang berhasil ditambahkan',
    'Item is already in the scan list' => 'Barang sudah ada di daftar scan',
    'Barcode not found' => 'Barcode tidak ditemukan',
    'Print Mutation' => 'Cetak Mutasi',
    'Edit' => 'Edit',
    'Date:' => 'Tanggal:',
    'From:' => 'Dari:',
    'To:' => 'Tujuan:',
    'Received' => 'Telah Diterima',
    'Waiting' => 'Menunggu',
    'Aim scanner or type barcode manually' => 'Arahkan scanner atau ketik barcode secara manual',
    'List of Received Items' => 'Daftar Barang yang Diterima',
    'List of Scanned Items' => 'Daftar Barang yang Di-scan'
];

foreach ($newTranslations as $key => $val) {
    if (!isset($translations[$key])) {
        $translations[$key] = $val;
    }
}

file_put_contents($file, json_encode($translations, JSON_PRETTY_PRINT));
echo 'Done';
