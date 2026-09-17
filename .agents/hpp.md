# HPP -- apa yang sudah diketahui, dan apa yang belum

Dibaca dari berkas rujukan Owner (tidak ikut ke repositori):

| Berkas | Isinya |
|---|---|
| `CPO-260106 - PT. LEMBU JANTAN PERKASA.pdf` | PO 50 ekor, HEIFER dan STEER sama-sama 62.500/kg |
| `Carcas - PT. LEMBU JANTAN PERKASA - 15-Jun-2026.pdf` | 20 ekor, timbang 14 Jun, potong 15 Jun, `WGH-260065` |
| `boning LJP 16 Juni.pdf` | 33 baris hasil boning: box, pcs, kg |
| `contoh costing.xlsx`, sheet `LJP 20 - 15 JUN` | form HPP accounting, lot LJP |
| `costing baqara.xlsx`, sheet `BQR 11 - 11 JUN` | form HPP accounting, lot BQR -- pembanding |
| `Costing Juni.xlsx` | **tujuh lot** sepanjang Juni, tiga supplier -- dua di antaranya sama dengan berkas di atas |

Empat yang pertama **satu rangkaian yang sama**: satu lot, 20 ekor, dari
PT. Lembu Jantan Perkasa. Dipesan lewat `CPO-260106`, diterima dan ditimbang
14 Juni, dipotong 15 Juni, di-boning 16 Juni, lalu di-costing.

**Costing Baqara adalah lot yang berbeda** (11 ekor, potong 11 Juni), dipakai
sebagai pembanding untuk memastikan mana yang tetap dan mana yang berubah
antar batch. Perbandingannya di bagian 8.

Catatan ini BUKAN keputusan. Isinya apa yang benar-benar dilakukan sekarang,
beserta pertanyaan yang belum ada jawabannya. Keputusan ditulis di `agents.md`
setelah Owner bertanya ke accounting.

---

## 1. Ketiga dokumen menyambung tanpa satu angka pun berubah

Diuji satu per satu, bukan disimpulkan dari bentuknya.

**Carcass -> costing.** Tiga angka disalin bulat-bulat:

| Costing | Laporan carcass | Nilai |
|---|---|---|
| `F91` Load Weight | Total Receive | 10.888,00 kg |
| `F88` Offal | Offal | 6.195,82 kg |
| `F89` Hides | Total Hides | 775,90 kg |

**Boning -> costing.** Setiap baris cocok sampai dua desimal. Totalnya:

```
daging hasil boning  =  6.115,08 kg   =  F87 di costing
offal                =  6.195,82 kg   =  F88
kulit                =    775,90 kg   =  F89
```

**Carcass -> boning.** Karkas A+B seluruh 20 ekor = 6.155,72 kg, hasil
boningnya 6.115,08 kg -- **susut boning 40,64 kg (0,66%)**.

Rendemen karkas 56,54% (`6.155,72 / 10.888`), persis yang tercetak di
laporannya. Rendemen boning 56,16% (`6.115,08 / 10.888`), persis `G87`.

### Nama produk yang berbeda antara boning dan costing

Bukan pemecahan, hanya penggantian nama -- dan satu penggabungan:

| Boning | Costing | kg |
|---|---|---|
| `PAHA DEPAN` | `Chuck` | 1.341,14 |
| `RUMP` | `Rump/Paha` | 289,70 |
| `SILVERSIDE` | `Silverside` | 348,06 |
| `FQ 85 CL` 190,00 **+** `FQ 85 CL CUT` 15,00 | `FQ 85 CL` | 205,00 |

Costing juga memuat puluhan baris produk ber-qty 0 yang tidak ada di boning --
daftar produknya tetap, bukan mengikuti hasil hari itu.

---

## 2. Metodenya: alokasi biaya gabungan menurut nilai jual

Satu sapi menghasilkan puluhan produk sekaligus. Biaya belinya satu, dan tidak
ada cara "benar" membagi biaya itu ke tiap potongan -- ini masalah *joint
product costing* yang klasik. Yang dipakai accounting adalah metode **Relative
Sales Value**:

```
J (nilai jual)      = harga net  x  kg hasil                        per produk
J90 (total)         = SUM(daging) + offal + kulit                  = 717.762.805,70
J91 (biaya beli)    = load weight x harga/kg = 10.888 x 62.500     = 680.500.000
L (alokasi biaya)   = (J / J90) x J91                               per produk
K (HPP per kg)      = L / kg                                        per produk
```

Harga net sendiri = harga gross dikurangi 6%: `H = I - (I x 6%)`.

### Yang menyederhanakan seluruhnya

Kalau `L = (H x F / J90) x J91` dan `K = L / F`, maka **F saling menghapus**:

```
HPP per kg  =  harga jual net  x  (J91 / J90)
```

Untuk lot ini rasionya `680.500.000 / 717.762.805,70 = 0,948085`.

Diuji: Topside `140.060 x 0,948085 = 132.788,76` -- sama persis dengan isi
berkasnya. Begitu juga Silverside, Chuck, dan Bone.

**Jadi seluruh sheet berisi 80 baris itu, untuk urusan HPP, adalah SATU angka
rasio.** Berat panen tiap produk tidak mempengaruhi HPP produk itu sendiri --
ia hanya ikut membentuk `J90`, dan lewat situ menggeser rasionya.

### Konsekuensi yang perlu disadari

HPP di sini **ditarik oleh harga jual**, bukan oleh biaya. Menaikkan harga
jual sebuah produk menaikkan HPP produk itu, padahal biaya sapinya tidak
berubah sepeser pun. Itu sifat metodenya, bukan kekeliruan -- tetapi artinya
HPP ini tidak bisa dipakai untuk menilai produk mana yang murah diproduksi.

---

## 3. Di mana BOM masuk

Boning mencatat **box dan pcs**, bukan cuma kilogram:

```
316 box dan 833 pcs daging, dari 31 baris produk
contoh: PAHA DEPAN 66 box / 315 pcs, TOPSIDE 21 box / 39 pcs,
        BONE 5 box / 0 pcs
```

Di situlah BOM (#344) bertemu costing. Baris BOM menyimpan dasar hitungnya
sendiri -- per box atau per pcs -- sehingga pemakaian bahan sebuah boning bisa
dihitung langsung:

```
karton, karung  ->  jumlah BOM  x  BOX produk itu
plastik vakum   ->  jumlah BOM  x  PCS produk itu
```

Bahan penolong **belum masuk hitungan costing sekarang** -- tidak ada satu
baris pun di form accounting. Begitu juga upah. Overhead ada (`J94` =
3.000/kg) tetapi **tidak menambah HPP**; ia hanya memotong profit di baris
94-95.

---

## 4. Yang sudah kita punya, dan yang belum

Seluruh rantainya sudah ada, dan bentuk tabelnya kebetulan sama persis dengan
laporan carcass legacy:

```
purchase_cattle_items.price          harga per KG, per kelas sapi
  -> cattle_receivings               supplier
  -> cattle_receiving_items          eartag, kelas, initial_weight
  -> cattle_weighing_items           actual_weight (timbang ulang)
  -> carcass_items                   carcass_1, carcass_2, hides, tail
  -> boning_carcasses -> bonings
  -> boning_items                    product_id, weight, qty_pcs
  -> product_materials               BOM (#344)
```

`carcass_1` / `carcass_2` adalah `Carcase A` / `Carcase B` di laporan itu, dan
`Carcass::offalWeight()` sudah menghitung offal menurut kesepakatan yang sama
(`karkas + buntut`; diuji: `6.155,72 + 40,10 = 6.195,82`, persis).

**Yang perlu diisi dari luar:** harga jual gross per produk, potongan 6%, dan
overhead per kg. Sisanya bisa dihasilkan sendiri.

**Yang belum ada:** `bonings` tidak menyimpan apa pun tentang lot atau
supplier -- kolomnya hanya `doc_no`, `boning_date`, `status`, `kunci`, `note`,
`created_by`. Lotnya baru terbaca lewat `boning_carcasses`, dan skema itu
mengizinkan satu boning memuat lebih dari satu carcass (karena itu lebih dari
satu lot). Contoh ini kebetulan satu lot penuh; apakah selalu begitu, belum
diketahui.

### Satu ganjalan di kode kita sendiri

`Carcass::yieldPercent()` membagi karkas dengan **berat timbang ulang**
(`actual_weight`), sedangkan laporan carcass legacy membaginya dengan **berat
terima** (kolom `Receive Wt`, yang jumlahnya 10.888 dan dipakai costing sebagai
`Load Weight`). Dua pembagi berbeda menghasilkan dua rendemen berbeda untuk
sapi yang sama.

Owner sudah memastikan berat mana yang dipakai membayar pemasok -- berat surat
jalan, lihat bagian 6. Yang belum: apakah rendemen yang KITA tampilkan juga
harus memakai berat itu, atau memang sengaja berbeda karena menjawab
pertanyaan yang lain (seberapa banyak karkas yang keluar dari sapi yang
benar-benar ada, bukan dari yang tertulis di surat jalan).

---

## 5. Pertanyaan yang GUGUR setelah diperiksa

**"Offal 6.195,82 kg untuk 20 ekor itu berat apa? Melebihi karkasnya."**

Jawabannya sudah ada di kode kita sendiri, dari keterangan Owner
4 September 2026: jeroan **tidak pernah ditimbang**. Beratnya ditetapkan
menurut kesepakatan sebagai `karkas + buntut` -- dan `6.155,72 + 40,10`
memang tepat `6.195,82`.

**"Apakah satu boning memuat lebih dari satu lot?"**

Untuk contoh ini: tidak. Boning 16 Juni adalah lot yang sama persis dengan
carcass 15 Juni, sampai ke desimalnya. Pertanyaannya diturunkan menjadi
"apakah SELALU begitu" -- lihat daftar di bawah.

---

## 6. Yang sudah dijawab Owner

Jawaban Owner, 6 September 2026, sebelum bertanya ke accounting.

### Load Weight = berat surat jalan, bukan timbang ulang

> "itu berat surat jalan bro qty receive, karena kami bayar sesuai qty kirim
> dari supplier atau qty yang kita receive bukan hasil timbang ulang"

Jadi `J91` -- biaya beli, dasar seluruh alokasi -- berdiri di atas
`cattle_receiving_items.initial_weight`, BUKAN `cattle_weighing_items.actual_weight`.
Masuk akal: yang dibayar ke pemasok memang apa yang dikirim, dan susutnya
sudah dicatat sendiri sebagai kerugian.

**Akibatnya bagi kode kita:** `Carcass::yieldPercent()` membagi karkas dengan
berat TIMBANG ULANG, sedangkan laporan carcass legacy membaginya dengan berat
TERIMA. Dua rendemen berbeda untuk sapi yang sama, dan yang dipakai costing
adalah yang kedua. Harus diputuskan sebelum HPP dibangun di atasnya.

### Harga DIKUNCI saat costing dibuat

> "sepertinya di kunci bro, karena harusnya tiap boning dapat harga yang
> berbeda tergantung kualitas hasil boning"

Ini menentukan bentuk modulnya: dokumen costing menyimpan **salinan harga**
yang dipakainya, bukan menunjuk price list. Price list yang berubah bulan
depan tidak boleh mengubah HPP boning yang sudah jadi -- sama seperti invoice
yang menyimpan harganya sendiri.

### Penamaan produk mengikuti permintaan pelanggan

> "chuck itu bagian paha depan dan rump itu paha ... karena spesialnya
> customer LION kita kirim rump dia minta namanya jadi paha"

Jadi `PAHA DEPAN` = Chuck dan `RUMP` = Rump/Paha bukan dua produk berbeda,
melainkan satu produk dengan nama yang berbeda menurut siapa yang membacanya.
Accounting akan menyesuaikan ke nama item.

### Penggabungan FQ 85 CL CUT selalu terjadi

> "selalu begitu dan mungkin nanti ada yang lain"

Jadi peta penggabungan harus bisa bertambah, bukan ditulis mati sebagai satu
kasus.

### Bahan penolong SEHARUSNYA masuk

> "nah harusnya dihitung tapi disini enggak, itu yang mau coba gw dongkrak"

Inilah alasan BOM (#344) dikerjakan lebih dulu. Form accounting sekarang tidak
menghitungnya sama sekali.

### Kedua kelas sapi satu harga, dan PO bukan batas lot

Dari `CPO-260106` (PO 14 Jun, tiba 13 Jun, PT. Lembu Jantan Perkasa):

| Kelas | Ekor | Harga/kg |
|---|---|---|
| HEIFER | 18 | 62.500 |
| STEER | 32 | 62.500 |

Kebetulan **kedua kelas satu harga**, sehingga `62.500` di costing berlaku
untuk seluruh lot. Tetapi Owner menegaskan itu kebetulan, bukan aturan:
"walaupun 2 class sapi itu harganya sama tidak menutup kemungkinan ada harga
beda".

**Jadi biaya beli TIDAK boleh dihitung sebagai satu harga dikali berat
total.** Yang benar:

```
J91  =  SUM( berat terima kelas itu  x  harga kelas itu )
```

Bentuk ini menghasilkan angka yang sama persis ketika harganya kebetulan sama
(`10.888 x 62.500 = 680.500.000`), dan tetap benar ketika suatu saat berbeda.
Datanya sudah ada: `cattle_receiving_items` menyimpan `initial_weight` beserta
`cattle_class_id`, dan `purchase_cattle_items` menyimpan harga per kelas.

Dan bukan sekadar kemungkinan. `CPO-260112`, **supplier yang sama**:

| PO | Tanggal | HEIFER | STEER |
|---|---|---|---|
| `CPO-260106` | 14 Jun 2026 | 62.500 | 62.500 |
| `CPO-260112` | 20 Agu 2026 | **61.700** | **62.000** |

Jadi harganya berbeda **antar kelas di dalam satu PO yang sama**, bukan hanya
antar supplier. Rumus "satu harga dikali berat total" sudah pasti salah untuk
lot yang kedua.

Harga karena itu harus selalu dibaca dari **PO milik lot itu sendiri**, per
kelas. Tidak pernah dari harga terakhir yang dipakai, dan tidak pernah dari
rata-rata -- keduanya akan tetap menghasilkan angka yang kelihatan wajar.

Form accounting sekarang memakai satu harga untuk semuanya. Itu benar untuk
lot 15 Juni dan diam-diam salah untuk lot 20 Agustus -- persis jenis
kekeliruan yang tidak menimbulkan gejala apa pun.

Tetapi PO-nya **50 ekor, sedangkan yang dipotong 20**. Jadi satu PO menaungi
lebih dari satu batch potong, dan **batas lot untuk costing adalah dokumen
CARCASS, bukan PO**. Biaya belinya pun dihitung dari berat terima ekor yang
benar-benar dipotong (`10.888 x 62.500 = 680.500.000`), bukan dari nilai PO --
yang memang tidak bisa diketahui sebelum sapinya ditimbang.

Karena itu PO sapi memang tidak punya nilai rupiah. Cetakan PO di aplikasi
kita sudah benar: hanya kelas, jumlah ekor, dan harga per kg -- tanpa subtotal
maupun total. Yang memuat subtotal adalah aplikasi legacy (`cattle/view.php`),
dan angkanya memang tidak bermakna: 18 ekor x 62.500 per KG.

### Satu boning selalu satu lot supplier

> "biasanya kalo sapi beda supplier semua dipisah mulai dari carcass sama
> boning"

Jadi pertanyaan tentang boning yang memuat lebih dari satu lot terjawab:
tidak terjadi. Pemisahannya sudah dilakukan sejak carcass. Skema kita tetap
mengizinkan sebaliknya (`boning_carcasses` banyak-ke-banyak), dan itu tidak
apa-apa selama tidak ada yang menganggapnya mustahil.

### Upah dihiraukan dulu, dan pengecualian offal disengaja

Upah: "hiraukan dulu". Offal dan kulit yang menyerap biaya tetapi tidak ikut
dalam pembagi `Gross Profit /kg`: "sepertinya itu disengaja".

---

## 7. Potongan gross -> net BUKAN satu angka

Sempat dicatat sebagai "potongan 6%". Setelah seluruh kolomnya diperiksa,
ternyata ada empat cara berbeda menurunkan `H` (NET) dari `I` (GROSS):

| Potongan | Produk |
|---|---|
| **6%** | Topside, Silverside, Knuckle, Tenderloin, Striploin cut, Rump, Chuck, Blade, Chuck Tender, Shank, Brisket, FQ 85 CL, Short Rib, Marrow Bone, Oxtail |
| **16,45%** | Striploin Whole, Striploin GOLD, Striploin Less fat, Cuberoll, Cuberoll TS |
| **5%** | Brisket PEDO, RIBS, Fat Brisket |
| **diketik tangan** | sisanya. Sebagian sama persis dengan gross (potongan 0%), sebagian tidak beraturan: Back Rib 34,29%, Operib Frenched 39,06%, Osso Bucco 20,51% |

Dan **enam belas baris tidak punya harga gross sama sekali** -- hanya net yang
diketik: Spare Rib, Scapular, Brisket Bone, Back Bone, Tendon, Tendon SP,
Bone, Tail Top, Tail Tip, Neck Bone, Conro, Bone SP, Fat Ginjal, Fat boning,
Offal, dan Kulit. Semuanya tulang, lemak, dan jeroan.

Yang terpakai di lot 15 Juni ini: 6% untuk hampir semuanya, 16,45% untuk
Cuberoll, 34,29% (diketik) untuk Back Rib, dan 16 item tanpa gross.

Karena `HPP = harga net x rasio`, angka potongan ini **ikut menentukan HPP
setiap produk**.

---

## 8. Tujuh costing sepanjang Juni

`Costing Juni.xlsx` berisi tujuh lot dari tiga supplier. Dibandingkan sel per
sel, dan inilah yang paling menjelaskan.

| Lot | Ekor | Load kg | Rendemen | Harga/kg | Rasio | HPP Topside | Net/kg |
|---|---|---|---|---|---|---|---|
| LJP 30 - 2 JUN | 30 | 15.332 | 55,96% | 62.500 | 0,956167 | 133.920,74 | 2.120,27 |
| LJP 19 - 3 JUN | 19 | 9.693 | 55,65% | 62.500 | 0,940151 | 131.677,51 | 4.149,72 |
| HDS 10 - 6 JUN | 10 | 5.498 | 55,36% | **63.000** | 0,952043 | 133.343,17 | 2.732,22 |
| BQR 24 - 10 JUN | 24 | 11.764 | 55,77% | **62.000** | 0,953932 | 133.607,76 | 2.368,56 |
| BQR 11 - 11 JUN | 11 | 5.315 | 56,36% | **62.000** | 0,977225 | 136.870,07 | **-436,15** |
| LJP 30 - 14 JUN | 30 | 16.568 | 56,87% | 62.500 | 0,935749 | 131.060,96 | 4.545,40 |
| LJP 20 - 15 JUN | 20 | 10.888 | 56,16% | 62.500 | 0,948085 | 132.788,76 | 3.093,59 |

**HPP satu produk yang sama bergerak 5.809 rupiah per kg dalam satu bulan**
(131.060,96 sampai 136.870,07 -- rentang 4,43%). Jadi benar bahwa tiap boning
menghasilkan HPP berbeda, dan besarnya bukan angka sepele.

Harga beli tetap per supplier sepanjang Juni: LJP 62.500, HDS 63.000,
BQR 62.000.

### Seluruh isian sebuah costing cuma EMPAT hal

Dicari dengan membandingkan ketujuh sheet: sel mana yang diketik tangan DAN
berbeda antar lot. Hasilnya, di seluruh kolom A sampai L:

| Sel | Isinya | Bisa kita hasilkan sendiri? |
|---|---|---|
| `D2` | judul lot, misalnya `SUPPLIER : LJP ( 20 EKOR )` | ya -- dari carcass |
| `L3` | tanggal potong (`L4` = `L3 + 1` otomatis) | ya -- dari carcass |
| `F91` | Load Weight | ya -- berat terima |
| `F93` | harga beli per kg | ya -- dari PO |
| `F6:F89` | kuantitas tiap produk | ya -- dari boning |

**Tidak ada yang lain.** Sisanya rumus, atau tetapan template.

Artinya seluruh isian costing sudah ada di basis data kita. Yang benar-benar
datang dari luar hanya dua, dan keduanya tetapan template: **daftar harga
jual** dan **overhead**.

### Daftar harganya sama persis di KETUJUH sheet

Seluruh kolom `NET PRICE` dan `GROSS PRICE` dibandingkan sel per sel:
**nol perbedaan**, 80 baris produk, tujuh lot, sepanjang Juni.

Jadi harga jual tidak diisi per batch -- ia hidup di dalam templatenya. "Harga
dikunci saat costing dibuat" lebih tepat disebut dikunci saat TEMPLATE dibuat.

Itu memperbesar taruhannya: kalau harga jual sebenarnya berubah di tengah
bulan sementara templatenya belum, HPP seluruh lot ikut meleset -- dan tidak
ada gejala apa pun yang menandainya.

### Overhead 3.000/kg juga tetapan, bukan isian

Sama di ketujuh lot. Dan justru karena rata itulah **BQR 11 ekor tercatat
merugi**: gross profit-nya 2.563,85 per kg, di bawah overhead 3.000. Padahal
rendemennya (56,36%) lebih baik daripada lima lot lain yang untung.

Untung-rugi sebuah lot karena itu lebih ditentukan harga beli dan harga jual
daripada oleh performa boningnya.

### Boning #0439 ternyata lot 14 Juni

Berkas boning yang pertama dibaca (`Detail Boning #0439 15 juni.pdf`, sempat
disangka salah pasangan) ternyata milik lot **LJP 30 - 14 JUN**: daging
9.423,03 kg, offal 9.489,65 kg, kulit 1.139,10 kg -- ketiganya persis.

Polanya konsisten: **boning dikerjakan sehari setelah potong**. Lot 14 Juni
di-boning 15 Juni, lot 15 Juni di-boning 16 Juni. Rumus `L4 = L3 + 1` di form
costing memang menuliskan aturan itu.

### Catatan tentang berkasnya

Tiap sheet menyimpan salinan lot lain di kolom tersembunyi `O` sampai `AS`.
Bukan data baru, hanya sisa penyalinan antar sheet -- tetapi perlu diketahui
supaya tidak dikira ada kolom tambahan yang bermakna.

---

## 9. Jawaban dari sisi penjualan, 7 September 2026

Owner menanyakan tujuh pertanyaan di bagian sebelumnya. Yang menjawab ternyata
**orang marketing, bukan accounting** -- perlu diingat saat membaca, karena
sebagian jawabannya sendiri memakai kata "mungkin".

### Warna sel = pelanggan

| Warna | Isi | Potongan |
|---|---|---|
| Kuning (`FFFF00`) | LION | 6% |
| Hijau (theme 9) | HYPERMART | 16,45% |
| Biru (indexed 27) | **selain kedua pelanggan itu** | tidak ada |

**Diverifikasi langsung dari `styles.xml`**, bukan diterima begitu saja:
seluruh baris kuning berpotongan 6% dan seluruh baris hijau berpotongan
16,45%, nol pengecualian.

Biru **bukan sebuah grup bernama "regular"** -- itu koreksi Owner. Biru
berarti "harga umum, untuk siapa pun di luar LION dan HYPERMART". Bedanya
penting saat modulnya dibangun: acuannya bukan menunjuk satu grup tertentu,
melainkan menyatakan bahwa produk itu dinilai memakai harga umum.

Jadi potongan gross -> net adalah **trading terms per pelanggan**, bukan
diskon produk. Dan pertanyaan "kenapa tulang dan lemak tidak punya harga
gross" terjawab: di luar kedua pelanggan itu tidak ada trading terms, jadi
gross dan net-nya sama dan hanya satu yang diketik.

### Potongan HANYA untuk dua pelanggan utama

Keterangan Owner: trading terms **hanya berlaku untuk HYPERMART dan LION**.
Semua pelanggan akan punya grup -- warga sekitar punya grup warga, karyawan
punya grup karyawan -- tetapi grup-grup itu untuk **transaksi penjualannya**,
bukan untuk menilai hasil boning.

**Batasan yang lahir dari sini, dan harus ditegakkan kode saat modulnya
dibangun:** penilaian hanya boleh memakai harga LION, harga HYPERMART, atau
harga umum. Kalau sebuah produk dinilai memakai harga karyawan atau warga,
total nilai jual turun, rasio naik, dan **HPP seluruh produk lain ikut naik**
-- potongan untuk karyawan berubah wujud menjadi tambahan biaya bagi produk
komersial, tanpa satu pun gejala yang menandainya.

### Overhead 3.000/kg adalah pengganti BOM

> "estimasi, mungkin itu pengganti build material"

Ini jawaban untuk pertanyaan overhead DAN pertanyaan bahan penolong --
keduanya hal yang sama. Konsekuensinya: begitu BOM benar-benar dihitung, bukan
hanya angkanya yang berubah, **posisinya di rumus juga pindah**. Sekarang ia
hanya memotong laba di baris bawah; kalau ia memang biaya kemasan, ia biaya
produksi dan tempatnya di dalam HPP.

Dan itu berarti lot BQR yang tercatat rugi 436/kg **dinyatakan rugi oleh angka
tebakan**.

### Blok baris 98-100 ternyata hidup

> "itu cuma itungan sampingan kadang digunakan jika Harga class berbeda"

Persis kasus `CPO-260112` (HEIFER 61.700, STEER 62.000). Jadi blok itu adalah
versi manual dari `SUM(berat kelas x harga kelas)` -- bukan sisa template.
Rumus yang diusulkan di bagian 6 karena itu bukan konsep baru, hanya
mengotomatiskan yang sudah dikerjakan dengan tangan.

### Sisanya

- **Daftar harga diperbarui saat ada negosiasi harga dengan pelanggan.**
  Legacy belum punya price list sama sekali; aplikasi ini punya, jadi kolom
  `GROSS PRICE` bisa diambil dari `price_list_items`.
- **Daftar produk mengikuti hasil boning** ("lebih baik ikuti hasil boning").
- **Produk yang harganya masih rentang tawar dinilai memakai NET.** Keputusan
  Owner. Back Rib tetap dinilai 69.000, bukan 105.000 -- gross dan net di
  baris-baris itu berfungsi sebagai batas atas dan batas bawah untuk pelanggan
  baru, bukan harga sungguhan.

---

## 10. Pengganti warna sel di aplikasi

Seluruh potongannya sudah punya rumah:

```
price_lists       (customer_group_id)
price_list_items  (product_id, price)      <- GROSS PRICE
customers         (default_discount)       <- potongan trading terms
```

Yang belum ada **hanya satu**: costing perlu tahu sebuah produk dinilai
memakai harga siapa. Di Excel itu dijawab warna sel; di aplikasi ia harus
menjadi data.

```
products.costing_customer_group_id  ->  customer_groups   (boleh kosong)
```

Kosong berarti **harga umum** -- itulah biru, dan itu keadaan yang paling
banyak, bukan pengecualian. Terisi berarti dinilai memakai harga pelanggan
tersebut, dan potongannya ikut dipakai:

```
GROSS = price_list_items.price     (produk itu, di grup acuannya)
NET   = GROSS x (1 - potongan grup itu)
```

Alasan memilih kolom, bukan menyalin gagasan warna: warna hanya terlihat oleh
yang membuka berkasnya. Kalau LION berhenti mengambil Topside, tidak ada yang
tahu HPP-nya melenceng. Sebagai kolom, asumsinya bisa dicetak di dokumen
costing -- *"Topside, dinilai memakai harga LION"* -- sehingga yang membaca
laporan melihat asumsinya, bukan menebaknya.

**Masih terbuka:** apa yang dilakukan costing bila sebuah produk seharusnya
memakai harga pelanggan tertentu tetapi belum diisi. Tiga pilihan yang
diajukan -- tolak, jatuh ke harga umum, atau jalan tetapi ditandai. Usulan:
yang ketiga.

---

## 11. Yang menurut Hafizh keliru dari metode ini

Diminta Owner, 7 September 2026: "kalau ada yang menurut lu keliru dari
hitungan hpp ini pasti gw tampung". Diurut dari yang paling mendasar.

### 11.1 Costing ini tidak bisa menyatakan produk mana yang untung

Bukan pendapat, melainkan akibat rumusnya:

```
HPP            = net x k
margin         = net - HPP = net x (1 - k)
margin persen  = 1 - k = 5,19%      untuk SETIAP produk
```

Topside 5,19%. Tulang 5,19%. Oxtail 5,19%. Semuanya sama persis, karena
marginnya memang diturunkan dari satu angka yang sama. Sheet-nya sendiri
menuliskan angka itu di `L92` = `J92/J90`.

Jadi pertanyaan "produk mana yang paling menguntungkan" akan selalu dijawab
"semuanya sama" -- bukan karena kenyataannya begitu, melainkan karena rumusnya
tidak sanggup menjawab lain.

### 11.2 HPP mengikuti harga jual, sehingga tidak bisa mendeteksi harga yang kemurahan

Menaikkan harga Topside 10% menaikkan HPP Topside kira-kira 10%, dan
marginnya tetap 5,19%. Produk yang selama ini kemurahan akan selalu terlihat
sama sehatnya dengan yang lain.

Ini konsekuensi metodenya, bukan cacat perhitungan -- tetapi harus disadari
kalau suatu saat HPP dipakai untuk memutuskan harga, karena ia akan mengamini
harga apa pun yang sudah ada.

### 11.3 Acuan satu produk menggeser HPP SELURUH produk

`k = biaya beli / total nilai jual`. Memindahkan satu produk ke harga yang
lebih rendah menurunkan penyebutnya, menaikkan `k`, dan menaikkan HPP semua
produk -- bukan hanya produk itu. Turun 1% pada total nilai jual menaikkan HPP
Topside sekitar 1.300 rupiah per kg.

Sebagian besar bahaya ini hilang selama batasan di bagian 9 dipegang. Karena
itu batasan tersebut harus ditegakkan kode, bukan diserahkan pada kebiasaan.

### 11.4 Risiko menghitung susut dua kali

Ini yang paling mungkin menghasilkan angka salah tanpa gejala.

Biaya beli dihitung dari **berat surat jalan** -- termasuk kilogram yang
menyusut dan tidak pernah menjadi produk. Biaya itu dialokasikan ke produk.
**Jadi susut sudah berada di dalam HPP.**

Sementara itu `financial_losses` mencatat susut, dan rencananya (bagian A
`tertunda.md`) nilainya diisi `quantity x HPP` begitu HPP tersedia. Kalau
keduanya dilaporkan, kerugian yang sama dihitung dua kali: sekali terbenam di
harga pokok produk, sekali lagi sebagai baris kerugian.

Berlaku juga untuk susut boning (40,64 kg pada lot 15 Juni).

**Harus diputuskan sebelum HPP dipakai, bukan sesudah.**

### 11.5 Untung-rugi sebuah lot ditentukan angka tebakan

Overhead 3.000/kg adalah estimasi. Lot BQR 11 ekor tercatat rugi 436/kg
semata-mata karenanya, padahal rendemennya (56,36%) lebih baik daripada lima
lot yang untung.

### 11.6 HPP ini untuk apa -- SUDAH DIJAWAB

Keterangan Owner, 7 September 2026: **untuk menilai persediaan** -- berapa
nilai stok di gudang, dan berapa harga pokok saat barang keluar.

Itu kabar baik, dan mengubah bobot keberatan di atas: untuk keperluan itu,
alokasi berbasis nilai jual adalah praktik yang **sah dan dipakai luas**.
Metodenya memadai apa adanya.

Yang tetap berlaku adalah batasnya: **HPP ini tidak boleh dipakai untuk
memutuskan harga atau menilai produk mana yang layak diproduksi** (11.1 dan
11.2). Untuk pertanyaan itu diperlukan metode yang berbeda, dan menambahkan
BOM pun tidak menolong -- cacatnya ada di rumus alokasinya, bukan pada
kelengkapan biayanya.

Keberatan yang tersisa dan tetap harus diselesaikan: **11.3** (batasan acuan)
dan **11.4** (susut dihitung dua kali). Keduanya menghasilkan angka yang salah
untuk keperluan penilaian persediaan itu sendiri.

---

## 12. Yang masih menunggu jawaban ACCOUNTING

Tujuh pertanyaan sebelumnya dijawab dari sisi penjualan (bagian 9). Lima di
antaranya tuntas. Yang tersisa di bawah ini bersifat akuntansi, dan orang
marketing memang bukan tempat bertanyanya.

1. **Susut apakah dihitung dua kali?** Biaya beli memakai berat surat jalan,
   sehingga kilogram yang menyusut sudah terbenam di dalam HPP produk.
   Sementara itu susut juga dicatat sebagai kerugian tersendiri. Kalau
   keduanya masuk laporan, kerugian yang sama muncul dua kali. Rinciannya di
   bagian 11.4. **Ini yang paling mendesak** -- ia menghasilkan angka yang
   salah untuk penilaian persediaan itu sendiri.

2. **Overhead 3.000/kg: dari mana angkanya, dan siapa yang meninjaunya?**
   Marketing menjawab "estimasi, mungkin pengganti build material". Angkanya
   sama di ketujuh lot sepanjang Juni, jadi ia tetapan. Yang belum jelas:
   berapa lama sekali ditinjau, dan atas dasar apa.

3. **Kalau bahan penolong nanti benar-benar dihitung dari BOM, ia menambah
   HPP atau tetap hanya memotong laba?** Kalau ia biaya kemasan, tempatnya di
   dalam HPP. Perpindahan itu mengubah nilai persediaan, jadi bukan keputusan
   yang boleh diambil implementor.

4. **Blok baris 98-100 dipakai kalau harga kelas sapi berbeda.** Konfirmasi:
   apakah benar maksudnya `SUM(berat kelas x harga kelas)`? Kalau ya, ia tidak
   perlu blok terpisah -- rumus itu bisa berlaku selalu, dan menghasilkan
   angka yang sama persis ketika harga kedua kelas kebetulan sama.

5. **Rendemen yang ditampilkan aplikasi memakai pembagi yang mana?** Laporan
   carcass legacy membaginya dengan berat terima; `Carcass::yieldPercent()`
   di aplikasi ini membaginya dengan berat timbang ulang. Biaya beli sudah
   dipastikan memakai berat terima (bagian 6), tetapi rendemen menjawab
   pertanyaan yang berbeda dan boleh saja memakai pembagi yang lain -- asalkan
   disengaja.

---

## 13. Ganjalan lain di berkasnya

- **Pembaginya tidak konsisten.** `G87 = F87/F91` (daging dibanding load
  weight -- rendemen). Tetapi `G88 = F88/F87` (offal dibanding **daging**,
  101%). Dua arti berbeda di kolom yang sama.
- **Rumus mati.** `H87 = I87*0,935` sementara `I87` kosong, jadi hasilnya 0.
- **Daftar harga tertanam di dalam form costing**, bukan diambil dari price
  list.

---

## 14. Yang masih dibutuhkan sebelum modulnya bisa dibangun

Ditulis 7 September 2026 atas permintaan Owner, supaya bisa dikumpulkan
sementara jatah token habis. Diurut menurut apa yang menghalangi apa.

### A. Keputusan Owner -- paling menghalangi, dan tidak butuh siapa pun

Ketiganya bisa dijawab Owner sendiri tanpa menunggu accounting.

1. **Produk yang belum punya grup acuan, costing harus bagaimana?**
   Tolak (costing tidak bisa dibuat), jatuh ke harga umum, atau jalan tetapi
   ditandai di dokumennya. Usulan: yang ketiga -- pekerjaan tidak berhenti,
   tetapi asumsinya kelihatan.

2. **Susut mau dihitung sekali atau dua kali?** Susut sudah terbenam di dalam
   HPP lewat berat surat jalan (bagian 11.4). Kalau `financial_losses` nanti
   diisi `quantity x HPP`, kerugian yang sama muncul dua kali. Pilihannya:
   biarkan di HPP saja dan `financial_losses` tetap nol rupiah, atau keluarkan
   dari HPP dan laporkan sebagai kerugian tersendiri.

3. **Rendemen yang kita tampilkan pakai pembagi yang mana?** Berat terima
   (seperti laporan carcass legacy) atau berat timbang ulang (seperti
   `Carcass::yieldPercent()` sekarang). Keduanya sah; yang penting disengaja.

### B. Data yang perlu dikumpulkan

Tanpa ini modulnya bisa dibangun, tetapi tidak bisa menghasilkan angka.

1. **Daftar grup pelanggan yang sebenarnya.** Nama persisnya, dan pelanggan
   mana masuk grup mana. Sekarang tabel `customer_groups` masih KOSONG.

2. **Price list per grup.** Ini yang paling besar: sekitar 80 produk dikali
   tiga daftar (LION, HYPERMART, harga umum). Tabel `price_lists` dan
   `price_list_items` juga masih kosong.

   Kalau menyalin dari form costing lebih mudah, kolom `GROSS PRICE` di sana
   adalah harga yang dicari -- dan isinya sama persis di ketujuh lot Juni,
   jadi cukup satu kali salin.

3. **Potongan trading terms.** Konfirmasi bahwa LION masih 6% dan HYPERMART
   masih 16,45%, dan apakah ada pelanggan utama ketiga.

4. **Pemetaan produk -> grup acuan.** Produk mana dinilai memakai harga siapa.
   **Ini tidak perlu dikumpulkan dari nol** -- warna sel di `Costing Juni.xlsx`
   sudah memuatnya seluruhnya, dan sudah terbaca. Hafizh bisa mengeluarkan
   daftarnya kapan saja (80 baris: nama produk dan warnanya), tinggal Owner
   periksa apakah masih berlaku.

5. **Overhead.** Apakah masih 3.000/kg, dan siapa yang menetapkannya.

### C. Jawaban accounting yang masih ditunggu

Lima pertanyaan di bagian 12. Yang paling menentukan cuma satu -- **susut
dihitung dua kali** -- dan itu sebenarnya sudah masuk daftar A karena Owner
bisa memutuskannya sendiri kalau accounting lambat menjawab.

Empat sisanya (asal angka overhead, posisi bahan penolong di rumus, blok
baris 98-100, pembagi rendemen) **tidak menghalangi** pembangunan modulnya.
Modul bisa dibuat dengan overhead sebagai angka yang bisa disetel, lalu
diisi belakangan.

### D. Yang TIDAK dibutuhkan lagi

Supaya tidak ada yang mengumpulkan dua kali:

- Contoh costing tambahan. Tujuh lot sudah cukup; polanya sudah terbukti
  seragam.
- Contoh boning atau carcass tambahan. Rantainya sudah diuji menyambung
  sampai dua desimal.
- Penjelasan metode. Rumusnya sudah diturunkan dan diverifikasi.

### E. Urutan pengerjaan yang gw usulkan

1. Isi `customer_groups` dan `price_lists` (butuh B1, B2, B3).
2. Tambah kolom `products.costing_customer_group_id` dan isi dari daftar
   warna (butuh B4).
3. Bangun dokumen costing: satu costing per laporan carcass, menyalin harga
   yang dipakainya (harga dikunci saat costing dibuat).
4. Sambungkan BOM ke pemakaian bahan per boning (sudah siap sejak #344).
5. Terakhir, baru pindahkan overhead ke dalam HPP kalau accounting setuju.

Langkah 1 dan 2 bisa dimulai kapan saja -- keduanya master data, tidak
menyentuh perhitungan apa pun, jadi aman dikerjakan sebelum jawaban accounting
datang.

---

## 15. Jawaban Owner, 17 September 2026

Menjawab bagian 14.A dan 14.B sekaligus, lewat Hafizh.

1. **Rendemen memakai berat terima**, seperti laporan carcass legacy. Berarti
   `Carcass::yieldPercent()` yang sekarang membagi dengan berat timbang ulang
   harus diganti pembaginya. Akibat sampingan: aturan 15 September ("ada satu
   sapi belum ditimbang -> rendemen tampil `-`") kehilangan alasannya, karena
   berat terima selalu ada sejak sapi datang; yang tetap butuh timbang ulang
   adalah susut perjalanan, bukan rendemen. Saat pembaginya diganti, aturan
   `-` itu boleh dilepas dari rendemen -- putuskan di PR-nya, jangan diam-diam.
2. **Overhead masih dipakai, dan berubah-ubah** -- 3.000, kadang 3.500 atau
   4.000, "tergantung mood bos". Jadi ia **angka per costing yang bisa
   disetel**, bukan tetapan aplikasi; nilai bawaannya angka costing terakhir.
3. **Pelanggan utama hanya LION dan HYPERMART.** Tidak ada yang ketiga.
4. **Grup pelanggan dan keanggotaannya akan terisi dari migrasi data legacy.**
   Catatan Hafizh: legacy TIDAK punya price list, jadi `price_lists` per grup
   tetap harus diisi tangan -- sumbernya kolom `GROSS PRICE` di form costing
   (identik di ketujuh lot Juni, cukup sekali salin).
5. Produk tanpa grup acuan -> jalan tapi ditandai; susut tetap di dalam HPP.
   Owner tidak keberatan; sesudah migrasi semua customer pasti bergrup, jadi
   kasus "tanpa grup" hanya mungkin untuk produk, bukan pelanggan.

Pertanyaan accounting (bagian 12) belum ditanyakan; tidak menghalangi.
