# Modul Boning

Modul **Boning** berfungsi untuk mencatat proses deboning (pemisahan daging dari tulang karkas) menjadi berbagai potongan daging spesifik (*Boning Items*) sekaligus mencatat pemakaian bahan pendukung tambahan (*Material Usage*). Modul ini mengintegrasikan output dari lini pemotongan karkas langsung ke dalam stok inventaris produk akhir.

## 1. Arsitektur & Relasi Database
Modul ini bertumpu pada model utama `Boning` yang menjembatani beberapa entitas:
- **Carcasses** (`BelongsToMany`): Karkas-karkas yang menjadi bahan baku dalam sesi boning ini.
- **BoningItems** (`HasMany`): Rincian produk daging hasil akhir dari proses deboning.
- **MaterialUsages** (`HasMany`): Rincian material pendukung (seperti plastik, tray) yang dikonsumsi selama sesi ini.
- **Creator** (`BelongsTo`): Pengguna yang mencatat dokumen boning.

## 2. Alur Logika (Business Logic)
1. **Pemilihan Bahan Baku**: Dokumen Boning dibuat dengan merelasikan satu atau lebih karkas yang statusnya masih tersedia. Begitu karkas dipilih dan dokumen disimpan, karkas tersebut akan dikunci oleh sistem sehingga tidak dapat dihapus atau dimodifikasi dari modul asalnya (Carcass).
2. **Pencatatan Hasil (Yield)**: Pekerja memotong karkas dan mengkategorikannya menjadi produk akhir. Sistem mencatat hasil potongan (nama produk, berat, kuantitas) ke dalam tabel `boning_items`. Hasil ini nantinya akan bermutasi masuk ke dalam stok gudang (Inventory).
3. **Pencatatan Material**: Selain daging, proses pengemasan awal membutuhkan material pendukung. Tabel `material_usages` mencatat jumlah material (berdasarkan BOM/kebutuhan aktual) yang nantinya akan mengurangi stok *Materials*.

## 3. UI/UX (Antarmuka Pengguna)
- **Desain Header Kompak**: Pada halaman *View*, informasi karkas yang diproses ditampilkan secara padat dalam satu baris (memanfaatkan `columnSpan`) menggunakan desain *Pill Badge* dinamis. Hal ini menjaga agar area *scroll* tidak terlalu panjang.
- **Form Ergonomis dengan Proteksi Input**:
  - Saat mencatat *Material Usage* melalui *Repeater*, sistem mengimplementasikan logika `disableOptionsWhenSelectedInSiblingRepeaterItems()`. Hal ini mencegah *user* secara tidak sengaja memilih material yang sama pada baris yang berbeda.
- **Bilingual & Navigasi**:
  - Modul ini mendukung translasi dua bahasa (Inggris/Indonesia).
  - Dilengkapi tombol *Back* pada halaman detail untuk mempercepat navigasi kembali ke tabel utama (Index).
- **Integritas Relasional**: Sistem menggunakan prinsip "Soft-Lock". Jika sebuah dokumen Karkas sedang diproses di modul ini, tombol *Delete* pada modul Karkas secara otomatis disembunyikan untuk mencegah *human error* yang merusak integritas *database*.
