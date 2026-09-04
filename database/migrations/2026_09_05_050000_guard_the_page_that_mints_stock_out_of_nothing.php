<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * Mencetak stok punya izinnya sendiri.
 *
 * `FoundItemScanner` -- "Product Tracking (Cek Barcode)" di menu -- bukan
 * halaman baca. Ia membuat baris `BeefStock` BARU dari isian orang: barang
 * yang ditemukan di gudang tetapi tidak pernah tercatat. Itu satu-satunya
 * tempat di aplikasi ini yang menambah persediaan tanpa dokumen asal.
 *
 * Sampai 5 September 2026 ia hanya dijaga gerbang clusternya, dan gerbang itu
 * berisi izin MELIHAT: `view_beef_stocks`, `view_beef_stock_movements`, atau
 * `view_beef_stock_aging`. Jadi siapa pun yang boleh melihat stok juga boleh
 * mencetaknya.
 *
 * Bentuk yang sama sudah ditambal pada Approve retur dan Lock repack:
 * kewenangan yang mengubah angka sungguhan menumpang kewenangan yang hanya
 * membaca.
 */
return new class extends Migration
{
    private const PERMISSION = [
        'name' => 'record_found_items',
        'module_name' => 'Beef Stocks',
        'description' => 'Record goods found in the warehouse that were never recorded',
    ];

    public function up(): void
    {
        Permission::updateOrCreate(['name' => self::PERMISSION['name']], self::PERMISSION);
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION['name'])->delete();
    }
};
