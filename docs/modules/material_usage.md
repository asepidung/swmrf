# UI/UX & Logic Documentation: Material Usage

## 1. Ikhtisar Modul
Modul **Material Usage** berfungsi sebagai buku besar (Ledger) pencatatan stok keluar untuk seluruh material (*packaging*, kardus, plastik, label, dll) dari berbagai macam jenis transaksi hulu seperti *Boning*, *Repack*, dan proses lainnya.

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Penyembunyian Field Nomor Dokumen**:
   - Di form `Create Manual Usage`, *field* **Document No.** (Nomor Dokumen) telah disembunyikan menggunakan komponen `Hidden`. 
   - Sistem kini otomatis men-*generate* nomor (seperti `MA#26001`) di latar belakang (secara siluman) untuk meminimalisir intervensi manual dan mempercepat laju entri data.
2. **Master-Detail View (Custom Database View)**:
   - Modul ini menggunakan sumber data berupa *Database View* kustom (`material_usage_headers`) yang merekap total pemakaian berdasarkan tipe proses referensinya.
   - Telah ditambahkan halaman Detail khusus (`ViewMaterialUsage.php`) yang dirancang rapi untuk menampilkan seluruh rincian pemakaian material di dalam tabel terpisah saat baris data utama diklik.
3. **Halaman Transaksi Penuh (Detail List)**:
   - Sebuah halaman rekapitulasi data datar (*flat list*) bernama **Detail List** telah ditambahkan di samping tombol aksi utama pada halaman *Index*.
   - Halaman ini memungkinkan *user* untuk meninjau secara mendalam (*drill-down*) setiap satuan *item* material yang keluar lintas transaksi dalam bentuk tabel datar.
4. **Bilingual Support (Translasi)**:
   - Seluruh teks label, tabel, notifikasi, menu navigasi, hingga judul form pada ruang lingkup *Material Usage* telah dibalut dengan instruksi lokalisasi standar `__()` untuk menyelaraskan dengan konfigurasi multibahasa aplikasi.
