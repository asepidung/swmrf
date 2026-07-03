<?php
$file = 'app/Filament/Admin/Resources/MutationResource/Pages/ScanMutation.php';
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
    "'Selesai Scan'" => "__('Complete Scan')",
    "'Terima Mutasi'" => "__('Receive Mutation')",
    "'Kembali'" => "__('Back')",
    "'Apakah Anda yakin ingin menyelesaikan proses scan? Barang yang tidak ada di daftar ini akan dihapus dari mutasi ini.'" => "__('Are you sure you want to complete scanning? Items not in this list will be removed from this mutation.')",
    "'Scan berhasil diselesaikan.'" => "__('Scan completed successfully.')",
    "'Belum ada barang yang di-scan!'" => "__('No items have been scanned yet!')",
    "'Barang berhasil ditambahkan'" => "__('Item added successfully')",
    "'Barang sudah ada di daftar scan'" => "__('Item is already in the scan list')",
    "'Barcode tidak ditemukan'" => "__('Barcode not found')"
];

foreach ($replacements as $from => $to) {
    $content = str_replace($from, $to, $content);
}
file_put_contents($file, $content);
echo "Done";
