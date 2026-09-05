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
        $byModule = static::query()->get()->groupBy('module_name');

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
