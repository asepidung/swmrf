<?php
$file = 'app/Filament/Admin/Resources/MutationResource/Pages/ReceiveMutation.php';
$content = file_get_contents($file);

$replacements = [
    "'Scan Barcode'" => "__('Scan Barcode')",
    "'Barcode'" => "__('Barcode')",
    "'Product'" => "__('Product')",
    "'Qty'" => "__('Qty')",
    "'Pcs'" => "__('Pcs')",
    "'Grade'" => "__('Grade')",
    "'pH'" => "__('pH')",
    "'POD'" => "__('POD')",
    "'Origin'" => "__('Origin')",
    "'Kembali'" => "__('Back')",
    "'Terima Semua'" => "__('Receive All')",
    "'Selesai Penerimaan'" => "__('Complete Receiving')",
    "'Apakah Anda yakin ingin menerima semua barang mutasi ini seketika?'" => "__('Are you sure you want to receive all mutation items at once?')",
    "'Apakah Anda yakin ingin menyelesaikan penerimaan? Barang yang belum di-scan tidak akan dimasukkan ke gudang tujuan dan akan dianggap selisih.'" => "__('Are you sure you want to complete the receiving? Unscanned items will not be added to the destination warehouse and will be considered as discrepancy.')",
    "'Semua barang berhasil diterima.'" => "__('All items received successfully.')",
    "'Penerimaan berhasil diselesaikan.'" => "__('Receiving completed successfully.')",
    "'Belum ada barang yang di-scan!'" => "__('No items have been scanned yet!')",
    "'Barang berhasil di-scan'" => "__('Item scanned successfully')",
    "'Barang sudah di-scan sebelumnya'" => "__('Item has already been scanned')",
    "'Barcode tidak ditemukan dalam mutasi ini'" => "__('Barcode not found in this mutation')"
];

foreach ($replacements as $from => $to) {
    $content = str_replace($from, $to, $content);
}
file_put_contents($file, $content);
echo "Done";
