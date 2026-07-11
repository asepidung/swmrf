# UI/UX & Logic Documentation: Purchase Order Cattle

## 1. Ikhtisar Modul
Modul **Purchase Order Cattle (PO Cattle)** digunakan untuk mendata pembelian sapi hidup dari *Supplier* berdasarkan kelas sapi (*Cattle Class*).

## 2. Peningkatan UI/UX Sesuai Guideline PROJECT.MD
1. **Pencegahan Item Duplikat**:
   - Di dalam halaman *Create/Edit*, form input menggunakan *Repeater*. Untuk mencegah *user* tidak sengaja memilih jenis kategori sapi (*Cattle Class*) yang sama berulang kali di baris yang berbeda, opsi pada *dropdown* telah dikunci secara dinamis menggunakan atribut `->disableOptionsWhenSelectedInSiblingRepeaterItems()`.
2. **Halaman Custom Detail List (Sesuai Aturan ke-34)**:
   - Telah ditambahkan halaman khusus **Detail List** (`PurchaseCattleDetailList`) yang memaparkan data anak (rincian sapi yang dibeli) dalam bentuk tabel datar (*Flat List*).
   - Halaman ini dilengkapi dengan fitur ekspor **Excel** dan **PDF** mandiri (tanpa menggunakan komponen *queue*).
   - Akses menuju halaman Detail List disematkan persis di sebelah kanan tombol "Create" pada halaman *Index*.
3. **Standarisasi Tombol Bawaan**:
   - Tombol aksi statis *View* dan *Edit* tidak ditampilkan langsung di dalam baris *table list* demi menghemat ruang (Aturan ke-46).
   - *User* hanya perlu mengklik baris data (*Clickable Rows*) untuk menuju halaman *Edit* (atau *View* jika data berstatus *soft-deleted*).

## 3. Fitur Ekspor
1. **PurchaseCattleItemExporter**: Untuk melakukan ekspor data spesifik rincian per-item sapi yang dibeli langsung ke format `.xlsx` dengan cepat.
2. **PDF View**: Rendering khusus menggunakan `dompdf` berbasis *blade view* `exports.purchase-cattle-details-pdf` untuk memfasilitasi cetak laporan resmi PO Cattle.
