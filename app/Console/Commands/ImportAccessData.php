<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Impor data AKSES dari berkas JSON hasil `access:export` ke basis data baru yang
 * bersih (13 Oktober 2026). Pasangannya: `access:export`.
 *
 * - users: dimasukkan dengan id yang sama (pivot dan langganan push menunjuk ke id itu),
 *   hash password dan remember_token dibawa apa adanya. id sudah ada dengan username
 *   BERBEDA -> DITOLAK (mode aman, seluruh --apply dibatalkan); username sama -> dilewati.
 * - permissions: dicocokkan menurut NAMA. Yang belum ada dibuat (id baru); yang sudah ada
 *   (dibuat migrasi) dipakai. Izin lama yang tidak ada di DB baru dibuat karena kode lama
 *   mungkin sudah menaruhnya lewat seeder.
 * - pivot user-permission: dipetakan lewat NAMA izin ke id izin di DB baru.
 * - push subscriptions: id dipertahankan; endpoint sama -> dilewati; id sama dengan endpoint
 *   berbeda -> DITOLAK.
 *
 * Default-nya dry-run (laporan). Idempoten. Tidak pernah menyentuh data transaksi.
 */
class ImportAccessData extends Command
{
    protected $signature = 'access:import
                            {--file= : Path berkas JSON dari access:export (bawaan: storage/app/access-export.json)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Impor users, permissions, pivot user-permission, dan push subscriptions dari berkas JSON ke basis data baru';

    /** @var array<string, array<int, string>> */
    private array $lines = [];

    /** @var array<int, string> */
    private array $refusals = [];

    public function handle(): int
    {
        $file = $this->option('file') ?: storage_path('app/access-export.json');

        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($file), true);

        if (! is_array($data) || ! isset($data['users'], $data['permissions'], $data['user_permissions'], $data['push_subscriptions'])) {
            $this->error('Berkas bukan hasil access:export yang sah.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        try {
            DB::beginTransaction();

            $this->importUsers($data['users']);
            $this->importPermissions($data['permissions']);
            $this->importPivot($data['user_permissions']);
            $this->importSubscriptions($data['push_subscriptions']);

            if ($this->refusals !== [] || ! $apply) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->report($apply);

        return $this->refusals === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param  array<int, array<string, mixed>>  $users */
    private function importUsers(array $users): void
    {
        $created = 0;
        $same = 0;

        foreach ($users as $user) {
            $existing = DB::table('users')->where('id', $user['id'])->first();

            if ($existing) {
                if (strtolower((string) $existing->username) !== strtolower((string) $user['username'])) {
                    $this->refusals[] = "users id {$user['id']}: sudah berisi \"{$existing->username}\", berkas \"{$user['username']}\" -- DITOLAK (tidak ditimpa).";

                    continue;
                }

                $same++;

                continue;
            }

            if (DB::table('users')->where('username', $user['username'])->exists()) {
                $this->refusals[] = "users id {$user['id']}: username \"{$user['username']}\" sudah dipakai id lain -- DITOLAK.";

                continue;
            }

            DB::table('users')->insert($user);
            $created++;
        }

        $this->lines['users'][] = "dibuat: {$created}, sama (dilewati): {$same}";
    }

    /**
     * @param  array<int, array<string, mixed>>  $permissions
     * @return array<string, int> nama izin => id di basis data ini
     */
    private function importPermissions(array $permissions): array
    {
        $created = 0;
        $same = 0;

        foreach ($permissions as $permission) {
            if (DB::table('permissions')->where('name', $permission['name'])->exists()) {
                $same++;

                continue;
            }

            DB::table('permissions')->insert([
                'name' => $permission['name'],
                'module_name' => $permission['module_name'] ?? null,
                'description' => $permission['description'] ?? null,
                'created_at' => $permission['created_at'] ?? now(),
                'updated_at' => $permission['updated_at'] ?? now(),
            ]);
            $created++;
        }

        $this->lines['permissions'][] = "dibuat: {$created}, sudah ada (dilewati): {$same}";

        return DB::table('permissions')->pluck('id', 'name')->all();
    }

    /** @param  array<int, array{user_id: int, permission: string}>  $pivot */
    private function importPivot(array $pivot): void
    {
        $ids = DB::table('permissions')->pluck('id', 'name');
        $created = 0;
        $same = 0;
        $skipped = 0;

        foreach ($pivot as $row) {
            $permissionId = $ids[$row['permission']] ?? null;

            if ($permissionId === null || ! DB::table('users')->where('id', $row['user_id'])->exists()) {
                $skipped++;

                continue;
            }

            $exists = DB::table('user_permissions')->where('user_id', $row['user_id'])->where('permission_id', $permissionId)->exists();

            if ($exists) {
                $same++;

                continue;
            }

            DB::table('user_permissions')->insert(['user_id' => $row['user_id'], 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()]);
            $created++;
        }

        $this->lines['user_permissions'][] = "dibuat: {$created}, sama (dilewati): {$same}, dilewati karena pengguna/izin tidak ada: {$skipped}";
    }

    /** @param  array<int, array<string, mixed>>  $subscriptions */
    private function importSubscriptions(array $subscriptions): void
    {
        $created = 0;
        $same = 0;

        foreach ($subscriptions as $subscription) {
            $existing = DB::table('push_subscriptions')->where('id', $subscription['id'])->first();

            if ($existing) {
                if ($existing->endpoint !== $subscription['endpoint']) {
                    $this->refusals[] = "push_subscriptions id {$subscription['id']}: sudah berisi endpoint lain -- DITOLAK (tidak ditimpa).";

                    continue;
                }

                $same++;

                continue;
            }

            if (DB::table('push_subscriptions')->where('endpoint', $subscription['endpoint'])->exists()) {
                $same++;

                continue;
            }

            DB::table('push_subscriptions')->insert($subscription);
            $created++;
        }

        $this->lines['push_subscriptions'][] = "dibuat: {$created}, sama (dilewati): {$same}";
    }

    private function report(bool $apply): void
    {
        $this->newLine();

        if ($this->refusals !== []) {
            $this->error($apply ? 'DIBATALKAN -- ada baris yang bentrok; TIDAK ADA yang ditulis.' : 'Dry-run -- ada baris yang bentrok; --apply akan DIBATALKAN.');
            foreach ($this->refusals as $refusal) {
                $this->line("  <fg=red>x {$refusal}</>");
            }
        } else {
            $this->info($apply ? 'Diterapkan (--apply):' : 'Dry-run -- tidak ada yang ditulis. Tambahkan --apply untuk benar-benar menulis.');
        }

        foreach ($this->lines as $table => $lines) {
            foreach ($lines as $line) {
                $this->line("  <fg=cyan>{$table}</> -- {$line}");
            }
        }
    }
}
