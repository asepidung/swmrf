<?php

/*
 * Riwayat versi aplikasi (permintaan Ayah, 31 Oktober 2026).
 *
 * Nomor versi: MAJOR.MODUL.PERBAIKAN.
 *  - MAJOR      : perombakan besar. 2 = aplikasi baru (refactory) yang menggantikan legacy.
 *  - MODUL      : naik setiap ada modul atau fitur baru; PERBAIKAN kembali ke 0.
 *  - PERBAIKAN  : naik setiap perbaikan bug.
 *
 * Daftar ini DIISI setiap kali Ayah menyatakan "fix" atau meminta penambahan: tambahkan satu entri di PALING ATAS dengan
 * nomor versi berikutnya. Versi yang tampil di footer adalah entri teratas. Pengguna melihat catatannya lewat klik
 * "Ver ..." di footer, dan satu kali per versi lewat jendela "Yang baru" saat mereka membuka aplikasi.
 *
 * kind: release | modul | fix. Tulis item dengan bahasa yang dimengerti pengguna lapangan, bukan bahasa teknis.
 */
return [
    'entries' => [
        [
            'version' => '2.0.0',
            'date' => '2026-11-02',
            'kind' => 'release',
            'title' => 'Aplikasi baru menggantikan legacy',
            'items' => [
                'Seluruh pekerjaan pindah dari aplikasi lama (legacy) ke WijayaApps yang baru.',
                'Data riwayat dari legacy sampai 31 Oktober 2026 sudah tersedia di aplikasi baru.',
            ],
        ],
    ],
];
