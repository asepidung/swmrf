<?php

/*
 * Nomor legal yang tercetak di SEMUA label produk -- satu rumah. Dipakai
 * lewat partial `print.partials.legal-block`; jangan diketik ulang di Blade
 * label manapun (sertifikat halal ada masa berlakunya, kalau diperbarui
 * cukup diubah di sini).
 *
 * Logo Halal (`public/img/halalrebah.png`) sendiri memuat nomor sertifikat
 * halal RPH -- gambarnya yang harus diganti kalau nomor itu berubah.
 */
return [
    'halal_gudang' => 'ID00310000134840521',
    'nkv_rph' => 'RPHR-3201170-007',
    'nkv_gudang' => 'CS-3201170-027',
    'registrasi_produk' => 'PHD320104012400241',
];
