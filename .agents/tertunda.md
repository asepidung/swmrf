# Pekerjaan yang ditunda

Daftar pekerjaan yang **sengaja belum dikerjakan**, beserta alasannya dan apa
yang harus ada lebih dulu supaya bisa dikerjakan.

Berkas ini bukan daftar bug. Bug dikerjakan saat ditemukan. Yang di sini
adalah pekerjaan yang menunggu keputusan Owner, menunggu modul lain, atau
sengaja diputuskan tidak dikerjakan.

Dibuat 6 September 2026, atas permintaan Owner.

**Tiap baris hanya memuat SISA pekerjaannya.** Yang sudah dikerjakan dicabut
dari sini dan riwayatnya tinggal di `agents.md` -- daftar tunggu yang memuat
catatan kemajuan berhenti bisa dibaca sekilas, dan itu satu-satunya gunanya.

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
| **Tanggal dokumen vs waktu input** — **INGATKAN BEGITU SELURUH MODUL SETTLE, SEBELUM LIVE** | Permintaan Owner 6 Sep: "kerjain tapi ingetin pas modul settle ya". **Sisa pekerjaannya:** kolom `transaction_date` di kedua tabel pergerakan — 25 titik tulis untuk daging, 1 untuk material. Ditunda supaya tidak dibayar dua kali selagi modul lain masih berubah. Ruang lingkupnya dibatasi satu hal: tabel `tallies` tidak punya kolom tanggal sendiri, padahal tally pintu masuk utama daging ke stok — jadi untuk sumber terbesarnya tanggal dokumen memang sama dengan waktu input. Ide soft delete `beef_stocks` sudah dipertimbangkan dan ditolak; alasannya di `agents.md` #323 |
| **Penjaga barcode di DO receipt** | Owner: "biarin gitu dulu nanti mau gw uji sendiri, karena aturan itu sebenarnya belum diperlukan untuk ada". Barcode dari surat jalan yang belum ada bukti terimanya ditolak. Hasil ujinya menentukan apakah aturannya dibuang atau justru diperluas ke tab Relabel |
| **Repack: penataan halaman** | Halaman Input Bahan dan Input Hasil belum ditata ulang. Logikanya sudah selesai |

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
| **View tabel Stock Overview masih fork Filament** | **Sisa pekerjaannya:** lima penyimpangan struktur, 220 baris, didaftar di kepala berkasnya. Belum bisa hilang selama Filament v3 tidak punya cara mencetak angka ringkasan DI DALAM baris grup, dan itu inti tampilannya. Ketertinggalannya sudah dijaga `ForkedTableViewTest` (#312), jadi ini bukan lagi risiko senyap — hanya pekerjaan yang belum selesai |
| **Kedua `stock:reconcile` belum diuji di data tebal** | **Sisa pekerjaannya:** jalankan lagi setelah dipakai beberapa minggu. Sisi daging 5 Sep dan sisi material 6 Sep dua-duanya bersih, tetapi bahannya masih puluhan baris — buku besar sekecil itu memang selalu cocok. Sisi material menemukan 3 baris saldo minus lama (KERTAS HVS, 31 Agu & 1 Sep), semuanya sebelum penolakan stok minus dipasang |
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
