# Barcode 26 digit vs label berat ratusan-ribuan kilo

Diangkat Owner 30 September 2026: label OFFAL, KULIT, dan BONE sering
digabung sehingga beratnya jauh melampaui 99,99 kg, padahal barcode swmrf
hanya menyediakan 4 digit untuk berat. **Status: data dan rencana dikumpulkan,
BELUM dikerjakan** (Owner: "jangan dulu dikerjain, kita kumpulin data dulu").

## Susunan barcode swmrf (26 digit)

```
origin(1) + ddmmyy(6) + kode produk(6) + grade(1) + berat(4) + pcs(2) + pH(2) + urutan(4)
                                                      ^ berat x 100, maks 99,99 kg
```

Dibangun di tujuh tempat (`str_pad(round($weight * 100), 4, ...)`):
`LabelingBoning`, `LabelingGoodsReceiptProduct`, `InputHasilRepack`,
`InputReturnItems`, `ScanStockTake`, `ScanTally` (relabel), `FoundItemScanner`.

`str_pad` **tidak memotong**: berat 5.747,66 kg menjadi segmen `574766` dan
barcodenya 28 digit, tanpa galat. Yang rusak kemudian:

1. `BarcodeHelper::getOrigin()` mewajibkan tepat 26 digit -> asal label
   tercatat `-UNIND`.
2. `ScanStockTake` menolak / menganggap asing barcode yang panjangnya bukan 26
   (baris 203, 364, 368, 388, 540) -> saat opname label offal diperlakukan
   seperti barcode supplier dan dibuatkan barcode baru.
3. `ScanStockTake` dan `FoundItemScanner` membaca berat/pcs/pH menurut
   POSISI (`substr($barcode, 14, 4)` dst.) -> untuk barcode 28 digit semua
   posisi sesudah berat bergeser dua karakter.
4. `pcs` juga cuma 2 digit (maks 99).

Hosting swmrf (`coba.`) per 30 Sep: hanya 4 barcode, semuanya 26 digit --
belum ada data yang rusak.

## Kenapa legacy tidak pernah kena

Barcode legacy (19-20 digit) = asal + nomor batch + tanggal + urutan. **Tidak
memuat berat.** Berat hanya di kolom `labelboning.qty`. Contoh:
`1456260928000048310`.

## Data produksi (`labelboning`, `is_deleted = 0`, dibaca 30 Sep)

| Barang | Label | < 100 kg | 100-999 | 1.000-9.999 | Terberat | Label per boning |
|---|---|---|---|---|---|---|
| BONE (37) | 2.960 | 2.585 | 375 | 0 | 591,94 | 6,95 |
| OFFAL (49) | 544 | 0 | 11 | 533 | 9.979,00 | 1,25 |
| KULIT (50) | 469 | 7 | 307 | 155 | 1.978,70 | 1,08 |

Label yang dihapus (`is_deleted = 1`) untuk ketiga barang: 235, dikeluarkan.

**Uji salah ketik -- kg per ekor per boning** (total label barang itu dibagi
`boning.qtysapi`):

| Barang | Boning | Min | Rata-rata | Maks |
|---|---|---|---|---|
| BONE | 426 | 14,3 | 19,9 | 27,3 |
| OFFAL | 435 | 221,7 | 301,3 | 745,1 |
| KULIT | 435 | 23,6 | 37,1 | 84,0 |

Kesimpulannya: **berat ribuan kilo itu ASLI, bukan salah ketik** -- offal
stabil sekitar 300 kg/ekor, kulit sekitar 37 kg/ekor. Pencilan yang
kemungkinan salah ketik hanya segelintir:

- BN0050 (11 Jan 2024, 12 ekor): offal 8.941 kg = 745 kg/ekor, kulit
  1.008 kg = 84 kg/ekor -- keduanya dua kali lipat normal.
- BN0063 (8 Feb 2024, 28 ekor): offal 12.180 kg dalam 2 label = 435
  kg/ekor -- kemungkinan satu label tercetak dua kali.
- BONE di atas 100 kg: 358 label di pita 100-199 (karung), 17 label
  200-592 kg (mayoritas Agustus-Oktober 2023, awal pemakaian).
- Produk LAIN di atas 100 kg hanya 5 label seumur aplikasi: FQ 85 CL CUT
  485 kg, CHUCK 100 kg / "100-Pc" (dua kali), PAHA DEPAN 117 dan 118 kg --
  hampir pasti salah ketik.
- `pcs` >= 100: 2 label (CHUCK "100-Pc" di atas).

Jadi kebutuhannya jelas: **tiga barang (offal, kulit, bone) butuh berat
sampai < 10.000 kg per label**; barang lain tidak pernah sah melewati 100 kg.

## Rencana solusi (untuk dibahas besok)

### Pilihan A (usulan Hafizh) -- barcode tetap 26 digit, segmen berat `9999` = "lihat database"

- Berat >= 100 kg -> segmen berat ditulis `9999`; pcs >= 100 -> `99`. Berat
  sebenarnya tetap utuh di `weight` / `qty_pcs`, seperti legacy.
- Semua pembaca posisi tetap benar; `getOrigin()` dan opname tidak berubah.
- Pembaca yang menemukan `9999` (FoundItemScanner, opname untuk barcode
  tanpa data) menganggap berat tidak diketahui dan meminta operator
  mengetiknya.
- Keunikan aman: urutan 4 digit di ujung membedakan label dengan awalan yang
  sama (maks 9.999 per produk+grade+hari -- offal 1-2 label per hari).
- Satu rumah untuk menyusun segmen (mis. `BarcodeSegments::weight()`), ketujuh
  tempat memakainya, dan penjaga yang melarang `str_pad(round($weight * 100)`
  muncul lagi di luar rumah itu.

### Pilihan B -- perlebar segmen berat jadi 6 digit (barcode 28 digit)

- Semua label BARU 28 digit; label lama tetap 26 -> dua format hidup
  bersamaan, setiap pembaca harus membedakan menurut panjang.
- `getOrigin()`, `ScanStockTake` (5 titik), `FoundItemScanner`, template
  label, dan semua test yang mengandaikan 26 harus diubah.
- Lebih banyak risiko untuk sesuatu yang sebenarnya tidak dibutuhkan barcode
  (berat sudah ada di database).

### Tambahan yang layak apa pun pilihannya

- **Peringatan salah ketik per produk**: berat per label di atas batas wajar
  (mis. > 100 kg untuk produk selain offal/kulit/bone) -> minta konfirmasi,
  bukan menolak. Datanya mendukung: di luar tiga barang itu, >= 100 kg selalu
  salah ketik. Batasnya bisa jadi kolom `products.max_label_weight` (null = 100).
- Barang mana yang boleh berat besar sebaiknya DATA (kolom produk), bukan
  daftar id di kode -- id legacy 37/49/50 tidak akan sama di swmrf.

## Yang perlu diputuskan Owner

1. Pilihan A atau B.
2. Peringatan salah ketik: dipasang atau tidak, dan batas bawaannya.
3. Label offal/kulit yang digabung: satu label per boning (seperti sekarang)
   tetap cara kerjanya?
