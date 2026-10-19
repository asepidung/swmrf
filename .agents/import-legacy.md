# Aturan import data legacy (latihan di coba., dipakai untuk import final ke VPS)

Dicatat per tabel: sumber -> tujuan, kolom, ID, keputusan Ayah. Dokumen ini yang dipakai pada import final.

## customers (keputusan Ayah, 19 Oktober 2026; belum diimpor, menunggu giliran)

Sumber: `customers` di snapshot legacy -> `customers`, id = idcustomer (aturan kolom ada di `.agents/agents.md`,
bagian "customers dengan id legacy").

**Nama pelanggan harus unik** (formulir menolak dengan pesan, dan database punya indeks unik). Snapshot 16 Oktober:
306 baris, 1 tanpa nama, 11 baris memakai nama yang sudah dipakai baris lain.

- **Nama kembar yang sama dengan pelanggan lain: digabung, yang dipakai adalah yang PALING BARU diperbarui**
  (`tanggal_update` terbaru; sama -> id terbesar). Bukan id terkecil: tiga cabang HPM yang lama masih TOP 4 hari,
  yang baru TOP 14 hari.
  - ABUBA - CIPETE: 218, 219 -> pakai 219
  - ABUBA - CITRA GARDEN: 226, 227 -> pakai 227
  - BULL & VINE STEAKHOUSE: 229, 230 -> pakai 230
  - HPM CYBERPARK KRWC: 121, 204 -> pakai 204
  - GRANDLUCKY - LIVING PLAZA CINERE: 209, 213 -> pakai 213
  - HPM PURI INDAH: 146, 207 -> pakai 207
  - HPM GADING SERPONG: 148, 205 -> pakai 205
  - HPM KARAWACI: 150, 208 -> pakai 208
  - NENENG(IBU): 194, 294 -> pakai 294
- **CIPTA BOGA PRIMA PT (135, 182, 188): bukan kembar biasa, tiga alamat kirim berbeda -> TETAP TIGA pelanggan**
  dengan nama diberi pembeda: 135 "CIPTA BOGA PRIMA PT (PASAR REBO)", 182 "CIPTA BOGA PRIMA PT (GEDANGAN)",
  188 "CIPTA BOGA PRIMA PT (VIA EKSPEDISI ELVANO)".
- **Peta id lama -> id yang dipakai** (mis. 204 untuk 121) WAJIB dibuat dan dipakai importer riwayat, dokumen terbuka,
  dan stok awal, supaya transaksi lama (invoice, Sales Order) tidak kehilangan pelanggannya. Dihitung otomatis dari
  snapshot dengan aturan yang sama (bukan ditulis tangan), dan dites.
- Pelanggan baru di aplikasi: nama yang sama persis ditolak. Yang beda hanya spasi/tanda baca ("CV. X" vs "CV.X")
  lolos formulir; cabang dari satu perusahaan harus diberi pembeda di nama seperti contoh Cipta Boga.

## Supir dan armada (data awal dari Owner, 19 Oktober 2026; tidak ada di legacy)

Perintah `php artisan master:seed-fleet` (dry-run bawaan; `--apply` menulis; aman diulang; nama/plat dibandingkan longgar;
plat yang sama dengan jenis berbeda = konflik, tidak ditimpa). Datanya ada di `App\Console\Commands\SeedFleet`:

- Supir (tabel `drivers`: name): ACEP NURFADILLAH, RESKHA PERMANA.
- Armada (tabel `vehicles`: vehicle_type, police_number): CANTER - F 8485 HD, TRAGA - F 9385 FI, L-300 - F 8489 HV,
  DUTRO - F 9201 F.
- Semua aktif. Tidak ada masalah yang perlu keputusan. Hasil yang harus cocok: 2 supir dan 4 armada.
