<?php
$files = [
    'resources/views/filament/admin/resources/mutation-resource/pages/receive-mutation.blade.php',
    'resources/views/filament/admin/resources/mutation-resource/pages/scan-mutation.blade.php'
];

$replacements = [
    "Tanggal:" => "{{ __('Date:') }}",
    "Dari:" => "{{ __('From:') }}",
    "Tujuan:" => "{{ __('To:') }}",
    "Telah Diterima" => "{{ __('Received') }}",
    "Menunggu" => "{{ __('Waiting') }}",
    "Arahkan scanner atau ketik barcode secara manual" => "{{ __('Aim scanner or type barcode manually') }}",
    "Daftar Barang yang Diterima" => "{{ __('List of Received Items') }}",
    "Daftar Barang yang Di-scan" => "{{ __('List of Scanned Items') }}"
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        foreach ($replacements as $from => $to) {
            $content = str_replace(">".$from."<", ">".$to."<", $content);
            $content = str_replace(trim($from), trim($to), $content); // Simple replace
        }
        // Correct double replacements
        $content = str_replace("{{ __('{{ __('", "{{ __('", $content);
        $content = str_replace("') }}') }}", "') }}", $content);
        file_put_contents($file, $content);
    }
}
echo "Done";
