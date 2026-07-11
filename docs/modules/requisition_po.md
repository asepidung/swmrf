# UI/UX & Logic Documentation: Requisition & Purchase Order Module

## 1. Ikhtisar Modul
Modul ini menangani alur permintaan (Requisition) untuk Material (Bahan Penolong) dan Product (Daging Sapi), serta turunannya yaitu pembuatan Purchase Order (PO). Alur ini melibatkan proses pengajuan, persetujuan oleh tim Finance, hingga pembuatan PO ke Supplier.

## 2. Struktur UI/UX Requisition Form
1. **Grid Layout**: 
   - Halaman *Create/Edit* menggunakan sistem Grid 12 kolom untuk *Repeater*. 
   - Proporsi kolom disesuaikan agar optimal: Material/Product (4 kolom), Qty (2 kolom), Price (2 kolom), Subtotal (disembunyikan saat input), Note (4 kolom).
   - Di halaman *View*, nama barang diciutkan menjadi 3 kolom untuk memberi ruang pada kolom *Subtotal* (2 kolom).
2. **Keyboard Navigation (Enter Key)**:
   - Pengguna dapat menggunakan tombol `Enter` pada form *Repeater* (di input Qty, Price, dan Note) untuk langsung melompat (*focus*) ke input baris di bawahnya pada kolom yang sama. Hal ini mempercepat proses *data entry*.
3. **Bypass Halaman Review**:
   - Tidak ada lagi halaman terpisah untuk *Review* atau *Approve Finance*.
   - Aksi *Review* dan *Finance Approval* dilakukan secara *inline* melalui **Pop-up Modal** pada halaman *View*.
4. **Toast Notification Tanpa Redirect**:
   - Setelah pengguna melakukan *Approve/Reject* di dalam Modal, modal akan menutup, status akan diperbarui seketika, dan notifikasi *Toast* akan langsung muncul.
   - Fitur *Redirect* (pindah halaman otomatis) sengaja dihilangkan untuk mencegah insiden hilangnya notifikasi *Toast* yang "dicuri" oleh *widget* Livewire lain yang sedang melakukan *polling* di *background* (*Session Stealing*).

## 3. Cetak PDF & Hitungan Pajak (Tax)
1. **Pemisahan Kolom Tax**:
   - Pada form cetak PDF (PDF Export menggunakan standard browser print/view), tabel *Subtotal* (sebelum pajak), *Tax 11%*, dan *Grand Total* ditampilkan secara terpisah dan eksplisit.
   - Rumus perhitungan diperbaiki: Nilai `total_amount` di database adalah representasi **Grand Total**. Sehingga, Subtotal dihitung mundur dengan cara `Total Amount - Tax Amount`.
2. **Purchase Order**:
   - Template PO disesuaikan agar mencantumkan rincian perpajakan (apabila vendor bersangkutan adalah *Taxable* / PKP).
   - *Header* pada kolom perhitungan rincian barang menggunakan label **TOTAL** (bukan *Subtotal*).
