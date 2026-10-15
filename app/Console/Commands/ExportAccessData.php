<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ekspor data AKSES dari basis data lama ke satu berkas JSON, untuk dipindahkan ke
 * basis data baru yang bersih (`access:import`) -- gladi resik DB produksi VPS dan
 * pembuatan DB baru untuk coba./app. (13 Oktober 2026).
 *
 * Hanya empat hal: users, permissions, pivot user-permission, dan langganan push.
 * Data transaksi latihan TIDAK ikut. Pivot ditulis dengan NAMA izin (bukan hanya id),
 * karena id izin di DB baru bisa berbeda dari DB lama.
 *
 * BERKAS HASILNYA BERISI HASH PASSWORD DAN KUNCI LANGGANAN PUSH. Jangan dimasukkan ke
 * repositori, jangan dikirim lewat chat; simpan di luar folder publik dan hapus setelah
 * dipakai. Default-nya `storage/app/access-export.json` (di luar git).
 */
class ExportAccessData extends Command
{
    protected $signature = 'access:export {--file= : Path berkas JSON hasil (bawaan: storage/app/access-export.json)}';

    protected $description = 'Ekspor users, permissions, pivot user-permission, dan push subscriptions ke berkas JSON';

    public function handle(): int
    {
        $file = $this->option('file') ?: storage_path('app/access-export.json');

        $permissions = DB::table('permissions')->orderBy('id')->get();
        $permissionNames = $permissions->pluck('name', 'id');

        $data = [
            'exported_at' => now()->toIso8601String(),
            'users' => DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'permissions' => $permissions->map(fn ($row) => (array) $row)->all(),
            'user_permissions' => DB::table('user_permissions')->orderBy('id')->get()
                ->map(fn ($row) => ['user_id' => $row->user_id, 'permission' => $permissionNames[$row->permission_id] ?? null])
                ->filter(fn ($row) => $row['permission'] !== null)
                ->values()
                ->all(),
            'push_subscriptions' => DB::table('push_subscriptions')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];

        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Diekspor ke {$file}");
        $this->line('  users: '.count($data['users']).' | permissions: '.count($data['permissions']).' | pivot: '.count($data['user_permissions']).' | push subscriptions: '.count($data['push_subscriptions']));
        $this->warn('Berkas ini berisi hash password dan kunci push -- JANGAN masuk repositori; hapus setelah dipakai.');

        return self::SUCCESS;
    }
}
