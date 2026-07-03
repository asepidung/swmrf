<?php
$file = 'app/Filament/Admin/Resources/MutationResource/Pages/ViewMutation.php';
$content = file_get_contents($file);

$replacements = [
    "'Cetak Mutasi'" => "__('Print Mutation')",
    "'Terima Mutasi'" => "__('Receive Mutation')",
    "'Edit'" => "__('Edit')",
    "'Kembali'" => "__('Back')"
];

foreach ($replacements as $from => $to) {
    $content = str_replace($from, $to, $content);
}
file_put_contents($file, $content);
echo "Done";
