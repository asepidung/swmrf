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

Perlu diputuskan mana yang benar sebelum HPP dibangun di atasnya, karena
`J91` -- biaya beli, dasar seluruh alokasi -- dihitung dari berat itu.

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

## 6. Pertanyaan untuk accounting

Disusun supaya bisa ditanyakan apa adanya.

**Tentang angka yang tidak dijelaskan**

1. Potongan **6%** pada `harga net = harga gross - 6%` itu apa? Bukan PPN
   (11%), dan tidak muncul di tempat lain mana pun.
2. **Overhead 3.000/kg** datang dari mana, dan apakah tetap tiap bulan?
   Kenapa ia tidak menambah HPP, hanya memotong profit?
3. **Harga/kg 62.500** itu angka kontrak, atau dihitung dari nilai PO? Lot ini
   berisi 13 STEER dan 7 HEIFER; PO di aplikasi kita menyimpan harga
   **per kelas sapi**, sementara costing memakai satu harga untuk semuanya.
   Apakah kedua kelas memang satu harga?
4. Angka **60.501,24** di baris 98 (`J97/F91`) untuk apa? Ia tidak dipakai
   sel mana pun.
5. Blok **1.100 kg @51.000 + 6.072 kg @50.000 = 359.700.000** di baris 98-100
   itu apa? Totalnya (7.172 kg) tidak sama dengan Load Weight (10.888 kg) dan
   tidak dirujuk sel mana pun.

**Tentang berat yang jadi dasar biaya**

6. **Load Weight 10.888 kg** itu berat surat jalan, atau berat saat sapi
   ditimbang di kandang? Di aplikasi kita keduanya tersimpan terpisah
   (`initial_weight` dan `actual_weight`), dan selisihnya sudah dicatat
   sebagai kerugian susut. Yang dipakai menghitung biaya beli harus yang mana?

**Tentang cakupan dan penamaan**

7. Apakah satu dokumen boning **selalu** berisi tepat satu lot? Kalau suatu
   saat dua lot di-boning bersamaan, dari mana accounting tahu potongan mana
   milik lot yang mana?
8. Siapa yang mengganti nama `PAHA DEPAN` menjadi `Chuck` dan `RUMP` menjadi
   `Rump/Paha` -- dan kenapa penamaannya berbeda antara boning dan costing?
9. `FQ 85 CL CUT` digabung ke `FQ 85 CL` di costing. Selalu begitu, atau
   keputusan sekali itu saja?

**Tentang harga**

10. Harga di kolom `GROSS PRICE` itu price list tanggal berapa? Kalau price
    list berubah bulan depan, **HPP boning yang lama ikut berubah, atau
    dikunci saat costing dibuat?**

**Tentang yang belum masuk**

11. Bahan penolong (plastik, karton, drylog, karung) **memang tidak dihitung
    ke HPP**, atau dihitung di tempat lain yang belum kita lihat?
12. Upah produksi masuk ke mana?
13. Offal dan kulit ikut menyerap biaya beli, tetapi beratnya tidak ikut dalam
    pembagi `Gross Profit /kg` (`J92/F87`, yang hanya berat daging). Disengaja?

---

## 7. Ganjalan lain di berkasnya

- **Pembaginya tidak konsisten.** `G87 = F87/F91` (daging dibanding load
  weight -- rendemen). Tetapi `G88 = F88/F87` (offal dibanding **daging**,
  101%). Dua arti berbeda di kolom yang sama.
- **Rumus mati.** `H87 = I87*0,935` sementara `I87` kosong, jadi hasilnya 0.
- **Daftar harga tertanam di dalam form costing**, bukan diambil dari price
  list.
