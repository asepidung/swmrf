# UI/UX & Logic Documentation: Goods Receipt Product (Beef Receipt)

## 1. Ikhtisar Modul
Modul **Goods Receipt Product** (Penerimaan Barang - Daging/Produk) menangani pencatatan penerimaan fisik daging berdasarkan Purchase Order (PO). Modul ini terhubung langsung ke inventaris, proses pelabelan, dan barcode scanning.

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Penyederhanaan Header Actions (Input GR)**:
   - Tombol-tombol aksi utama seperti **Scan** dan **Label** telah dikeluarkan dari halaman form *Input GR* untuk meminimalisir penumpukan tombol (*clutter*) di *header*.
   - Sebagai gantinya, tombol **Back** ditambahkan agar *user* bisa kembali ke *Index* dengan mudah.
   - Saat pengguna mengunci dokumen (menekan tombol **Lock**), sistem tidak lagi me-*reload* halaman input yang sama, melainkan langsung melakukan *redirect* kembali ke halaman *Index*.
2. **ActionGroup di Halaman Index**:
   - Untuk mengompensasi penghapusan tombol Scan dan Label dari halaman Input, fungsi-fungsi ini dipindahkan ke *Table Actions* di halaman *Index* menggunakan antarmuka `ActionGroup` berikon tiga titik vertikal (`heroicon-m-ellipsis-vertical`). Hal ini membuat form aksi menjadi dinamis dan *dropdown* tanpa mengorbankan ruang layar (*clean table UI* sesuai Aturan ke-46).
3. **Bilingual Support (Translasi)**:
   - Seluruh teks *hardcoded* mulai dari nama kolom (*Product*, *Weight*, *Grade*), *header* form (*Catatan*, *Supplier Name*), notifikasi *Toast*, hingga deskripsi pengunci (*Lock* modal) telah disisir dan dibungkus menggunakan *helper* lokalisasi `__()`. Sistem ini siap diterjemahkan (*Ready for Indonesian translation* di `id.json`).
