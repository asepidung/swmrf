<?php

namespace App\Console\Commands;

use App\Support\LegacySqlDump;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Impor pengguna legacy (tabel `users`: idusers, userid, passuser, fullname, status) dengan id legacy.
 *
 * - id = idusers, username = userid, name = fullname (dirapikan spasinya), is_active = status AKTIF,
 *   role employee, TANPA hak akses (dicentang admin), jenis kelamin kosong.
 * - Kata sandi legacy TIDAK dibawa (keputusan Ayah, 16 Okt): kolom `passuser` diabaikan sepenuhnya. Semua pengguna
 *   diberi kata sandi bawaan `1234`; `CheckPasswordChange` otomatis memaksa menggantinya saat masuk pertama.
 * - Tolak bentrok: id yang sudah dipakai username lain, atau username yang sudah dipakai id lain. Id yang sama dengan
 *   username yang sama dilewati (idempoten). Dry-run bawaan; satu penolakan membatalkan seluruh penulisan.
 *
 * Berkas sumber asli berisi hash kata sandi legacy: jangan di-commit dan jangan disalin ke server. Cukup salinan
 * tanpa hash (kolom passuser dikosongkan), karena kolom itu tidak dibaca.
 */
class ImportLegacyUsers extends Command
{
    protected $signature = 'legacy:import-users
                            {--source=legacy/users-legacy-2026-10-16.sql : Berkas SQL tabel users legacy (passuser diabaikan; jangan di-commit)}
                            {--apply : Benar-benar menulis (default hanya laporan dry-run)}';

    protected $description = 'Impor pengguna legacy dengan id legacy, kata sandi bawaan 1234 (wajib diganti saat masuk pertama), tanpa hak akses';

    public function handle(): int
    {
        $path = (string) $this->option('source');
        $path = is_file($path) ? $path : base_path($path);

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $rows = LegacySqlDump::parseTable((string) file_get_contents($path), 'users');
        $apply = (bool) $this->option('apply');

        $created = $skipped = $refused = [];
        $seenUsernames = [];
        $inserts = [];
        $defaultPassword = Hash::make('1234');

        foreach ($rows as $row) {
            $id = (int) $row['idusers'];
            $username = trim((string) $row['userid']);
            $name = trim((string) preg_replace('/\s+/', ' ', (string) $row['fullname']));
            $active = strtoupper(trim((string) $row['status'])) === 'AKTIF';
            $label = "id {$id} {$username}".($active ? '' : ' (INAKTIF)');

            if ($username === '' || $name === '') {
                $refused[] = "{$label}: username atau nama kosong -- DITOLAK.";

                continue;
            }

            $byId = DB::table('users')->where('id', $id)->first(['id', 'username']);

            if ($byId) {
                if (strtolower((string) $byId->username) === strtolower($username)) {
                    $skipped[] = "{$label}: sudah ada.";
                } else {
                    $refused[] = "{$label}: id {$id} sudah dipakai username \"{$byId->username}\" -- DITOLAK.";
                }

                continue;
            }

            $owner = DB::table('users')->whereRaw('LOWER(username) = ?', [strtolower($username)])->value('id');

            if ($owner !== null || isset($seenUsernames[strtolower($username)])) {
                $refused[] = "{$label}: username sudah dipakai id ".($owner ?? $seenUsernames[strtolower($username)]).' -- DITOLAK.';

                continue;
            }

            $seenUsernames[strtolower($username)] = $id;
            $created[] = "{$label}: {$name}";

            $inserts[] = [
                'id' => $id,
                'name' => $name,
                'username' => $username,
                'password' => $defaultPassword,  // bawaan 1234; passuser legacy diabaikan
                'role' => 'employee',
                'is_active' => $active,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Satu penolakan membatalkan seluruh penulisan (sama seperti legacy:import-master).
        if ($apply && $refused === []) {
            DB::transaction(function () use ($inserts): void {
                foreach ($inserts as $row) {
                    DB::table('users')->insert($row);
                }
            });
        }

        $this->report($apply, $created, $skipped, $refused);

        return $refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<int, string>  $created
     * @param  array<int, string>  $skipped
     * @param  array<int, string>  $refused
     */
    private function report(bool $apply, array $created, array $skipped, array $refused): void
    {
        $this->line(match (true) {
            $apply && $refused !== [] => 'DIBATALKAN -- ada baris DITOLAK; TIDAK ADA yang ditulis.',
            $apply => 'DITERAPKAN (--apply)',
            default => 'DRY-RUN -- tidak ada yang ditulis. Tambahkan --apply untuk menulis.',
        });
        $this->line('users -- dibuat: '.count($created).', sudah ada (dilewati): '.count($skipped).', DITOLAK: '.count($refused));

        foreach ($refused as $item) {
            $this->line("  x {$item}");
        }
        foreach ($created as $item) {
            $this->line("  + {$item}");
        }
        foreach ($skipped as $item) {
            $this->line("  = {$item}");
        }

        $this->newLine();
        $this->line('Catatan: hak akses kosong (diberikan admin); kata sandi semua pengguna 1234 dan wajib diganti saat masuk pertama; role employee. '
            .'Hapus berkas sumber dari server sesudah impor.');
    }
}
