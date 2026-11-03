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
 * Jendela "Yang baru" hanya mengumumkan entri bertanggal SEBELUM hari ini (diumumkan pada pembukaan pertama esok harinya),
 * jadi tulis SATU entri per hari, dikumpulkan; jangan satu entri per perbaikan kecil.
 *
 * kind: release | modul | fix. Tulis item dengan bahasa yang dimengerti pengguna lapangan, bukan bahasa teknis.
 */
return [
    'entries' => [

        [
            'version' => '2.0.3',
            'date' => '2026-11-03',
            'kind' => 'fix',
            'title' => 'Perbaikan tampilan, cetak, dan aturan hapus',
            'items' => [
                'Aturan hapus: dokumen yang dihapus tidak bisa dikembalikan. Kalau perlu lagi, buat dokumen baru; nomor lama dilewati. Tally yang dihapus juga dibuat ulang dengan nomor baru.',
                'Tampilan lebih lega di laptop kecil: ukuran otomatis menyesuaikan lebar layar, isi halaman memenuhi lebar, baris tabel lebih rapat, dan tombol Ekspor sebaris dengan kotak cari.',
                'Daftar Faktur: tombol Detail, TTF, dan Draft seragam dan rapi, juga lebih nyaman di HP.',
                'Sales Order: pilihan produk di Add Products tetap sesuai urutan klik. Halaman cetak SO dibuka tanpa harga (tombol Show Price menampilkannya).',
                'Rencana Pengiriman ikut berpindah saat tanggal atau customer SO diubah, dan hilang bila SO-nya dihapus, jadi tidak ada baris kosong.',
                'Cetak DO lebih ringkas seperti legacy, ada tanda tangan Driver, dan kotak centang dokumen (Invoice, NKV, Halal) sesuai data customer.',
                'Scan Tally: notifikasi cukup satu, barcode yang sudah dipindai ditandai jelas, ada tombol Cetak Ulang Barcode, dan batas Max POD aman.',
                'Label barcode lebih lebar, tidak mentok tepi, dan lebih pendek.',
                'Master Customer Group punya status aktif/nonaktif; grup nonaktif tidak muncul lagi di pilihan.',
                'Pengeluaran (Expenses): bisa diedit, termasuk yang sudah Settled; kategori bisa ditambah langsung dengan tombol +, dan nama kategori dirapikan.',
                'Laporan QC punya kolom Keterangan. Activity Logs tidak lagi mencatat item per baris dokumen, dan kolom Target selalu berupa nomor dokumen.',
                'Dashboard: pesan tugas lebih singkat, permintaan material/daging yang masih menunggu selalu tampil walau beda bulan, dan pesan menyebut Finance yang menyetujui.',
            ],
        ],

        [
            'version' => '2.0.2',
            'date' => '2026-11-02',
            'kind' => 'fix',
            'title' => 'Perbaikan cetak, daftar tugas, dan tukar faktur',
            'items' => [
                'Cetakan invoice diperkecil supaya pas di kertas.',
                'Menu Bank Accounts, Payables, Receivables, Cash Book, dan Sales Report dihilangkan.',
                'Daftar Delivery Plans: tanggal yang paling dekat dengan hari ini ada di paling atas.',
                'Activity Logs bisa dibuka lagi, dan kolom Target kini menampilkan nomor dokumen.',
                'Cetak label barcode lebih stabil dan tidak lagi bergantung pada layanan di luar aplikasi.',
                'Stok awal sudah memiliki nilai harga, sehingga nilai repack dan HPP penjualan mulai terisi.',
                'Daftar tugas di Dashboard dirapikan: pesan dari data lama dibersihkan, angka Draft Invoice sama dengan isi halamannya, dan permintaan material/daging dibuka tanpa batas tanggal.',
                'Tukar Faktur: pesan di Dashboard membuka ringkasan per customer. Daftar invoice yang belum ditukar lebih ringkas, dan tombol TTF hanya ada di sana.',
            ],
        ],

        [
            'version' => '2.0.1',
            'date' => '2026-11-02',
            'kind' => 'fix',
            'title' => 'Perbaikan invoice dan nomor SO',
            'items' => [
                'Daftar Draft Invoice kini hanya berisi penerimaan DO yang memang menunggu ditagih; sisa data lama yang di legacy pun tidak punya invoice sudah tidak muncul.',
                'Cetakan invoice lebih besar dan memenuhi lebar kertas.',
                'Nomor SO di daftar Sales Order bisa diklik: membuka halaman cetak SO di tab baru.',
            ],
        ],

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
