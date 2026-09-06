<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Dokumen terhapus hanya boleh terlihat oleh yang berhak melihatnya.
 *
 * Ada TIGA bentuk kerusakan yang ditemukan saat menyisir modul Invoice, dan
 * ketiganya berbagi satu sebab: penjagaannya dipasang di lapisan yang salah.
 *
 * SATU -- BOCOR. Sepuluh Resource membawa masuk baris terhapus untuk SEMUA
 * ORANG:
 *
 *     return parent::getEloquentQuery()
 *         ->withoutGlobalScopes([SoftDeletingScope::class]);
 *
 * lalu mengandalkan `TrashedFilter` yang digerbangi izin untuk menyaringnya
 * kembali. Filament membuang filter yang tidak terlihat sebelum query
 * dijalankan, jadi izin itu hanya menyembunyikan TOMBOLNYA, bukan datanya.
 *
 * DUA -- IZIN YANG TIDAK MENGIZINKAN APA-APA. Tiga Resource memeriksa haknya
 * dengan `auth()->user()->can('view_deleted_...')`. Proyek ini tidak
 * mendaftarkan nama izin sebagai Gate dan tidak punya `Gate::before`, jadi
 * pemeriksaan itu SELALU false -- filternya tidak pernah muncul untuk siapa
 * pun, programmer sekalipun. Izinnya bisa diberikan, dan pemberiannya tidak
 * berakibat apa-apa.
 *
 * TIGA -- IZIN YANG TIDAK PERNAH DIPASANG. `view_deleted_carcasses` sudah ada
 * di seeder sejak lama tanpa satu pun kode yang membacanya, sementara Carcass
 * dan Sales Return membuka baris terhapusnya tanpa pemeriksaan apa pun.
 *
 * Semuanya kini lewat `TrashedRecords::visibleTo()`, dan izinnya menjadi batas
 * yang sebenarnya. Perilakunya diuji dari sisi pengguna di
 * DeletedInvoiceVisibilityTest; berkas ini menjaga POLANYA supaya modul
 * berikutnya tidak diam-diam memasang lubang yang sama.
 */
class DeletedRecordVisibilityTest extends TestCase
{
    /** @return array<string, string> berkas => isinya */
    private function resourceSources(): array
    {
        $sources = [];

        foreach (glob(app_path('Filament/Admin/Resources/*Resource.php')) as $file) {
            // Komentar dibuang lebih dulu. Sudah lima kali di proyek ini
            // pemindai menuduh keterangan yang MENERANGKAN pola lama sebagai
            // pemakainya -- termasuk komentar yang menjelaskan kenapa
            // `withoutGlobalScopes` telanjang tidak boleh dipakai.
            $sources[basename($file)] = $this->tanpaKomentar(file_get_contents($file));
        }

        return $sources;
    }

    /**
     * Tidak ada Resource yang mematikan penyaring hapus-lunak tanpa memeriksa
     * izin lebih dulu.
     */
    public function test_no_resource_drops_the_soft_delete_scope_for_everyone(): void
    {
        $offenders = [];

        foreach ($this->resourceSources() as $name => $source) {
            if (str_contains($source, 'withoutGlobalScopes')) {
                $offenders[] = $name;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'Resource berikut membawa masuk baris terhapus tanpa memeriksa izin. '
            ."Pakai TrashedRecords::visibleTo(parent::getEloquentQuery(), 'view_deleted_...').",
        );
    }

    /**
     * Hak atas dokumen terhapus diperiksa dengan hasPermission, bukan can().
     *
     * `can()` menanyakannya kepada Gate, yang tidak tahu apa-apa tentang nama
     * izin di proyek ini. Jawabannya selalu tidak, dan tidak ada satu pun
     * gejala: tombolnya cuma tidak pernah muncul.
     */
    public function test_deleted_record_permissions_are_checked_the_way_this_project_checks_them(): void
    {
        $offenders = [];

        foreach ($this->resourceSources() as $name => $source) {
            if (preg_match("/->can\('view_deleted_[a-z_]+'\)/", $source)) {
                $offenders[] = $name;
            }
        }

        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            'Resource berikut memeriksa izin lewat Gate, yang tidak mengenal nama izin '
            .'proyek ini, sehingga jawabannya selalu tidak. Pakai hasPermission().',
        );
    }

    /**
     * Setiap izin `view_deleted_*` yang dipakai kode benar-benar ada di seeder.
     *
     * Izin yang tidak pernah dibuat tidak bisa diberikan kepada siapa pun,
     * jadi fiturnya mati diam-diam -- terlihat seolah tidak ada dokumen yang
     * pernah dihapus.
     */
    public function test_every_deleted_record_permission_actually_exists(): void
    {
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $dipakai = [];

        foreach ($this->resourceSources() as $source) {
            preg_match_all("/'(view_deleted_[a-z_]+)'/", $source, $matches);
            $dipakai = array_merge($dipakai, $matches[1]);
        }

        $hilang = [];

        foreach (array_unique($dipakai) as $permission) {
            if (! str_contains($seeder, "'".$permission."'")) {
                $hilang[] = $permission;
            }
        }

        sort($hilang);

        $this->assertSame(
            [],
            $hilang,
            'Izin berikut dipakai kode tetapi tidak pernah dibuat, jadi tidak bisa '
            .'diberikan kepada siapa pun.',
        );
    }

    /**
     * Arah sebaliknya: izin `view_deleted_*` yang dibuat tetapi tidak pernah
     * DIBACA satu baris kode pun.
     *
     * Penjaga di atas menangkap izin yang dipakai tetapi tidak pernah dibuat.
     * Lubang yang bentuknya berlawanan sama merugikannya, dan lebih sulit
     * disadari: centangnya ADA di form Hak Akses, bisa diberikan, dan tidak
     * berakibat apa-apa. Orang yang memberikannya percaya sudah memberi hak
     * yang sebenarnya tidak pernah sampai.
     *
     * `view_deleted_repacks` berada dalam keadaan itu sampai hari ini --
     * lengkap dengan `RepackPolicy::restore()` yang karena itu tidak pernah
     * bisa terpakai: tidak ada satu pun layar yang menampilkan repack
     * terhapus, jadi tidak ada yang bisa dipulihkan.
     */
    public function test_every_deleted_record_permission_is_actually_read(): void
    {
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        preg_match_all("/'(view_deleted_[a-z_]+)'/", $seeder, $cocok);

        $dibaca = [];

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path())
        );

        foreach ($berkas as $satu) {
            if (! $satu->isFile() || $satu->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/'(view_deleted_[a-z_]+)'/",
                $this->tanpaKomentar(file_get_contents($satu->getPathname())),
                $ketemu,
            );

            $dibaca = array_merge($dibaca, $ketemu[1]);
        }

        $mati = array_values(array_diff(
            array_unique($cocok[1]),
            array_unique($dibaca),
            $this->sengajaTidakDipakai(),
        ));

        sort($mati);

        $this->assertSame(
            [],
            $mati,
            "Izin berikut ada di form Hak Akses tetapi tidak dibaca satu baris kode pun. "
            ."Centangnya bisa diberikan dan tidak berakibat apa-apa:\n".implode("\n", $mati),
        );
    }

    /**
     * Izin `view_deleted_*` yang memang tidak bisa dipakai.
     *
     * Dibaca dari `Permission::TIDAK_DITAMPILKAN`, bukan ditulis ulang di
     * sini. Daftar yang sama di dua tempat selalu berakhir berbeda -- dan
     * daftar inilah yang juga menentukan apa yang disembunyikan dari form
     * Hak Akses, jadi keduanya wajib bicara tentang hal yang sama persis.
     *
     * @return list<string>
     */
    private function sengajaTidakDipakai(): array
    {
        return array_keys(\App\Models\Permission::TIDAK_DITAMPILKAN);
    }

    /** Komentar dibuang supaya keterangannya tidak ikut terhitung dibaca. */
    private function tanpaKomentar(string $isi): string
    {
        $hasil = '';

        foreach (@token_get_all($isi) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $hasil .= is_array($token) ? $token[1] : $token;
        }

        return $hasil;
    }
}
