<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * Dua kewenangan yang berbeda, dan sengaja tidak digabung.
 *
 * `set_repack_yield_limit` MENENTUKAN apa yang wajar. Itu keputusan mutu, dan
 * pemegangnya QC.
 *
 * `override_repack_yield` MELEWATI batas itu untuk satu dokumen, dengan alasan
 * tertulis. Itu keputusan operasional atas satu kejadian.
 *
 * Menggabungkannya berarti siapa pun yang boleh menembus juga boleh menurunkan
 * ambangnya sampai tidak ada lagi yang perlu ditembus -- dan penjagaannya
 * lenyap tanpa satu pun catatan penembusan.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'name' => 'set_repack_yield_limit',
            'module_name' => 'Repack',
            'description' => 'Set the reasonable shrinkage limit for repack batches',
        ],
        [
            'name' => 'override_repack_yield',
            'module_name' => 'Repack',
            'description' => 'Lock a repack whose shrinkage exceeds the limit, with a written reason',
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_column(self::PERMISSIONS, 'name'))->delete();
    }
};
