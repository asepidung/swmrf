<p align="center">
<a href="https://trakteer.id/saepullrock"><img src="https://img.shields.io/badge/Support_me-Trakteer-red?style=for-the-badge&logo=ko-fi" alt="Support on Trakteer"></a>
</p>

# Wijaya Meat (SWM)

Sistem ERP untuk **Wijaya Meat**, produsen daging sapi. Menangani seluruh siklus operasional mengikuti perjalanan barang secara fisik:

```
Sapi hidup → Terima & Timbang → Karkas → Boning → Repack/Relabel
          → Stok → Tally → Delivery Order → Invoice → Piutang
```

Proyek ini adalah modernisasi sistem lama berbasis PHP prosedural + AdminLTE 3, dikerjakan bertahap dengan **Strangler Pattern**.

## Tech Stack

- **Laravel 11** — Eloquent ORM dan Migration
- **Filament v3** — panel admin
- **MySQL** — basis data
- **Tailwind CSS** + **Vite**

## Modul

| Kelompok | Isi |
|---|---|
| **Master Data** | Users, Suppliers, Customers, Products, Materials, Cattle Class, Bank Account |
| **Procurement** | Requisition, PO Cattle/Beef/Material, Goods Receipt |
| **Production** | Cattle Receive, Weighing, Carcass, Boning, Repack, Relabel |
| **Inventory** | Beef Stock, Material Stock, Mutation, Stock Take |
| **Sales** | Sales Order, Tally, Delivery Order, Delivery Plan, Sales Return, Price List |
| **Finance** | Invoice, Payment, Receivable, Payable, Bank Transaction |

Penjelasan rinci tiap modul ada di [`docs/modules/`](docs/modules/).

## Menjalankan Secara Lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Akun superuser bawaan: username `saepullrock`, password `1234`. Aplikasi akan **memaksa penggantian password pada login pertama**.

> Password bawaan sengaja dibuat sepele dan **wajib tetap `1234`**. Repositori ini publik, jadi tidak boleh ada password sungguhan di dalamnya.

## Testing

```bash
php artisan test
```

Test berjalan di atas SQLite `:memory:` (lihat `phpunit.xml`) dan tidak menyentuh database MySQL utama. Karena itu **seluruh migrasi wajib memakai sintaks yang didukung MySQL maupun SQLite**.

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [`project.md`](project.md) | Aturan main pengembangan — wajib dibaca sebelum berkontribusi |
| [`.agents/agents.md`](.agents/agents.md) | Riwayat keputusan beserta alasannya |
| [`docs/modules/`](docs/modules/) | Penjelasan rinci tiap modul |

## Kontribusi

Kerjakan di branch `feature/issue-[nomor]` dan ajukan sebagai Pull Request. **Dilarang commit langsung ke `main`** — push ke `main` memicu auto-deploy beserta `php artisan migrate --force` ke server uji coba.

Baca [`project.md`](project.md) lebih dulu.
