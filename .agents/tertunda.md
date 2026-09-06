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
| **Pemindahan 417 baris BOM legacy** | Modulnya sudah ada (#344), isinya belum. Menuntut pemetaan produk dan bahan antara dua master yang berbeda, dan `basis` tiap baris harus ditentukan -- legacy tidak menyimpannya, jadi tidak bisa disalin begitu saja |

---

## C. Menunggu modul QC

| Yang tertunda | Kenapa |
|---|---|
| Mutu barang retur | Owner: "nanti kita ada modul qc kok". Barang retur sekarang langsung siap dijual lagi; di lapangan sudah ada penanganannya sendiri (biasanya lewat repack) |
| **QC / QA Monitoring Produksi** | Belum ada modulnya. pH dan Grade menempel di dokumen lain, tanpa tempat yang menyatakan lulus atau tidaknya suatu batch. Kompensasi pemasok di Payable dicatat tanpa dokumen pemeriksaan yang mendasarinya |

### Bahan yang sudah ditelusuri untuk QC

Owner, 6 September 2026, saat modulnya mulai dibicarakan: "nanti aja nunggu
instruksi dari gw bro, soalnya modulnya ada banyak nih" dan "ini nanti kita
tentukan di saat pembuatan". **Rancangannya ditentukan Owner, bukan ditebak
di sini.**

Yang sudah dipetakan supaya tidak digali ulang saat modulnya mulai:

| Yang sudah ada | Keadaannya |
|---|---|
| **`ph_level`** | Ada di DELAPAN tabel: `boning_items`, `tally_items`, `beef_stocks`, `mutation_items`, `repack_materials`, `repack_results`, `stock_take_items`, `sales_return_items`, `goods_receipt_product_items`. **Tidak divalidasi sama sekali** -- angka apa pun diterima, dan tidak ada satu pun yang menyimpulkan lulus atau tidak. Nilainya ikut masuk ke barcode 26 karakter (dua digit, pH x 10) |
| **`grade_id`** | CHILL, FROZEN, A, B, R. Ini KONDISI SIMPAN, bukan hasil pemeriksaan -- dan umur simpannya diatur `App\Support\ShelfLife` |
| **Kompensasi pemasok** | `Payable::applyCompensation()`. Alasannya SELALU mutu ("lemaknya terlalu banyak, hasil dagingnya sedikit" -- Owner), tetapi tidak ada dokumen pemeriksaan yang mendasarinya. Catatan penting di sana: kompensasi TIDAK PERNAH menyentuh kerugian susut, dan pembedaan berat-vs-mutu sudah pernah dipasang lalu dibatalkan |
| **Suhu** | Tidak ada kolomnya di mana pun |
| **Foto / lampiran** | Tidak ada penyimpanan berkas untuk dokumen mana pun |

---

## D. Menunggu keputusan Owner

| Yang tertunda | Keadaannya sekarang |
|---|---|
| **Tanggal dokumen vs waktu input** — **INGATKAN BEGITU SELURUH MODUL SETTLE, SEBELUM LIVE** | Permintaan Owner 6 Sep: "kerjain tapi ingetin pas modul settle ya". **Sisa pekerjaannya:** kolom `transaction_date` di kedua tabel pergerakan — 25 titik tulis untuk daging, 1 untuk material. Ditunda supaya tidak dibayar dua kali selagi modul lain masih berubah. Ruang lingkupnya dibatasi satu hal: tabel `tallies` tidak punya kolom tanggal sendiri, padahal tally pintu masuk utama daging ke stok — jadi untuk sumber terbesarnya tanggal dokumen memang sama dengan waktu input. Ide soft delete `beef_stocks` sudah dipertimbangkan dan ditolak; alasannya di `agents.md` #323 |
| **Penjaga barcode di DO receipt** | Owner, 6 Sep: "pass deh sementara gak bisa dikerjain sekarang". **Sisa pekerjaannya:** memutuskan aturannya dibuang atau dipertahankan, sesudah Owner mengujinya sendiri. **Yang sudah ditelusuri, supaya tidak diulang:** penjaganya pertanyaan KEDUA dari empat di `InputReturnItems.php` -- barang yang surat jalannya belum punya bukti terima ditolak diretur, dengan alasan itu TOLAKAN dan pintunya di halaman Approve DO (di sana tally item dihapus, stok kembali, bukti terima lahir sudah berkurang, invoice ikut berkurang). **Catatan lama menyebut "diperluas ke tab Relabel" dan itu KELIRU:** Relabel bukan tab di halaman retur, melainkan action di Scan Tally untuk mengganti POD barang yang kelewat umur dan mencetak label baru berprefiks `6`. Luruskan dulu maksudnya sebelum aturannya disentuh, kalau tidak yang dikerjakan penjagaan untuk layar yang tidak ada hubungannya |
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
| **View tabel Stock Overview masih fork Filament** | **Sisa pekerjaannya: tidak ada yang bisa dikerjakan lagi.** Tinggal 105 baris, dan seluruhnya satu hal: baris kategori yang mencetak angka ringkasan DI DALAM dirinya. Filament v3 merender header grup sebagai satu sel membentang, jadi tidak ada tempat menaruh angka per kolom -- yang disediakannya baris ringkasan TERSENDIRI. Owner memutuskan baris kategori tetap SATU baris (#330). Ketertinggalannya dijaga `ForkedTableViewTest` |
| **Kedua `stock:reconcile` belum diuji di data tebal** | **Sisa pekerjaannya:** jalankan lagi setelah dipakai beberapa minggu. Sisi daging 5 Sep dan sisi material 6 Sep dua-duanya bersih, tetapi bahannya masih puluhan baris — buku besar sekecil itu memang selalu cocok. Sisi material menemukan 3 baris saldo minus lama (KERTAS HVS, 31 Agu & 1 Sep), semuanya sebelum penolakan stok minus dipasang |

---

## G. Izin yang menunggu dicentang Owner

Dibuat lewat migrasi dan sudah ada di sistem, tetapi belum dilekatkan ke
siapa pun. Selama belum dicentang, hanya akun programmer yang bisa memakainya.

`approve_sales_returns` · `unlock_sales_returns` · `set_repack_yield_limit` ·
`override_repack_yield` · `record_found_items` · `cancel_receivable_payments` ·
`record_payable_compensations` · `delete_beef_stocks` · `finish_stock_takes` ·
`finish_material_stock_takes` · `record_material_findings` ·
`view_deleted_sales_returns` · `view_deleted_material_stock_takes` ·
`pay_purchase_materials` · `view_deleted_repacks` ·
`view_qc_reports` · `create_qc_reports` · `edit_qc_reports` ·
`delete_qc_reports` · `view_deleted_qc_reports` ·
`view_product_materials` · `create_product_materials` ·
`edit_product_materials` · `delete_product_materials`
