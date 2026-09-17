<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Setiap model yang punya Filament Resource wajib punya Policy TERDAFTAR.
 *
 * MaterialUsageResource ketahuan tidak punya Policy sama sekali untuk
 * MaterialUsageHeader -- dan proyek ini tidak punya `Gate::before`, jadi
 * `authorize()`/`canCreate()` jatuh ke Response::allow(). Siapa pun yang
 * login bisa membuka "Create Manual Usage" dan membuat penyesuaian stok
 * sungguhan, terlepas dari izin apa pun yang dipegangnya.
 *
 * Penjaga ini memindai SEMUA Resource (Admin + Cluster), bukan cuma yang
 * baru diperbaiki, supaya kelas kesalahan yang sama tidak lolos lagi kalau
 * suatu saat ada Resource baru yang lupa Policy-nya. Susulan 17 September
 * 2026: sudah dijalankan sekali atas seluruh Resource yang ada -- hasilnya
 * HANYA MaterialUsageResource yang kosong, jadi daftar pengecualian di
 * bawah sengaja dibiarkan kosong.
 */
class ResourceHasPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function every_resources_model_has_a_registered_policy(): void
    {
        $files = array_merge(
            glob(app_path('Filament/Admin/Resources/*Resource.php')),
            glob(app_path('Filament/Clusters/*/Resources/*Resource.php')),
        );

        $this->assertNotEmpty($files, 'Pola pencarian Resource tidak menemukan apa pun -- penjaga ini diam-diam tidak memeriksa apa-apa.');

        $tanpaPolicy = [];

        foreach ($files as $file) {
            $relative = str_replace(app_path().DIRECTORY_SEPARATOR, '', $file);
            $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
            $class = 'App\\'.substr($relative, 0, -4);

            if (! class_exists($class) || ! method_exists($class, 'getModel')) {
                continue;
            }

            $model = $class::getModel();

            if (in_array($class.' -> '.$model, $this->dikecualikanSementara(), true)) {
                continue;
            }

            if (Gate::getPolicyFor($model) === null) {
                $tanpaPolicy[] = "{$class} -> {$model}";
            }
        }

        $this->assertSame(
            [],
            $tanpaPolicy,
            "Resource berikut tidak punya Policy terdaftar untuk modelnya, jadi authorize() jatuh fail-open:\n".implode("\n", $tanpaPolicy),
        );
    }

    /**
     * Pengecualian sementara -- diisi HANYA setelah dilaporkan dan
     * ditriase, bukan ditambah begitu saja supaya test ini lolos.
     *
     * @return array<int, string>
     */
    private function dikecualikanSementara(): array
    {
        return [
        ];
    }
}
