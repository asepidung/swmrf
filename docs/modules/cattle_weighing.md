# UI/UX & Logic Documentation: Cattle Weighing

## 1. Ikhtisar Modul
Modul **Cattle Weighing** (Penimbangan Sapi) berfungsi untuk mencatat berat awal dan berat aktual sapi setelah tiba di fasilitas. Modul ini terintegrasi erat dengan modul penerimaan sapi (Cattle Receiving) dan karkas (Carcass). Selisih antara berat aktual dan berat awal dicatat sebagai penyusutan (*Shrinkage*).

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Validasi Karkas pada Form Edit (Read-only)**:
   - Apabila data sapi pada penimbangan ini sudah diproses dan masuk ke modul Karkas (`Carcass`), data *weighing* tidak lagi bisa dihapus. 
   - Tombol **Delete** dan **Force Delete** di halaman Edit akan dinonaktifkan (disabled).
   - Tombol **Save** akan dihilangkan (disembunyikan) agar form berubah menjadi mode *View-only* (hanya baca).
   - Pengguna akan menerima notifikasi peringatan (warna kuning/warning) saat membuka halaman Edit: "This Cattle Weighing has been processed into Carcass and is now read-only."
2. **Proteksi Level Model (Backend)**:
   - Mencegah penghapusan rekaman secara paksa (*hard-delete* atau *soft-delete*) dari fungsi kode maupun bulk delete, menggunakan `deleting` event pada model `CattleWeighing`. Apabila nekat, akan muncul exception.
3. **Penyempurnaan Istilah Industri pada Cetak PDF**:
   - Kolom yang sebelumnya bernama "Variance" di PDF cetak hasil penimbangan telah direvisi menjadi istilah standar industri, yaitu **"Shrinkage"** (Susut).
4. **Bilingual Support (Translasi)**:
   - Sama halnya dengan modul lain, seluruh teks statis/hardcoded di dalam modul ini telah dipasang pembungkus `__()` agar siap untuk lokalisasi dua bahasa (Indonesia-Inggris).
