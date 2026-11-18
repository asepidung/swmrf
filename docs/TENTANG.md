# Tentang Aplikasi SWM

## Latar Belakang

Pekerjaan pencatatan di tempat kerja sebelumnya berjalan dengan cara yang membuat tugas sehari-hari berat. Masalah utamanya:

- **Data tidak beraturan.** Catatan tersebar dengan format berbeda-beda sehingga sulit dicocokkan.
- **Pengerjaan lama.** Satu dokumen atau laporan butuh banyak langkah manual dan pengecekan ulang.
- **Data sering hilang.** Catatan yang tercecer atau tertimpa membuat kejadian lama sulit ditelusuri.
- **Tanggung jawab tidak jelas.** Tidak ada kejelasan siapa memegang pekerjaan apa, sampai pengelolaan stok pernah dipegang oleh bagian pemasaran.

### Contoh 1: boning dan label

Boning dulu dicatat tangan di karton dan kertas. Setelah boning selesai, isi kertas dicetak labelnya satu per satu lewat aplikasi desain label dengan informasi seadanya dan tanpa konsistensi data. Setelah semua label tercetak, petugas membawanya ke gudang dan mencari karton daging yang cocok untuk ditempeli. Satu label salah tempel berarti stok dan harga ikut salah.

*Sekarang:* label dicetak langsung dari data boning yang sama, dengan nomor barcode yang konsisten, tanpa tahap tulis ulang.

### Contoh 2: tally barang

Tally dulu dilakukan manual. Satu orang menyebutkan nama barang dan beratnya, satu orang lain mengetik di Excel. Salah dengar, salah baca, dan salah ketik mudah terjadi, dan sulit dilacak sesudahnya.

*Sekarang:* tally memakai scanner. Barcode dibaca langsung, tanpa perantara lisan atau ketikan.

### Contoh 3: pembagian tugas

Tanpa pembagian yang jelas, tidak ada yang bisa ditanya ketika ada selisih, dan stok pernah dipegang oleh orang yang tugas utamanya menjual.

*Sekarang:* setiap perubahan tercatat siapa yang melakukannya dan kapan.

## Tujuan

Aplikasi ini dibuat oleh **Asep Saepullah** sebagai sarana untuk mempermudah proses kerja: merapikan data, mempercepat pengerjaan, mencegah data hilang, dan membuat setiap perubahan bisa ditelusuri. Ia alat bantu kerja dan tidak menggantikan pertimbangan orang yang menjalankannya.

## Cakupan

Penerimaan dan stok daging, boning dan pelabelan, penjualan dan pengiriman, faktur dan pembayaran, pengeluaran, serta opname stok. Daftar modul lengkap dan perubahannya ada di halaman Changelog.

## Pembuat dan perawatan

Dirancang, ditulis, dan dirawat oleh **Asep Saepullah**.

- **Sejak 2023:** pengembangan aplikasi ini dimulai pada tahun 2023.
- **November 2026:** versi yang berjalan sekarang adalah versi refactor, yaitu penulisan ulang dari dasar dengan struktur yang lebih rapi.
- Riwayat setiap perubahan di versi refactor, lengkap dengan tanggal dan penulisnya, tersimpan di repositori kode (git) dan tercatat sejak 28 Mei 2026.

## Prinsip pemakaian

- Data yang diinput mencerminkan kejadian sebenarnya. Aplikasi tidak dibuat untuk menyembunyikan atau mengubah catatan setelah kejadian.
- Dokumen yang sudah dihapus tidak dipulihkan. Kalau perlu lagi, dibuat dokumen baru, dan nomor lama dibiarkan melompat. Jejaknya tetap ada di log aktivitas.
- Setiap perubahan penting tercatat di log aktivitas (siapa, apa, kapan).

## Asal-usul dan ketentuan

- **Ide dan pembuatan.** Aplikasi ini murni ide Asep Saepullah, dibuat untuk kemudahan kerja. Tidak ada permintaan khusus dari pihak perusahaan untuk membuatnya.
- **Penciptaan.** Seluruh kode program, logika, dan rancangan antarmuka aplikasi ini dirancang dan ditulis oleh Asep Saepullah. Riwayat perubahannya tersimpan di repositori kode (git).
- **Pembiayaan.** Perusahaan tidak membiayai pembuatan aplikasi ini. Perusahaan menanggung biaya operasional berupa sewa domain, VPS, dan cadangan harian, kurang lebih Rp7 juta per tahun, dibayar sekitar bulan Juni.
- **Data.** Data usaha yang tersimpan di dalam aplikasi adalah milik perusahaan.
- **Perjanjian.** Sampai catatan ini ditulis, belum ada perjanjian tertulis antara pembuat dan perusahaan mengenai aplikasi ini.
- **Penghentian.** Apabila perusahaan menilai aplikasi ini tidak relevan atau mengganggu kelancaran perusahaan, perusahaan dapat menyampaikan permintaan penghentian. Pembuat bersedia membicarakan masa transisi dan ekspor data.

Catatan ini menjelaskan latar belakang, tujuan, dan asal-usul aplikasi. Ia tidak menetapkan kepemilikan hak atas aplikasi dan tidak menggantikan perjanjian tertulis apa pun.
