# UI/UX & Logic Documentation: Boning

## 1. Ikhtisar Modul
Modul **Boning** berfungsi untuk mencatat proses pemisahan daging dari tulang (karkas) menjadi produk-produk daging (*Boning Items*) dan juga mencatat penggunaan material tambahan (*Material Usage*). Modul ini terintegrasi dengan modul Karkas dan manajemen produk (Inventaris).

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Pencegahan Pemilihan Material Ganda (Material Usage)**:
   - Pada form *Material Usages*, elemen *Select Material* telah diberikan perlindungan menggunakan `disableOptionsWhenSelectedInSiblingRepeaterItems()`.
   - Ini mencegah *user* untuk tidak sengaja memilih material yang sama lebih dari satu kali di baris yang berbeda.
2. **Tombol Navigasi di Halaman View**:
   - Di halaman *View Boning*, tombol-tombol pada *Header Actions* telah dirapikan. Label tombol telah dimunculkan secara utuh (tidak hanya *icon*).
   - Telah ditambahkan juga tombol **Back** agar pengguna dapat dengan mudah kembali ke halaman daftar utama (*Index*).
3. **Bilingual Support (Translasi)**:
   - Sama halnya dengan modul hulu (Cattle Receiving, Weighing, dan Carcass), seluruh teks statis (nama kolom, *label*, *placeholder*, pemberitahuan) pada form dan modal di modul Boning telah dibungkus menggunakan *helper* `__()` untuk mendukung lokalisasi (bilingual).
4. **Relasi Hilir dengan Karkas**:
   - Karkas yang telah diproses di form Boning ini akan secara otomatis terkunci dari fungsi penghapusan atau pengubahan paksa pada sisi hulu (modul Carcass) demi menjaga integritas data lintas modul.
