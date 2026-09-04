<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boning akhirnya bisa dinilai hasilnya.
 *
 * Berat karkas yang masuk SUDAH tercatat sejak lama -- di `carcass_items`,
 * per ekor, sebagai belahan A, belahan B, kulit, dan buntut. Yang tidak ada
 * hanyalah satu baris kode yang membacanya: sampai 4 September 2026 kata
 * `weight` cuma muncul SEKALI di seluruh `BoningResource`, dan itu pun
 * `->weight('bold')` -- ketebalan huruf, bukan berat.
 *
 * Kolomnya sama persis dengan yang dipakai Repack, dan itu disengaja: dua
 * proses yang menjawab pertanyaan yang sama sebaiknya dijawab dengan cara
 * yang sama.
 *
 * Izinnya pun dipisah dengan alasan yang sama -- menentukan apa yang wajar
 * adalah keputusan mutu, melewatinya untuk satu dokumen adalah keputusan atas
 * satu kejadian. Digabung berarti siapa pun yang boleh menembus juga boleh
 * menurunkan ambangnya sampai tidak ada lagi yang perlu ditembus.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'name' => 'set_boning_yield_limit',
            'module_name' => 'Boning',
            'description' => 'Set the reasonable shrinkage limit for boning batches',
        ],
        [
            'name' => 'override_boning_yield',
            'module_name' => 'Boning',
            'description' => 'Lock a boning whose shrinkage exceeds the limit, with a written reason',
        ],
    ];

    public function up(): void
    {
        Schema::table('bonings', function (Blueprint $table) {
            $table->text('yield_override_reason')->nullable()->after('note');
            $table->foreignId('yield_override_by')->nullable()->after('yield_override_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('yield_override_at')->nullable()->after('yield_override_by');
        });

        foreach (self::PERMISSIONS as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', array_column(self::PERMISSIONS, 'name'))->delete();

        Schema::table('bonings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('yield_override_by');
            $table->dropColumn(['yield_override_reason', 'yield_override_at']);
        });
    }
};
