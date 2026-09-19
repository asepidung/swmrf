# Modul Expense (Pengeluaran Kas Kecil)

Ditulis 20 September 2026, sesudah issue #453 (4 langkah, PR #467-#470) --
modul baru, tidak menggantikan apa pun yang sudah ada. Keputusan Owner
18 September 2026 lewat Hafizh; rancangan lengkap ada di issue #453.

## 1. Prinsip: tanpa approval, kontrolnya keterlihatan

Modul ini mencatat uang keluar untuk kebutuhan operasional harian (air
minum, materai, obat, ATK, ongkir/parkir driver) -- nominal kecil, sering,
banyak orang berbeda yang menerima. Owner secara eksplisit TIDAK ingin
approval di sini. Kontrolnya tiga hal lain:

1. Hanya pemegang izin yang boleh mencatat (`create_expenses`).
2. Semuanya tercatat siapa memberi ke siapa (`recipient_name`, teks bebas
   -- OB/driver/siapa pun, tidak wajib punya akun).
3. **Daftar kasbon yang belum dikembalikan selalu terlihat** -- tab "Open
   Advances" di `ExpenseResource`, bukan terkubur di balik filter.

Tidak ada saldo tersimpan di mana pun. Setiap uang keluar/masuk adalah
`BankTransaction` (`reference_type` = `Expense`) -- Buku Kas dan saldo akun
kas otomatis benar, sama seperti modul uang lain di proyek ini
(`BankAccount::currentBalance()` selalu jumlah langsung dari baris
`bank_transactions`, tidak pernah kolom saldo).

## 2. Dua jenis dokumen

| Jenis | Alur | Efek kas |
|---|---|---|
| **Advance (kasbon)** | Uang diberikan dulu (`Expense::createAdvance()`) -> status `Open` -> penerima kembali dengan nota + sisa (`settle()`) -> `Settled` | Keluar sebesar uang muka saat dibuat; saat settle: sisa masuk kembali (`in`), atau kalau nota > uang muka selisihnya keluar tambahan (`out`); pas sama nominal = tidak ada transaksi tambahan |
| **Reimburse** | Penerima sudah bayar sendiri, bawa nota (`Expense::createReimburse()`) | Satu transaksi keluar sebesar nota; langsung `Settled` |

Biaya yang DILAPORKAN = **nominal nota** (`receipt_amount`), bukan nominal
uang yang dikeluarkan (`advance_amount`) -- keduanya beda saat kasbon,
sama saat reimburse. Ini yang dipakai rekap per kategori (§6).

Advance `Open` boleh **dibatalkan** (`cancel()`) hanya kalau uangnya
dikembalikan penuh (transaksi masuk sebesar uang muka, status
`Cancelled`) -- baris TETAP ADA, tidak dihapus. Menghapus (`delete()`)
sebuah advance `Open` justru mengembalikan uangnya otomatis (refund),
supaya tidak pernah ada `BankTransaction` yatim yang tertinggal.

## 3. Struktur data

- **`expense_categories`**: master kecil, `name` unik uppercase,
  `is_active`. Seed awal 6 kategori lewat migrasi (ATK, AIR MINUM,
  MATERAI, OBAT, ONGKIR DRIVER, PARKIR) -- bukan `db:seed`. Tidak bisa
  dihapus selama masih dipakai `Expense` manapun (guard `deleting()` di
  model, ditegakkan lewat `MasterDataDeletion::attempt()` di Resource).
- **`expenses`**: `expense_number` (`DocumentNumber`, prefix `EXP#`,
  padding 4), `expense_date`, `type` (`advance`/`reimburse`),
  `bank_account_id` (wajib, default `BankAccount::cashAccount()`),
  `expense_category_id` (wajib), `recipient_name` (teks bebas, uppercase),
  `user_id` (opsional, bila penerima punya akun), `advance_amount`,
  `receipt_amount`, `settled_at`, `settled_by`, `status`
  (`Open`/`Settled`/`Cancelled`), `description`, `receipt_photo`
  (opsional), `created_by`. SoftDeletes, LogsActivity. `balance_due`
  (turunan, bukan kolom) = `advance_amount - receipt_amount`.

## 4. Kunci

`Settled` dan `Cancelled` terkunci total -- tidak bisa diedit maupun
dihapus. Ditegakkan DUA lapis:

- **Model** (`Expense::booted()`): `updating()` menolak begitu status ASLI
  (`getOriginal('status')`) sudah `Settled`/`Cancelled`; `deleting()`
  menolak kalau status bukan `Open` (dan mengembalikan uang kalau memang
  `Open`).
- **Halaman** (`EditExpense`): pola sama dengan `EditGoodsReceiptMaterial`
  -- `mount()` menolak MEMBUKA halaman edit untuk yang sudah terkunci
  (redirect + notifikasi), `beforeSave()` membaca ULANG barisnya dengan
  `lockForUpdate()` sebelum menyimpan (menutup celah tab yang sudah
  terbuka sebelum expense-nya terkunci dari sesi lain).

Field finansial (`type`, `bank_account_id`, `advance_amount`,
`receipt_amount`) dikunci lagi begitu record sudah dibuat -- keputusan
implementasi saya sendiri, bukan permintaan eksplisit issue. Alasannya:
`BankTransaction`-nya sudah tercatat berdasarkan nilai-nilai itu sejak
`createAdvance()`/`createReimburse()`; mengizinkan diketik ulang lewat
form Edit akan membuat dokumen berbeda dari buku kas tanpa jejak apa pun.
Mengubah jumlahnya yang sah cuma lewat `settle()`, `cancel()`, atau hapus
-- yang SEMUANYA menulis `BankTransaction` penyeimbangnya sendiri.

`settle()` dan `cancel()` sendiri dibungkus `DB::transaction()` +
`lockForUpdate()` pada baris expense-nya -- klik ganda tombol Settle tidak
membuat dua transaksi kas (`Only an open advance can be settled.` pada
percobaan kedua).

## 5. Layar

- **`ExpenseResource`** (grup FINANCE, dekat Invoices): tabel nomor,
  tanggal, jenis, kategori, penerima, uang muka, nota, sisa, status.
  Tab "All" dan **"Open Advances"** (kasbon belum kembali, prinsip §1).
  Filter status, kategori, silent date filter bulan berjalan (rujukan
  `CashBookResource`), `TrashedFilter` digerbangi `view_deleted_expenses`.
- **Create**: lewat `Expense::createAdvance()`/`createReimburse()`
  (`CreateExpense::handleRecordCreation()`), bukan `create()` polos --
  supaya `BankTransaction` ikut lahir.
- **Aksi baris**: **Settle** (modal nominal nota + upload foto nota) dan
  **Batalkan**, keduanya untuk status `Open` saja, digerbangi
  `edit_expenses` rangkap (`visible()` DAN diperiksa ulang di dalam
  `action()`).
- **`ExpenseCategoryResource`**: master kecil, SATU izin
  (`manage_expense_categories`) untuk seluruh aksi -- keputusan eksplisit
  issue, bukan 4 izin terpisah seperti master data lain. Ditaruh di grup
  FINANCE (bukan MASTER DATA generik) karena `module_name` permission-nya
  sama dengan `Expense` sendiri.
- **Halaman View**: ringkasan expense + daftar `BankTransaction` yang
  lahir dari dokumennya (infolist, pola sama dengan `StockTakeResource`).

### Foto nota: disk `local`, bukan `public`

Tidak ada precedent `FileUpload` sama sekali di app ini sebelum modul ini
-- keputusan desain baru yang saya buat sendiri, dilaporkan (bukan
ditanya lebih dulu, karena bukan kontradiksi). App ini konsisten
menggerbangi dokumen keuangan lewat route berizin (print/PDF), bukan disk
publik yang bisa ditebak siapa pun. `receipt_photo` disimpan di disk
`local` (`storage/app/private`, tidak ada URL publik sama sekali), dengan
`visibility('private')`. Karena disk `local` TIDAK mendukung
`temporaryUrl()` (limitasi driver Laravel, bukan bug), thumbnail bawaan
Filament diganti lewat `getUploadedFileUsing()` supaya tidak crash saat
membuka form Edit untuk record yang sudah punya foto -- URL-nya menunjuk
ke route `expense.receipt-photo` (izin `view_expenses`), bukan
`$storage->url()` langsung.

### Cetak, ekspor, rekap (langkah 3)

- **`expense.print`** (izin `view_expenses`): "Bukti Kas Keluar", HTML
  biasa (pola sama dengan `sales-return-plan.print` -- dicetak lewat
  dialog print browser, bukan digenerate PDF di server). Sengaja TANPA
  baris approval di tanda tangannya (cuma "Diterima Oleh"/"Dicatat Oleh"),
  konsisten dengan prinsip §1.
- **Ekspor Excel + PDF** pada tabel, mengikuti filter aktif lewat
  `getFilteredTableQuery()` (pola sama dengan `CashBookResource`).
- **Rekap per Kategori**: menjumlah `receipt_amount` (nominal NOTA) per
  kategori, HANYA expense `Settled` -- advance yang masih `Open` belum
  punya biaya nyata untuk direkap (lihat §2 soal nota vs uang muka).

## 6. Izin

Enam izin baru (migrasi, bukan seeder --
`2026_09_20_090200_create_the_expense_permissions.php` +
`2026_09_20_150000_...` untuk `view_deleted_expenses`, sengaja ditunda ke
langkah 2 sampai ada `TrashedFilter` yang membacanya): `view_expenses`,
`create_expenses`, `edit_expenses`, `delete_expenses`,
`view_deleted_expenses`, `manage_expense_categories`. Belum dilekatkan ke
siapa pun -- dicatat di `tertunda.md` §G.

## 7. Yang TIDAK dikerjakan (keputusan Owner)

- Approval/ambang nominal -- Owner eksplisit: tidak perlu.
- Kaitan ke DO/driver tertentu untuk ongkir -- kategori saja dulu, kolom
  opsional bisa ditambah nanti kalau memang dibutuhkan.
- Anggaran per kategori.
