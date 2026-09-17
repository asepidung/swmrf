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
| **Klaim retur vs fisik** (13 Sep, ditunda Owner; **17 Sep Owner memutuskan bentuknya, lihat baris berikutnya**) | Pelanggan klaim 20 kg, fisik 18 kg, kebijakan: kredit ngikutin klaim, selisih kerugian. Rancangan sudah disepakati lalu ditunda: (1) sales membuat retur + **klaim per produk** dan memilih DO (atau mengosongkan bila tidak jelas), gudang hanya memindai; (2) kredit = klaim × harga invoice, dibatasi yang pernah ditagih; (3) selisih klaim−fisik → `FinancialLoss` sumber baru "Sales Return", **dinilai harga kredit bukan HPP** (uang yang benar-benar diberikan tanpa barang kembali); (4) klaim kosong → perilaku sekarang; klaim ada fisik nol → tidak boleh disetujui. **Tidak menabrak Repack**: barang retur masuk `BeefStock IN_STOCK` biasa, Repack memungutnya seperti stok lain; susut seset adalah urusan Repack, retur tidak boleh mencatatnya. Syarat lapangan: **gudang menimbang saat menerima retur, SEBELUM seset** -- kalau tidak, susut seset ikut dinilai harga jual. Ikut ditunda, tiga yang kecil: autofocus DO hanya di create; nilai uang Sales Return (tabel + cetakan) mengikuti `view_invoices`; badge + bagian "retur yang memotong" di layar Invoice |
| **Plan Sales Return -- modul baru milik sales** (keputusan Owner 17 Sep 2026; **dijadwalkan Sabtu 19 Sep 2026** bersama rombak Sales Return dan dua modul lain yang belum disebut Owner) | Retur dipecah dua dokumen. **(1) Plan Sales Return** di menu Sales: sales memilih customer, DO asal (atau *unidentified DO* bila tidak jelas), item yang diretur, dan **qty klaim** menurut informasi customer. **(2) Sales Return** (gudang): plan muncul sebagai draft, gudang menariknya dan mengisi penerimaan fisik -- per item ada **qty klaim** (dari plan) dan **qty diterima** (real). Selisih klaim vs fisik menjadi **Financial Loss** sumber "Sales Return". **Terpisah dari Repack** sepenuhnya. Kebijakan per pelanggan: untuk **LION** kadang qty klaim diterima apa adanya (kredit ikut klaim); untuk **customer lain** qty disesuaikan, bahkan sama dengan yang diterima (kredit ikut fisik). Rancangan 13 Sep (kredit = klaim x harga invoice; selisih dinilai harga kredit; gudang menimbang sebelum seset) tetap berlaku sebagai rincian. **Dijawab Owner 17 Sep:** (a) **satu aturan untuk semua pelanggan, tanpa setelan per grup**: kredit = qty klaim di plan, selisih klaim−fisik = Financial Loss (LION 100 kg, timbang 98 kg → 2 kg rugi). Kalau untuk pelanggan lain mau dinego jadi 98, caranya **ubah qty klaim di plan** — dari halaman plan, atau dari halaman penerimaan gudang yang boleh mengubah plan-nya. (b) **Retur tanpa plan TIDAK BOLEH** — aplikasi menolak input retur tanpa plan; aturannya ditegakkan Owner di perusahaan. (c) Izin baru `create_sales_return_plans` (dan view/edit/delete-nya) untuk sales, lewat migrasi |
| **Tanggal dokumen vs waktu input** — **INGATKAN BEGITU SELURUH MODUL SETTLE, SEBELUM LIVE** | Permintaan Owner 6 Sep: "kerjain tapi ingetin pas modul settle ya". **Sisa pekerjaannya:** kolom `transaction_date` di kedua tabel pergerakan — 25 titik tulis untuk daging, 1 untuk material. Ditunda supaya tidak dibayar dua kali selagi modul lain masih berubah. Ruang lingkupnya dibatasi satu hal: tabel `tallies` tidak punya kolom tanggal sendiri, padahal tally pintu masuk utama daging ke stok — jadi untuk sumber terbesarnya tanggal dokumen memang sama dengan waktu input. Ide soft delete `beef_stocks` sudah dipertimbangkan dan ditolak; alasannya di `agents.md` #323 |
| **Penjaga barcode di DO receipt** | Owner, 6 Sep: "pass deh sementara gak bisa dikerjain sekarang". **Sisa pekerjaannya:** memutuskan aturannya dibuang atau dipertahankan, sesudah Owner mengujinya sendiri. **Yang sudah ditelusuri, supaya tidak diulang:** penjaganya pertanyaan KEDUA dari empat di `InputReturnItems.php` -- barang yang surat jalannya belum punya bukti terima ditolak diretur, dengan alasan itu TOLAKAN dan pintunya di halaman Approve DO (di sana tally item dihapus, stok kembali, bukti terima lahir sudah berkurang, invoice ikut berkurang). **Catatan lama menyebut "diperluas ke tab Relabel" dan itu KELIRU:** Relabel bukan tab di halaman retur, melainkan action di Scan Tally untuk mengganti POD barang yang kelewat umur dan mencetak label baru berprefiks `6`. Luruskan dulu maksudnya sebelum aturannya disentuh, kalau tidak yang dikerjakan penjagaan untuk layar yang tidak ada hubungannya |
| **Repack: penataan halaman** | Halaman Input Bahan dan Input Hasil belum ditata ulang. Logikanya sudah selesai |
| **Boning: batasan kapan `unlock()` ditolak** | Owner, 13 Sep 2026: pola yang dipakai untuk `Repack::unlock()`/`SalesReturn::unlock()` (menolak kalau barcode hasilnya sudah tidak `IN_STOCK` di `beef_stocks`) **tidak berlaku untuk Boning** -- barangnya lazim sudah banyak keluar gudang bahkan SEBELUM boningnya di-unlock, jadi syarat itu akan membuat Boning nyaris tidak pernah bisa dibuka lagi. "Kalau memang mau diberikan batasan harus cari parameter lain" (Owner). **Sisa pekerjaannya:** cari parameter yang benar-benar mencerminkan kapan sebuah Boning sudah tidak aman diedit ulang -- BUKAN sekadar meniru pola stok Repack/SalesReturn. Sengaja ditunda, `Boning::unlock()` TIDAK disentuh sampai ada pendekatan itu |

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
| **Glitch 'arrowdown' pada Select Filament** | **Ditunda (9 Sep 2026):** Saat Dropdown (terutama yang Searchable) difokuskan dan ditekankan panah bawah di keyboard, kadang muncul teks "arrowdown" di daftar opsi. Sudah disisir, tidak ada file/DB yang memuat *string* "arrowdown" secara statis. Kemungkinan besar disebabkan oleh intercept/bug di Alpine.js/Choices.js bawaan Filament v3 dengan browser atau extension macro tertentu. Diputuskan ditunda. |
| **Delivery Plan tanpa kunci status** | Susulan 17 Sep 2026, batch 6: driver/vehicle/load_time masih bisa diubah bebas setelah pengiriman selesai (semua Sales Order-nya `completed`/`cancelled`). Tidak ditandai [A] karena tidak ada angka finansial maupun dokumen kepatuhan yang bergantung padanya (beda dari Cattle Weighing/Receiving) -- keputusan Owner, bukan bug |

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
`edit_product_materials` · `delete_product_materials` ·
`view_drivers` · `create_drivers` · `edit_drivers` · `delete_drivers` ·
`view_vehicles` · `create_vehicles` · `edit_vehicles` · `delete_vehicles` ·
`manage_user_permissions`

### Susulan sapuan 14-17 September 2026 (PR #390-#449)

Sama seperti daftar di atas -- sudah berlaku lewat kode/migrasi, belum
tentu ada yang mencentangnya. Bedanya: baris-baris ini bukan izin yang
lahir tanpa halaman (seperti QC/Fleet di atas, yang memang modul baru),
melainkan izin untuk halaman/aksi yang SUDAH dipakai staf setiap hari dan
SEBELUMNYA sama sekali tidak dijaga. Begitu PR-nya naik ke produksi, staf
yang biasa memakai fiturnya bisa mendadak ditolak kalau izinnya belum
pernah dicentang untuk peran mereka -- baris ini disusun supaya Ayah bisa
memeriksanya sekali jalan, modul demi modul, bukan menunggu staf lapor
satu per satu.

**Izin yang benar-benar baru** (belum pernah ada baris permission-nya di
produksi -- kode/seeder yang menyebutnya sudah ada, tetapi baris izinnya
sendiri baru dibuat migrasi PR #406):

`view_customers` · `create_customers` · `edit_customers` · `delete_customers` ·
`view_customer_groups` · `create_customer_groups` · `edit_customer_groups` ·
`delete_customer_groups` · `view_customer_segments` ·
`create_customer_segments` · `edit_customer_segments` ·
`delete_customer_segments` · `view_receivables`

**Izin yang sudah ada tetapi BARU DITEGAKKAN** di halaman/aksi yang
sebelumnya sama sekali tidak dijaga -- periksa peran yang memakai modul
ini setiap hari sudah punya izinnya:

| Modul | Izin | Yang sekarang dijaga (PR) |
|---|---|---|
| User | `view_users` | Menu User sebelumnya tampil untuk siapa pun yang login (#394) |
| Delivery Order | `approve_delivery_orders` | Aksi Approve DO itu sendiri, sebelumnya cuma tombolnya yang disembunyikan (#415) |
| Invoice | `tukar_faktur` | Aksi tukar faktur itu sendiri (#416) |
| Tally | `create_tallies`, `edit_tallies` | Tombol Create Tally, aksi hapus Draft Tally, DAN metode scan ScanTally -- sebelumnya cukup `view_tallies` untuk memakai stok lewat scan (#424) |
| Boning | `edit_bonings`, `delete_bonings` | `canAccess()` LabelingBoning, aksi hapus tabel Boning (#425) |
| Mutation | `edit_mutations`, `view_mutations` | Metode scan ScanMutation, aksi hapus item, route cetak (#426) |
| Repack | `edit_repacks`, `view_repacks` | `canAccess()` Input Bahan/Hasil, route label &amp; ringkasan cetak (#427) |
| Stock Take (daging) | `edit_stock_takes` | `canAccess()` ScanStockTake, aksi hapus temuan -- sebelumnya TANPA izin sama sekali (#429) |
| GR Beef | `lock_goods_receipt_products`, `edit_goods_receipt_products`, `delete_goods_receipt_products` | Metode lock/save/hapus di halaman input, sebelumnya cuma tombolnya yang dijaga (#430) |
| GR Material | `create_gr_materials` | `canAccess()` Create, metode processSave/confirmPartial/forceCompleted (#431) |
| Material Stock Take | `edit_material_stock_takes` | Kolom hitung yang bisa diedit inline -- sebelumnya cukup `view_material_stock_takes` (#432) |
| Material Usage | `view_material_usages`, `create_material_usages`, `edit_material_usages`, `delete_material_usages` | Belum ada Policy sama sekali sebelumnya -- Filament meloloskan siapa pun yang login (#447) |
| Supplier | `view_suppliers` | Perlu diperiksa manual, kemungkinan sudah tercentang luas (#408) |

**Sistemik, bentuknya beda dari dua daftar di atas (#433):** bulk delete
lewat kotak centang tabel (`deleteAny`/`forceDeleteAny`/`restoreAny`) di
SELURUH ~50 Resource sebelumnya TIDAK DIJAGA IZIN APA PUN, dan sekarang
memakai izin `delete_...` yang SAMA dengan hapus satu baris. **Tidak perlu
izin baru** untuk staf yang sudah punya izin hapus satu barisnya -- tetapi
kalau ada staf yang sejauh ini bisa hapus massal tanpa punya izin hapus
satu baris (lewat celah yang baru ditutup ini), hapus massalnya berhenti
sampai izin `delete_...` modulnya dicentang untuk peran mereka. Modul yang
kena: Bank Account, Beef Stock, Boning, Carcass, Cattle Class, Cattle
Receiving, Cattle Weighing, Customer(+Group+Segment), Delivery Order,
Delivery Plan, Driver, GR Beef/Material, Grade, Invoice,
Material(+Requisition), Material Stock Take, Material Usage, Mutation,
Price List, Product(+Category+Material)+Requisition, Purchase Cattle, QC
Report, Repack, Sales Order, Sales Return, Stock Take, Supplier, Tally,
Vehicle, Warehouse.

**Tidak masuk daftar di atas, sengaja:** ~20 izin `view_...` yang mulai
dijaga di route cetak/ekspor (batch 7, #449 -- `view_purchase_cattles`,
`view_tallies`, `view_invoices`, dst). Tombol cetak/ekspor di layar selalu
berada DI DALAM halaman Resource yang sudah mensyaratkan izin `view_...`
yang SAMA untuk dibuka -- siapa pun yang sebelumnya bisa mengklik tombol
cetak sudah otomatis punya izin itu. Yang ditutup #449 cuma jalur tebak
URL langsung, bukan jalur normal staf memakai fiturnya, jadi tidak ada
staf yang perlu izin baru karenanya.

---

## H. Setelah aplikasi live -- changelog dan versi

Keputusan Owner, 7 September 2026. **Belum dikerjakan, dan sengaja tidak
dikerjakan sebelum live** -- versinya baru mulai dihitung sejak produksi.

### Penomoran versi

Mulai dari **2.0.0**, dengan arti yang ditetapkan Owner sendiri:

```
2 . 0 . 0
|   |   +--  perbaikan bug
|   +------  modul / fitur
+----------  versi aplikasi
```

Jadi `2.0.0` berarti: aplikasi versi kedua (yang pertama aplikasi legacy),
belum ada modul baru sejak live, belum ada perbaikan bug sejak live.

Angka mayor 2 dipilih karena ini penerus aplikasi legacy, bukan aplikasi
pertama -- penomorannya melanjutkan sejarahnya, bukan mengulang dari nol.

### Changelog yang tampil saat login

Setiap kali versinya berubah, pengguna melihat changelog terbaru **saat
login**. Syaratnya satu, dan itu bagian yang menentukan bentuknya:

**Hanya tampil SEKALI per pengguna, sampai ada changelog baru.**

Artinya yang disimpan bukan "sudah pernah lihat atau belum", melainkan
**versi changelog terakhir yang sudah dilihat pengguna itu**. Kalau yang
tersimpan lebih lama daripada versi sekarang, changelog ditampilkan; kalau
sama, tidak. Menyimpan penanda boolean akan gagal pada rilis berikutnya --
penanda itu harus direset untuk semua orang tiap kali rilis, dan reset yang
terlewat membuat sebagian orang tidak pernah melihat rilis baru tanpa gejala
apa pun.

### Yang belum diputuskan

- Di mana changelog-nya ditulis: berkas di repositori, atau tabel yang bisa
  disunting dari aplikasi.
- Apakah pengguna bisa membukanya lagi setelah ditutup (misalnya dari menu
  About), atau memang sekali lewat.
- Apakah rilis yang cuma perbaikan bug juga memunculkannya, atau hanya rilis
  yang membawa modul/fitur.
