<?php
// Detail Database sesuai info lo
$host     = "localhost"; // Hostinger biasanya localhost
$username = "u525862761_idung";
$password = 'H4f1zh(!!$@%%@'; // Gunakan petik tunggal jika ada karakter khusus
$dbname   = "u525862761_swm";

// Lokasi penyimpanan backup
$backupDir = __DIR__ . "/backups";
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Nama file backup (format: db-swm-2026-02-24_00-00.sql.gz)
$fileName = "db-swm-" . date("Y-m-d_H-i") . ".sql.gz";
$filePath = $backupDir . "/" . $fileName;

// Command untuk mysqldump (Hostinger mendukung exec/shell_exec)
// Kita langsung kompres pake gzip biar file enteng
$command = "mysqldump --user=$username --password='$password' --host=$host $dbname | gzip > $filePath";

exec($command, $output, $returnVar);

if ($returnVar === 0) {
    echo "Backup Berhasil: $fileName\n";

    // --- OPTIONAL: HAPUS BACKUP LAMA (Misal simpan 7 hari terakhir aja) ---
    $daysToKeep = 7;
    $files = glob($backupDir . "/*.sql.gz");
    foreach ($files as $file) {
        if (is_file($file) && time() - filemtime($file) > ($daysToKeep * 24 * 60 * 60)) {
            unlink($file);
            echo "Hapus backup lama: " . basename($file) . "\n";
        }
    }
} else {
    echo "Backup GAGAL. Cek kredensial atau izin exec() hosting lo.";
}
