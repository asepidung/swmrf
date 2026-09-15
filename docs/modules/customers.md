# Modul Customers (Pelanggan)

Ditulis ulang 15 September 2026 -- versi sebelumnya menggambarkan fitur yang
tidak pernah diimplementasikan (Email, Global Search, halaman View/Infolist).
Dokumen ini mengikuti kolom & perilaku yang sungguhan berlaku sekarang.

Cluster **Customers** terdiri dari tiga Resource: `CustomerResource`,
`CustomerGroupResource`, dan `CustomerSegmentResource`.

## 1. Struktur data

- **`customers`**: `name`, `customer_group_id`, `customer_segment_id`,
  `address`, `top` (Term of Payment, hari), `default_discount`, `pic`,
  `phone`, `required_documents` (array), `invoice_exchange` (boolean),
  `is_active`. Tidak ada kolom Email. `is_taxable` sempat ada tapi dihapus
  15 September 2026 -- tidak pernah bisa diisi lewat form, dan Wijaya Meat
  berstatus non-PKP sehingga penjualan memang tidak dikenai PPN.
- **`customer_groups`**: `name` (unique), `head_office_address`,
  `head_office_pic`, `top`. Satu-satunya jalan menuju harga -- `price_lists`
  dikunci ke grup, bukan ke customer perorangan.
- **`customer_segments`**: `name` saja.
- `name`/`address`/`pic`/`head_office_*` dipaksa UPPERCASE lewat mutator di
  model, bukan cuma CSS.

## 2. Aturan bisnis

1. **Setiap Customer wajib punya grup** (keputusan Ayah, 15 September 2026).
   Field `customer_group_id` `->required()` di form Create & Edit. Data lama
   yang sempat tanpa grup dibereskan migrasi backfill
   (`2026_09_15_130000_...`): dibuatkan `CustomerGroup` baru bernama sama
   dengan Customer-nya. Alasannya: `ReceivableResource` dibangun di atas
   model `CustomerGroup` -- Customer tanpa grup piutangnya tidak pernah
   muncul di modul Piutang sama sekali.
2. **Grup baru otomatis dari nama**, kalau field grup dikosongkan lewat jalur
   selain form (`KeepsCustomerInAGroup::ensureCustomerGroup()`) -- dipakai
   `Create`/`EditCustomer`. Sejak field-nya wajib di form, jalur ini praktis
   hanya relevan untuk pemanggilan di luar form (import, tinker).
3. **Lifecycle Aktif/Nonaktif**: Customer tidak dihapus permanen kalau sudah
   pernah bertransaksi -- tombol Delete disembunyikan (`EditCustomer`) dan
   bulk delete melewati baris yang punya `salesOrders()`
   (`CustomerResource::table()`).
4. **Hapus CustomerGroup ditolak** kalau masih punya Customer, PriceList,
   Receivable, atau Payment (`CustomerGroup::isInUse()`) -- baik lewat Delete
   tunggal maupun bulk. Hapus CustomerSegment yang masih dipakai Customer
   ditolak lewat `MasterDataDeletion` (pesan ramah, bukan galat SQL) karena
   `customers.customer_segment_id` RESTRICT.
5. **Jejak audit**: `Customer`, `CustomerGroup`, `CustomerSegment` memakai
   `LogsActivity` (sejak 15 September 2026) -- data yang menentukan TOP,
   diskon, dan grup harga sekarang tercatat siapa mengubah apa.

## 3. UI

- Ekspor Excel tersedia di ketiga Resource (`CustomerResource` sudah lama
  punya, `CustomerGroupResource`/`CustomerSegmentResource` ditambahkan
  15 September 2026). Tidak ada ekspor PDF.
- Tidak ada Global Search, tidak ada halaman View/Infolist -- hanya
  `index`/`create`/`edit` di ketiga Resource.
- Dukungan bilingual (`lang/en.json` + `lang/id.json`) seperti standar
  seluruh panel.
