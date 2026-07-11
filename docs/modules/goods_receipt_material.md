# UI/UX & Logic Documentation: Goods Receipt Material

## 1. Ikhtisar Modul
Modul **Goods Receipt Material** (Penerimaan Material/Barang Umum) menangani pencatatan penerimaan barang non-daging berdasarkan Purchase Order (PO) Material. Modul ini terintegrasi dengan data inventaris dan pencatatan hutang (Account Payable).

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Fitur Cancel di Halaman Drafts (Pending PO)**:
   - Ditambahkan tombol **Cancel PO** (ikon `heroicon-o-x-mark` berwarna merah) di halaman *Drafts* (Daftar PO Pending).
   - Pengguna sekarang bisa langsung membatalkan PO Material langsung dari halaman *Drafts* jika diperlukan, lengkap dengan modal konfirmasi keselamatan untuk mencegah salah klik.
2. **Penyederhanaan Header Actions & Form**:
   - Seluruh *Header Actions* (seperti Cancel dan Print) telah dirapikan agar fungsional dan intuitif, lengkap dengan *auto-redirect* kembali ke *Index* setelah suatu aksi berhasil dilakukan (seperti Save atau Delete).
3. **Bilingual Support (Translasi)**:
   - Seperti halnya pada penerimaan daging (Produk), seluruh teks statis/hardcoded di form *Input*, notifikasi *Toast*, nama kolom, sampai teks *summary* kalkulasi pajak telah diintegrasikan dengan fungsi lokalisasi `__()`.
   - Modul ini siap diterjemahkan dengan mulus (*Seamless English / Indonesian Localization*).
4. **Perbaikan Rute (Routing Collision Fix)**:
   - Memastikan urutan pendaftaran rute di `GoodsReceiptMaterialResource::getPages()` sudah tepat di mana halaman statis seperti `detail-list` berada di atas rute dengan wildcard parameter `/{record}` untuk mencegah *Error 404 Not Found*.
