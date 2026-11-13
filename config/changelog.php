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
            'version' => '2.0.9',
            'date' => '2026-11-13',
            'kind' => 'fix',
            'title' => 'Penjagaan hapus di Penerimaan Produk dan ekspor detail stok',
            'items' => [
                'Penerimaan Produk (scan dan labeling): barang yang sudah masuk Tally atau jadi bahan Repack kini diberi tanda "T" atau "R" dan tidak bisa dihapus dari stok, seperti di Labeling Boning dan hasil Repack. Sebelumnya barang yang sudah dimuat untuk dikirim masih bisa terhapus dari sini.',
                'Detail stok per produk: ada tombol Export Data (Excel dan PDF) untuk barcode yang sedang tampil, lengkap dengan total berat dan pcs. Mencari atau mengurutkan di layar ikut menentukan isi berkasnya.',
            ],
        ],

        [
            'version' => '2.0.8',
            'date' => '2026-11-10',
            'kind' => 'fix',
            'title' => 'Nilai susut kirim, QC dokumen terhapus, dan tanggal PO',
            'items' => [
                'Financial Loss: susut kirim (Delivery Order) kini punya nilai rupiah, dihitung dari kg susut dikali harga pokok karton yang dikirim. Kalau ada karton yang belum punya harga pokok, nilainya tetap "belum dinilai" dan terisi sendiri begitu harganya masuk. Repack yang sebelumnya tertahan karena barcode berputar juga sudah terhitung.',
                'QC Reports: tugas QC untuk dokumen yang sudah dihapus, atau tally yang Sales Order-nya dihapus, tidak lagi muncul di daftar dan di hitungan tugas dashboard. Nomor dokumen yang terhapus ditandai "(Dihapus)" dan keterangannya tetap terbaca.',
                'PO Material dan PO Produk: tanggal PO kini selalu hari finance menyetujui, tidak lagi mengikuti tanggal jatuh tempo request.',
                'Daftar dokumen: arahkan kursor ke nama pembuat atau penyetuju untuk melihat kapan dokumennya dibuat atau disetujui (tanggal dan jam), tanpa kolom tambahan. Berlaku di semua daftar yang menampilkan nama itu.',
                'Daftar Plan Sales Return: ada kolom baru "Dibuat Oleh" supaya jelas siapa yang membuat tiap plan.',
                'Dashboard: kartu "Items in stock over 60 days" kini hanya tampil untuk pengguna yang punya izin melihat umur stok daging, sehingga tidak ada lagi kartu yang berujung halaman ditolak.',
                'Labeling Boning dan hasil Repack: barang yang sudah masuk Tally diberi tanda "T", dan yang sudah jadi bahan Repack diberi tanda "R". Barang bertanda T atau R tidak bisa dihapus (tombol hapusnya tidak tampil) supaya stoknya tidak selisih; begitu dilepas dari Tally atau bahan Repack, tombol hapusnya muncul lagi. Sebelumnya penanda T di Labeling Boning masih bertanda "D" dan hasil Repack belum punya penjagaan ini.',
            ],
        ],

        [
            'version' => '2.0.7',
            'date' => '2026-11-09',
            'kind' => 'fix',
            'title' => 'Retur di invoice, harga cadangan produk, dan riwayat versi',
            'items' => [
                'Invoice (cetakan): barang yang diretur pelanggan kini tampil sebagai baris pengurang berwarna merah tepat di bawah barisnya, dengan berat dan total bernilai minus (contoh: Retur STRIPLOIN TS -5,18 Kg). Baris asli tetap menunjukkan yang dikirim, sedangkan Grand Total dan total berat sudah memperhitungkan retur dan sama dengan yang ditagih.',
                'Daftar Invoice: kolom Nilai Invoice kini sudah dikurangi retur yang disetujui, dengan keterangan "Dikurangi Retur" dan besarnya di bawah angka.',
                'Master Produk: ada isian baru "Harga cadangan per kg" (opsional). Dipakai hanya untuk menilai retur jual dan temuan opname daging saat produk itu tidak punya stok berharga, supaya nilainya tidak kosong. Kalau ada stok berharga, rata-rata stok tetap didahulukan.',
                'Invoice (cetakan): Grand Total kini ikut menghitung biaya tambahan (Delivery Cost, Ice Gell, Styrofoam, dan sejenisnya). Sebelumnya hanya barang yang dijumlah, sehingga angkanya berbeda dari Nilai Invoice di daftar. Kalimat "Says" (terbilang) juga kini menuliskan nilai invoice, bukan sisa tagihan, jadi invoice yang sudah lunas tidak lagi tertulis kosong.',
                'Invoice (form): kotak nama pada Other Charges kini menampilkan nama biaya yang tersimpan (termasuk nama dari invoice lama, mis. "STYROFOAM BESAR GARUDA"), tidak lagi terbaca "Product" seolah kosong.',
                'Riwayat versi: hanya versi terbaru yang terbuka, versi-versi lama terlipat dan bisa dibuka satu per satu.',
            ],
        ],

        [
            'version' => '2.0.6',
            'date' => '2026-11-06',
            'kind' => 'fix',
            'title' => 'Pengingat kasbon, laporan opname material, dan tampilan',
            'items' => [
                'Dashboard: yang berhak membuat pengeluaran kini melihat kartu pengingat jumlah kasbon (advance) yang belum dilaporkan. Klik kartunya untuk membuka daftar Pengeluaran.',
                'Opname Material: opname yang sudah selesai punya tombol Analisa, laporan yang disusun otomatis oleh sistem: ringkasan barang sesuai, lebih, dan kurang, rincian per kategori, barang yang selisih, riwayat hitung ulang beserta alasannya, dan lampiran seluruh barang. Dalam jumlah, bukan rupiah.',
                'Opname Material: tombol Force delete tidak lagi muncul di halaman Review untuk dokumen yang sudah dihitung, dikirim, atau selesai.',
                'Opname Material: peninjau di tahap Review kini juga melihat kolom stok sistem (di detail dan cetakan), jadi selisih mudah dibaca, misalnya sistem 0 dan hitungan 5 berarti lebih 5. Penghitung tetap tidak melihatnya.',
                'Opname Material: kolom Satuan ditambahkan di tabel detail dan cetakan opname.',
                'Opname Material: barang yang belum diisi saat Submit for Review dianggap 0 dan diberi keterangan "Tidak diisi, dianggap 0", sehingga selisihnya terbaca saat review.',
                'Sesi habis: kalau aplikasi dibiarkan lama lalu sesi berakhir, muncul kotak "Sesi Anda masuk chiller dulu" dengan tombol masuk lagi, menggantikan kotak bawaan peramban. Halaman tidak dimuat ulang sendiri, jadi isian yang belum disimpan masih terlihat.',
                'Tampilan: tombol utama dan tombol hijau lebih tua sehingga tulisan putihnya lebih jelas terbaca, dan cincin fokus saat menekan Tab lebih terlihat.',
                'Tampilan di HP: ukuran huruf dasar mengikuti pengaturan perangkat, dan tombol "+ Spec" serta tong sampah di item Sales Order cukup besar untuk disentuh.',
                'Sales Order: kalau ada isian wajib yang kosong, pesan galatnya tampil langsung di isian yang bersangkutan saat menekan Simpan.',
            ],
        ],

        [
            'version' => '2.0.5',
            'date' => '2026-11-05',
            'kind' => 'fix',
            'title' => 'Perbaikan opname, repack, dan pengeluaran',
            'items' => [
                'Opname Material: kolom Qty Fisik kembali berupa kotak isian sehingga hitungan bisa dimasukkan. Daftar barang urut abjad menurut nama.',
                'Opname Material: stok sistem dan selisih tidak terlihat selama menghitung, termasuk di Detail Hitung dan cetakan. Selisih baru terlihat setelah opname selesai, atau oleh peninjau saat tahap review.',
                'Opname Material: peninjau bisa menolak hasil dengan tombol Tolak / Hitung Ulang, memilih barang yang dihitung ulang dan menulis alasannya. Penghitung membaca alasan itu di halaman input, dan dashboard memberi tahu peninjau (menunggu ditinjau) serta penghitung (harus dihitung ulang).',
                'Opname Material: kalau tab ditutup atau halaman di-refresh saat ada angka yang belum tersimpan, muncul peringatan dulu.',
                'Opname Material: ada tombol Submit for Review di halaman Input Stock dan tombol Tinjau di daftar untuk dokumen yang menunggu keputusan. Complete Opname di tahap review kini benar-benar menerapkan selisih ke stok, dan peninjau cukup berizin Selesaikan tanpa izin Ubah.',
                'Repack: tabel input bahan menampilkan POD barang yang dipindai. Judul kolom POD seragam di Repack dan Tally.',
                'Pengeluaran (Expenses): cara pembayaran bisa diganti saat edit, dari Reimburse ke Kasbon atau sebaliknya, selama kasbon belum dilaporkan atau uangnya belum bergerak lagi.',
                'Opname Daging: opname yang sudah selesai punya tombol Analisa, laporan yang disusun otomatis oleh sistem: ringkasan selisih, nilai rupiah (HPP), pasangan barang hilang dan temuan yang kemungkinan karton yang sama, rincian per produk, dan lampiran daftar barcode untuk dicek gudang.',
                'Cetak DO: nomor DO dan isi di tepi kertas tidak lagi terpotong, karena margin kiri-kanan dilebarkan.',
            ],
        ],

        [
            'version' => '2.0.4',
            'date' => '2026-11-04',
            'kind' => 'fix',
            'title' => 'Perbaikan tanggal, rencana kirim, dan label',
            'items' => [
                'Tanggal tidak lagi bergeser mundur sehari setiap kali dokumen diedit dan disimpan (misalnya tanggal kirim Sales Order). Berlaku untuk semua isian tanggal.',
                'Rencana Pengiriman: customer dari grup KARYAWAN dan WARGA +62 tidak lagi dimasukkan. Pengaturannya ada di master Customer Group.',
                'Rencana Pengiriman: yang sudah lewat tanggal tetapi surat jalannya sudah keluar kini berlabel "Menunggu penerimaan".',
            ],
        ],

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
