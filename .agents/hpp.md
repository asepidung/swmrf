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

## 9. Pertanyaan untuk accounting

Disusun supaya bisa ditanyakan apa adanya.

1. **Potongan gross -> net itu artinya apa, dan kenapa berbeda-beda per
   produk?** Terlihat di rumus kolom `H` (NET PRICE) -- bukan di catatan mana
   pun:

   ```
   H6  = I6 - (I6*6%)          Topside dan sebagian besar potongan utama
   H18 = I18 - (I18*16.45%)    Striploin Whole, GOLD, Less fat, Cuberoll
   H36 = I36 - (I36*5%)        Brisket PEDO, RIBS, Fat Brisket
   ```

   Sisanya diketik langsung tanpa rumus -- sebagian kebetulan sama dengan
   gross, sebagian tidak (Back Rib 69.000 sementara gross-nya 105.000).

   Kalau ini diskon pelanggan, ia akan berubah tiap periode dan ikut menggeser
   HPP.
2. Kenapa tulang, lemak, dan jeroan **tidak punya harga gross sama sekali**?
   Apakah keduanya memang tidak pernah dijual dengan daftar harga?
3. **Overhead 3.000/kg** datang dari mana? Angkanya sama di ketujuh lot
   sepanjang Juni, jadi ia tetapan -- bukan sesuatu yang dihitung per lot.
   Ditinjau berapa lama sekali? Dan kenapa ia tidak menambah HPP, hanya
   memotong profit?

   Ini bukan pertanyaan kecil: BQR 11 ekor tercatat **merugi** semata-mata
   karena overhead rata ini, padahal rendemennya lebih baik daripada lima lot
   yang untung.
4. Blok baris 98-100 (**1.100 kg @51.000 + 6.072 kg @50.000**) muncul sama
   persis di kedua costing, jadi tampaknya sisa template. **Boleh dibuang?**
5. **Kolom `GROSS PRICE` itu menyalin price list yang mana, dan kapan
   diperbarui?** Kedua costing memakai daftar harga yang identik sampai ke
   sen, jadi harganya hidup di template. Kalau price list sebenarnya sudah
   berubah, HPP seluruh lot ikut meleset tanpa gejala.
7. Daftar produk di form costing selalu sama tiap kali (puluhan baris ber-qty
   0 tetap ada), atau boleh mengikuti hasil boning hari itu?
8. Kalau bahan penolong nanti ikut dihitung, ia **menambah HPP** atau
   diperlakukan seperti overhead sekarang -- hanya memotong profit?

---

## 10. Ganjalan lain di berkasnya

- **Pembaginya tidak konsisten.** `G87 = F87/F91` (daging dibanding load
  weight -- rendemen). Tetapi `G88 = F88/F87` (offal dibanding **daging**,
  101%). Dua arti berbeda di kolom yang sama.
- **Rumus mati.** `H87 = I87*0,935` sementara `I87` kosong, jadi hasilnya 0.
- **Daftar harga tertanam di dalam form costing**, bukan diambil dari price
  list.
