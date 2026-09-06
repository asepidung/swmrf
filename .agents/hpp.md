# HPP -- apa yang sudah diketahui, dan apa yang belum

Dibaca dari tiga berkas rujukan Owner (tidak ikut ke repositori):

| Berkas | Isinya |
|---|---|
| `Carcas - PT. LEMBU JANTAN PERKASA - 15-Jun-2026.pdf` | 20 ekor, timbang 14 Jun, potong 15 Jun, `WGH-260065` |
| `boning LJP 16 Juni.pdf` | 33 baris hasil boning: box, pcs, kg |
| `contoh costing.xlsx`, sheet `LJP 20 - 15 JUN` | form HPP accounting |

Ketiganya **satu rangkaian yang sama**: satu lot, 20 ekor, dari PT. Lembu
Jantan Perkasa. Diterima dan ditimbang 14 Juni, dipotong 15 Juni, di-boning
16 Juni, lalu di-costing.

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

Dan harganya **tergantung supplier** -- keterangan Owner. Itu sudah ikut
tertangani dengan sendirinya: harga dibaca dari PO milik lot itu, dan satu lot
selalu satu supplier. Yang harus dijaga adalah tidak pernah mengambil harga
dari tempat lain, misalnya harga terakhir yang dipakai atau rata-rata.

Form accounting sekarang memakai satu harga untuk semuanya. Itu benar hari ini
dan akan diam-diam salah pada lot pertama yang harganya berbeda -- persis
jenis kekeliruan yang tidak menimbulkan gejala apa pun.

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

## 8. Pertanyaan untuk accounting

Disusun supaya bisa ditanyakan apa adanya.

1. **Potongan gross -> net itu artinya apa, dan kenapa berbeda-beda per
   produk?** (6%, 16,45%, 5%, dan sejumlah baris yang diketik langsung.)
   Kalau ini diskon pelanggan, ia akan berubah tiap periode -- dan ikut
   menggeser HPP.
2. Kenapa tulang, lemak, dan jeroan **tidak punya harga gross sama sekali**?
   Apakah keduanya memang tidak pernah dijual dengan daftar harga?
3. **Overhead 3.000/kg** datang dari mana, dan apakah tetap tiap bulan?
   Kenapa ia tidak menambah HPP, hanya memotong profit?
4. Angka **60.501,24** di baris 98 (`J97/F91`) untuk apa? Ia tidak dipakai
   sel mana pun.
5. Blok **1.100 kg @51.000 + 6.072 kg @50.000 = 359.700.000** di baris 98-100
   itu apa? Totalnya (7.172 kg) tidak sama dengan Load Weight (10.888 kg) dan
   tidak dirujuk sel mana pun.
6. **Apakah ada daftar harga kedua?** Kolom `GROSS PRICE` di costing belum
   dipastikan menyalin price list yang berlaku. Kalau ternyata daftar yang
   berbeda, ia harus ikut disimpan -- kalau tidak, harga yang dipakai
   menghitung HPP tidak akan pernah bisa dihasilkan ulang.
7. Daftar produk di form costing selalu sama tiap kali (puluhan baris ber-qty
   0 tetap ada), atau boleh mengikuti hasil boning hari itu?
8. Kalau bahan penolong nanti ikut dihitung, ia **menambah HPP** atau
   diperlakukan seperti overhead sekarang -- hanya memotong profit?

---

## 9. Ganjalan lain di berkasnya

- **Pembaginya tidak konsisten.** `G87 = F87/F91` (daging dibanding load
  weight -- rendemen). Tetapi `G88 = F88/F87` (offal dibanding **daging**,
  101%). Dua arti berbeda di kolom yang sama.
- **Rumus mati.** `H87 = I87*0,935` sementara `I87` kosong, jadi hasilnya 0.
- **Daftar harga tertanam di dalam form costing**, bukan diambil dari price
  list.
