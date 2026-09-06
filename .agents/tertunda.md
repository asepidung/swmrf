# Pekerjaan yang ditunda

Daftar pekerjaan yang **sengaja belum dikerjakan**, beserta alasannya dan apa
yang harus ada lebih dulu supaya bisa dikerjakan.

Berkas ini bukan daftar bug. Bug dikerjakan saat ditemukan. Yang di sini
adalah pekerjaan yang menunggu keputusan Owner, menunggu modul lain, atau
sengaja diputuskan tidak dikerjakan.

Dibuat 6 September 2026, atas permintaan Owner.

---

## A. Menunggu HPP

Owner meminta hal-hal berikut TIDAK diingatkan sampai ia sendiri yang
membukanya kembali. Dicatat di sini supaya tidak hilang, bukan untuk ditagih.

| Yang tertunda | Kenapa |
|---|---|
| Nilai rupiah barang retur | Butuh HPP untuk menilai barang yang kembali. Sekarang tercatat Rp 0 |
| Nilai kerugian susut kirim | `financial_losses` menyimpan KILOGRAM-nya (sejak 4 Sep), rupiahnya nol. Menilainya dengan harga jual melebih-lebihkan: yang hilang modal ditambah margin yang tidak jadi didapat, bukan harga jualnya. Saat HPP ada, rupiahnya tinggal `quantity x HPP` |
| Nilai kerugian susut timbang sapi | Sama; sumbernya `CattleWeighing` |
| **Killing Lost** (Kerugian Potong) | Modulnya belum ada, dan tidak ada satu pun kode yang menulisnya |
| **Lost Cost** (Biaya/Kerugian Lain) | Modulnya belum ada. `FinancialLossResource::canCreate()` mengembalikan `false` dan tidak punya halaman Create, jadi tidak bisa diinput manual |

`FinancialLoss::isNotPricedYet()` sudah membedakan nol yang berarti "belum
dinilai" dari nol yang berarti "memang tidak rugi".

---

## B. Menunggu BOM

| Yang tertunda | Kenapa |
|---|---|
| **Material Usage** (Pemakaian Bahan Penolong) | Owner: "material usage nanti aja kaitannya sama BOM". Belum disisir |
| **B.O.M** sendiri | Belum ada modulnya. Tidak ada yang menyatakan satu produk butuh bahan apa dan berapa; pemakaian bahan dicatat manual tanpa pembanding, HPP tidak bisa utuh, dan kebutuhan bahan tidak bisa diperkirakan di muka. Menyentuh Boning, Repack, Material Usage, dan HPP sekaligus |

---

## C. Menunggu modul QC

| Yang tertunda | Kenapa |
|---|---|
| Mutu barang retur | Owner: "nanti kita ada modul qc kok". Barang retur sekarang langsung siap dijual lagi; di lapangan sudah ada penanganannya sendiri (biasanya lewat repack) |
| **QC / QA Monitoring Produksi** | Belum ada modulnya. pH dan Grade menempel di dokumen lain, tanpa tempat yang menyatakan lulus atau tidaknya suatu batch. Kompensasi pemasok di Payable dicatat tanpa dokumen pemeriksaan yang mendasarinya |

---

## D. Menunggu keputusan Owner

| Yang tertunda | Keadaannya sekarang |
|---|---|
| **Tanggal dokumen vs waktu input** | Owner: "kita bahas nanti deh". `beef_stock_movements` hanya punya `created_at`, jadi posisi stok tanggal mundur mengikuti WAKTU INPUT. Untuk mengikuti tanggal dokumen dibutuhkan kolom tanggal transaksi baru, dan itu hanya berlaku maju -- baris lama tidak menyimpannya |
| **Penjaga barcode di DO receipt** | Owner: "biarin gitu dulu nanti mau gw uji sendiri, karena aturan itu sebenarnya belum diperlukan untuk ada". Barcode dari surat jalan yang belum ada bukti terimanya ditolak. Hasil ujinya menentukan apakah aturannya dibuang atau justru diperluas ke tab Relabel |
| **Repack: penataan halaman** | Halaman Input Bahan dan Input Hasil belum ditata ulang. Logikanya sudah selesai |
| **Dua belas centang Hak Akses yang tidak berakibat apa-apa** | Ada di form, bisa diberikan, tidak dibaca satu baris kode pun. Empat karena stok dan pergerakannya memang tidak memakai hapus lunak (`view_deleted_beef_stocks`, `view_deleted_beef_stock_movements`, `view_deleted_material_stocks`, `view_deleted_material_stock_movements`); dua karena layarnya berdiri di atas CustomerGroup, bukan di atas dokumen yang namanya disebut (`view_deleted_price_lists`, `view_deleted_receivables`); lima karena layar Material Adjustment tidak pernah dibuat meski tabel dan modelnya ada (`view_material_adjustments`, `create_material_adjustments`, `edit_material_adjustments`, `delete_material_adjustments`, `view_deleted_material_adjustments`); satu sengaja dipertahankan dengan alasan tertulis (`delete_users`). **Pertanyaannya: dibuang dari form, atau dibiarkan?** Membuang baris izin ikut memutus lekatannya ke pengguna yang telanjur dicentang, jadi ini keputusan Owner. Sementara ini dijaga oleh daftar beralasan di `DeletedRecordVisibilityTest` supaya tidak bertambah diam-diam |
| **Qty permintaan pembelian DAGING: berdesimal atau bulat?** | Kolomnya `decimal(15,2)` dan kotak isiannya menerima desimal (`inputmode: decimal`), tetapi LAYAR daftar rinci dan BERKAS CETAKNYA sama-sama membulatkannya ke bilangan bulat. Jadi 12,50 Kg tersimpan 12,50 tetapi terbaca 13 di dua tempat, tanpa ada yang memberitahu. Sisi material sengaja bulat -- keputusan Owner 5 September: "material itu gak ada qty koma-komaan". Untuk daging belum pernah ditanyakan, dan belum ada satu baris pun datanya untuk ditebak dari situ. **Kalau daging dibeli per kilogram berkoma, yang salah tampilannya; kalau dibeli bulat, yang salah tipe kolomnya.** Dua-duanya perbaikan sebaris, hanya arahnya yang menunggu jawaban |

---

## E. Diputuskan TIDAK dikerjakan

Ini bukan tunggakan. Ini keputusan, dan tidak perlu ditinjau ulang kecuali
Owner memintanya.

| Keputusan | Alasan |
|---|---|
| Dokumen cetak dan ekspor PDF tetap berbahasa Indonesia | 122 baris di 63 berkas. Invoice dan surat jalan pergi ke pelanggan Indonesia; bahasa sebuah dokumen ditentukan oleh siapa yang membacanya, bukan oleh setelan operator yang menekan tombol cetak |
| Teks yang DITULIS KE BASIS DATA tetap Indonesia | Catatan pergerakan stok, jejak audit, alasan pembatalan. Menerjemahkannya saat ditulis membuat satu kolom memuat dua bahasa bercampur selamanya. Itu catatan, bukan antarmuka |
| Perintah artisan dan baris log tetap Indonesia | Pembacanya yang merawat sistem, bukan pengguna aplikasi |
| Susut boning tidak dihitung | Kulit dan offal ikut menjadi label di dalam boning, jadi hasilnya memuat barang yang bukan berasal dari karkasnya |
| Kirim/terima mutasi tidak diberi izin tersendiri | Keputusan Owner: dipakai harian, dibiarkan menumpang akses halamannya |
| Pengguna dinonaktifkan, tidak dihapus | Keputusan Owner: "user mah jangan ada hapus aktif non aktif aja" |

---

## F. Utang teknis yang terlihat

Bukan menunggu apa pun -- hanya belum dikerjakan, dan besarnya diketahui.

| Utang | Ukurannya |
|---|---|
| **View tabel Stock Overview adalah fork Filament** | `beef-stock/table.blade.php`, 1449 baris, meleset 163 baris dari aslinya. Upgrade Filament tidak akan menyentuhnya. Sudah sekali meledak: `x-filament-tables::header` dipanggil tanpa `:actions-position`, dan bug itu tidur sejak fork dibuat sampai tabelnya diberi description (#279) |
| **`stock:reconcile` belum diuji di data tebal** | Saat dijalankan 5 Sep hasilnya bersih, tetapi hanya 32 baris pergerakan dari 5 hari dan 2 kombinasi. Buku besar dengan 32 baris memang selalu cocok. Jalankan lagi setelah dipakai beberapa minggu |
| **`auth()->id() ?? 1` di tiga belas tempat** | **Wajib beres SEBELUM data lama dipindahkan.** Hari ini id 1 tidak ada -- pengguna paling awal id 100, dan itu permintaan Owner justru supaya id 1 dst. bisa dipakai user warisan nanti. Jadi sekarang fallback itu menunjuk pengguna yang tidak ada: kolom ber-foreign-key akan menolak dengan galat SQL, yang tanpa FK menyimpan id nyangkut. Begitu data lama masuk, id 1 menjadi ORANG SUNGGUHAN, dan kegagalan yang tadinya keras berubah menjadi diam: tindakan tercatat atas nama orang lain, tanpa gejala apa pun. Dari delapan kolom yang diperiksa, lima sudah nullable; tiga belum (`material_findings.created_by`, `purchase_materials.approved_by`, `beef_stock_movements.created_by`) |
| **Laporan yang belum ada** | Fast Moving Products, Sales Report, Laporan Stock Gudang |

---

## G. Izin yang menunggu dicentang Owner

Dibuat lewat migrasi dan sudah ada di sistem, tetapi belum dilekatkan ke
siapa pun. Selama belum dicentang, hanya akun programmer yang bisa memakainya.

`approve_sales_returns` · `unlock_sales_returns` · `set_repack_yield_limit` ·
`override_repack_yield` · `record_found_items` · `cancel_receivable_payments` ·
`record_payable_compensations` · `delete_beef_stocks` · `finish_stock_takes` ·
`finish_material_stock_takes` · `record_material_findings` ·
`view_deleted_sales_returns` · `view_deleted_material_stock_takes` ·
`pay_purchase_materials` · `view_deleted_repacks`
