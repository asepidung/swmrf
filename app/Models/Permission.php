<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * Tab penampung modul yang belum dipetakan ke grup mana pun.
     *
     * Modul tak terpeta SENGAJA tetap ditampilkan, bukan dibuang. Kalau
     * dibuang, haknya tidak bisa diberikan sama sekali dan tidak ada yang
     * menyadarinya -- persis jenis kegagalan senyap yang paling mahal.
     * Ada test yang memastikan tab ini selalu kosong.
     */
    public const UNGROUPED = 'OTHERS';

    /**
     * Izin yang ADA di basis data tetapi TIDAK ditampilkan di form.
     *
     * Semuanya izin yang tidak dibaca satu baris kode pun. Selama ia tampil,
     * centangnya bisa diberikan dan tidak berakibat apa-apa -- dan orang yang
     * memberikannya percaya sudah memberi hak yang sebenarnya tidak pernah
     * sampai. Itu lebih buruk daripada tidak ada centangnya sama sekali.
     *
     * **Barisnya TIDAK dihapus dari basis data.** Menghapusnya ikut memutus
     * lekatannya ke pengguna yang telanjur dicentang, dan lekatan itu tidak
     * bisa dikembalikan tanpa mencentang ulang satu per satu. Yang
     * disembunyikan cuma tampilannya, dan mengembalikannya nanti cukup
     * dengan mencabut namanya dari daftar ini.
     *
     * Keputusan Owner, 6 September 2026, menyerahkan pilihannya: "lu kasih
     * keputusan terbaik lah".
     *
     * Alasannya ditulis bersama namanya. Daftar tanpa alasan akan berubah
     * menjadi tempat pembuangan bagi izin yang sekadar belum sempat
     * dikerjakan -- dan itu persis kebalikan dari gunanya.
     *
     * @var array<string, string>
     */
    public const TIDAK_DITAMPILKAN = [
        // Stok dan pergerakannya tidak memakai hapus lunak sama sekali. Stok
        // hanya mencatat posisi sekarang -- keputusan Owner, supaya tabelnya
        // tetap ringan -- dan pergerakan stok justru tidak boleh dihapus
        // karena ia jejak auditnya. Tidak ada baris terhapus untuk dilihat.
        'view_deleted_beef_stocks' => 'stok daging tidak memakai hapus lunak',
        'view_deleted_beef_stock_movements' => 'pergerakan stok daging tidak pernah dihapus',
        'view_deleted_material_stocks' => 'stok material tidak memakai hapus lunak',
        'view_deleted_material_stock_movements' => 'pergerakan stok material tidak pernah dihapus',

        // Kedua layar ini berdiri di atas CustomerGroup, bukan di atas dokumen
        // yang namanya disebut izin ini -- dan CustomerGroup tidak memakai
        // hapus lunak.
        'view_deleted_price_lists' => 'layarnya berdiri di atas CustomerGroup',
        'view_deleted_receivables' => 'layarnya berdiri di atas CustomerGroup',

        // Tabel dan modelnya ada, layarnya tidak pernah dibuat.
        'view_material_adjustments' => 'layar Material Adjustment belum dibuat',
        'create_material_adjustments' => 'layar Material Adjustment belum dibuat',
        'edit_material_adjustments' => 'layar Material Adjustment belum dibuat',
        'delete_material_adjustments' => 'layar Material Adjustment belum dibuat',
        'view_deleted_material_adjustments' => 'layar Material Adjustment belum dibuat',

        // Keputusan Owner: "user mah jangan ada hapus aktif non aktif aja".
        // `UserPolicy::delete()` karena itu selalu menjawab tidak, siapa pun
        // yang bertanya. Barisnya dipertahankan sebagai contoh penamaan bagi
        // modul lain; alasan lengkapnya ada di policy-nya.
        'delete_users' => 'pengguna dinonaktifkan, tidak dihapus',
    ];

    protected $fillable = ['name', 'module_name', 'description'];

    /**
     * The users that belong to the permission.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }

    /**
     * Pengelompokan modul hak akses, mengikuti grup sidebar.
     *
     * Urutannya sengaja sama persis dengan `navigationGroups()` di
     * AdminPanelProvider. Alasannya bukan kerapian: admin yang memberi hak
     * akses sedang membayangkan menu apa yang nanti dilihat pengguna, jadi
     * susunan yang sama membuat hasilnya bisa ditebak tanpa mencoba dulu.
     *
     * Nama modul di sini harus sama persis dengan `permissions.module_name`
     * yang di-seed di DatabaseSeeder.
     *
     * @return array<string, array<int, string>>
     */
    public static function moduleGroups(): array
    {
        return [
            'REQUEST' => [
                'Product Requisition',
                'Material Requisition',
            ],
            'PURCHASE ORDER' => [
                'PO Beef',
                'PO Material',
                'PO Cattle',
            ],
            'GOODS RECEIPT' => [
                'GR Beef',
                'GR Material',
            ],
            'CATTLE' => [
                'Cattle Receiving',
                'Cattle Weighing',
                'Carcass',
            ],
            'PRODUCTION' => [
                'Boning',
                'Repack',
                'Material Usages',
            ],
            'WAREHOUSE' => [
                'Mutations',
                'Stock Takes',
                'Material Stock Takes',
                'Material Adjustments',
            ],
            'STOCKS' => [
                'Beef Stocks',
                'Beef Stock Movements',
                'Beef Stock Aging',
                'Material Stocks',
                'Material Stock Movements',
                'Material Findings',
            ],
            'DISTRIBUTION' => [
                'Delivery Plans',
                'Delivery Orders',
                'Tallies',
            ],
            'SALES' => [
                'Sales Orders',
                'Sales Returns',
                'Price Lists',
            ],
            'FINANCE' => [
                'Invoices',
            ],
            // Empat modul di bawah memakai grup ACCOUNTING di Resource-nya,
            // bukan FINANCE. Peta ini HARUS mengikuti grup yang benar-benar
            // dipakai Resource, bukan tebakan -- ada test yang menjaganya.
            'ACCOUNTING' => [
                'Payables',
                'Receivables',
                'Bank Accounts',
                'Cash Book',
                'Financial Loss',
            ],
            'QC' => [
                'QC Reports',
            ],
            'REPORTS' => [
                'Reports',
            ],
            'MASTER DATA' => [
                'Products',
                'Product Categories',
                'Grades',
                'Warehouses',
                'Suppliers',
                'Customers',
                'Customer Groups',
                'Customer Segments',
                'Cattle Classes',
                'Logistic Items',
            ],
            'SYSTEM' => [
                'Users',
                'Activity Logs',
                'System',
            ],
        ];
    }

    /** Grup sidebar tempat sebuah modul bernaung, atau UNGROUPED bila belum dipetakan. */
    public static function groupFor(string $moduleName): string
    {
        foreach (static::moduleGroups() as $group => $modules) {
            if (in_array($moduleName, $modules, true)) {
                return $group;
            }
        }

        return static::UNGROUPED;
    }

    /**
     * Seluruh permission, dikelompokkan per grup sidebar lalu per modul.
     *
     * Grup yang tidak punya satu pun permission dibuang, supaya tab kosong
     * tidak ikut tampil di form.
     *
     * @return array<string, array<string, \Illuminate\Support\Collection>>
     */
    public static function groupedByModuleGroup(): array
    {
        // Yang tidak berakibat apa-apa tidak ikut tampil. Alasan tiap
        // namanya ada di `TIDAK_DITAMPILKAN`.
        $byModule = static::query()
            ->whereNotIn('name', array_keys(static::TIDAK_DITAMPILKAN))
            ->get()
            ->groupBy('module_name');

        $result = [];

        // Ditelusuri mengikuti urutan moduleGroups() supaya urutan tabnya
        // stabil dan sama dengan sidebar, bukan urutan abjad.
        foreach (static::moduleGroups() as $group => $modules) {
            foreach ($modules as $moduleName) {
                if ($byModule->has($moduleName)) {
                    $result[$group][$moduleName] = $byModule->get($moduleName);
                }
            }
        }

        foreach ($byModule as $moduleName => $permissions) {
            if (static::groupFor($moduleName) === static::UNGROUPED) {
                $result[static::UNGROUPED][$moduleName] = $permissions;
            }
        }

        return $result;
    }
}
