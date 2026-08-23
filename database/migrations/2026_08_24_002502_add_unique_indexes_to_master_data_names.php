<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data tidak boleh punya identitas kembar.
 *
 * Keempat kolom di bawah sudah divalidasi unique di form Filament, tetapi
 * tidak punya pengaman di level database. Validasi form saja tidak mengikat:
 * dua permintaan yang tiba bersamaan bisa sama-sama lolos, dan penyisipan
 * lewat seeder, import, atau tinker melewatinya sama sekali.
 *
 * warehouses.name bahkan belum punya validasi form sekalipun.
 *
 * Catatan: users.name SENGAJA tidak diikutkan. Dua orang boleh bernama sama;
 * yang menjadi identitas adalah username, dan kolom itu sudah unique.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $targets = [
        'suppliers' => 'name',
        'customers' => 'name',
        'materials' => 'name',
        'warehouses' => 'name',
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                $blueprint->unique($column, "{$table}_{$column}_unique");
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                $blueprint->dropUnique("{$table}_{$column}_unique");
            });
        }
    }
};
