<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menjaga konsistensi istilah dan kelengkapan terjemahan pada navigasi.
 *
 * Produk di sistem ini adalah hasil pemotongan sapi: daging, tulang, offal,
 * dan kulit. "Beef" terlalu sempit karena tulang dan kulit bukan daging,
 * sementara "Products" terlalu luas dan tidak membedakannya dari Material
 * (bahan penolong). Istilah yang dipakai: "Cattle Products" / "Produk Sapi".
 */
class NavigationTerminologyTest extends TestCase
{
    /** @return array<string, string> */
    protected function translations(string $locale): array
    {
        return json_decode(file_get_contents(base_path("lang/{$locale}.json")), true) ?? [];
    }

    /** @test */
    public function it_keeps_both_language_files_as_valid_json()
    {
        foreach (['id', 'en'] as $locale) {
            $this->assertIsArray(
                json_decode(file_get_contents(base_path("lang/{$locale}.json")), true),
                "lang/{$locale}.json bukan JSON yang valid."
            );
        }
    }

    /**
     * Menu yang tampil dalam bahasa Inggris saat aplikasi berbahasa Indonesia
     * melanggar aturan bilingual di project.md.
     *
     * @test
     */
    public function it_translates_every_cattle_product_navigation_term_to_indonesian()
    {
        $id = $this->translations('id');

        $keys = [
            'Cattle Products',
            'Beef',
            'Beef Categories',
            'Beef Category',
            'Beef Request',
            'Beef Requests',
            'Beef Stock',
            'Opname Beef',
            'Grades',
            'Warehouses',
        ];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $id, "Kunci '{$key}' belum punya terjemahan Indonesia.");
            $this->assertNotSame('', trim($id[$key]), "Terjemahan '{$key}' kosong.");
        }
    }

    /** @test */
    public function it_uses_produk_sapi_as_the_indonesian_umbrella_term()
    {
        $id = $this->translations('id');

        $this->assertSame('Produk Sapi', $id['Cattle Products']);
        $this->assertSame('Produk Sapi', $id['Beef']);
        $this->assertSame('Kategori Produk Sapi', $id['Beef Categories']);
        $this->assertSame('Permintaan Produk Sapi', $id['Beef Requests']);
    }

    /**
     * Sistem ini juga menangani sapi hidup (PO Cattle, Cattle Receiving), jadi
     * "Stok Sapi" akan ambigu antara sapi hidup dan barang hasil potong.
     * Bentuk yang dipakai wajib "Stok Produk Sapi".
     *
     * @test
     */
    public function it_never_labels_processed_stock_as_plain_stok_sapi()
    {
        $id = $this->translations('id');

        $this->assertSame('Stok Produk Sapi', $id['Beef Stock']);
        $this->assertNotSame('Stok Sapi', $id['Beef Stock']);
    }

    /**
     * Menjaga aturan bilingual di project.md secara otomatis: setiap teks yang
     * dibungkus __() pada modul Produk Sapi, Grade, dan Warehouse wajib
     * terdaftar di kedua berkas bahasa. Tanpa test ini, label baru gampang
     * lolos dan menu tampil setengah Inggris saat aplikasi berbahasa Indonesia.
     *
     * @test
     */
    public function it_registers_every_translation_key_used_by_these_modules_in_both_languages()
    {
        $paths = [
            app_path('Filament/Clusters/ProductsCluster.php'),
            app_path('Filament/Clusters/ProductsCluster'),
            app_path('Filament/Admin/Resources/GradeResource.php'),
            app_path('Filament/Admin/Resources/GradeResource'),
            app_path('Filament/Admin/Resources/WarehouseResource.php'),
            app_path('Filament/Admin/Resources/WarehouseResource'),
        ];

        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $this->assertNotEmpty($files, 'Tidak ada berkas yang diperiksa.');

        $keys = [];
        foreach ($files as $file) {
            preg_match_all("/__\('([^']*)'\)/", file_get_contents($file), $matches);
            foreach ($matches[1] as $key) {
                $keys[$key] = true;
            }
        }

        $id = $this->translations('id');
        $en = $this->translations('en');

        foreach (array_keys($keys) as $key) {
            $this->assertArrayHasKey($key, $id, "Kunci '{$key}' belum terdaftar di lang/id.json.");
            $this->assertArrayHasKey($key, $en, "Kunci '{$key}' belum terdaftar di lang/en.json.");
        }
    }

    /**
     * Sub-menu cluster wajib tampil di ATAS (tab horizontal), bukan di samping.
     * Aturan ini berlaku untuk semua cluster; yang belum diperbaiki dibenahi
     * sambil menyisir modulnya masing-masing.
     *
     * @test
     */
    public function it_places_cluster_sub_navigation_on_top()
    {
        $clusters = [
            \App\Filament\Clusters\ProductsCluster::class,
            \App\Filament\Clusters\CustomersCluster::class,
        ];

        foreach ($clusters as $cluster) {
            $position = (new \ReflectionClass($cluster))
                ->getStaticPropertyValue('subNavigationPosition');

            $this->assertSame(
                \Filament\Pages\SubNavigationPosition::Top,
                $position,
                "Sub-menu {$cluster} harus berada di atas, bukan di samping."
            );
        }
    }

    /** @test */
    public function it_names_the_master_data_cluster_cattle_products()
    {
        $this->assertSame(
            'Cattle Products',
            \App\Filament\Clusters\ProductsCluster::getNavigationLabel()
        );
    }

    /** @test */
    public function it_places_grade_inside_the_cattle_products_cluster()
    {
        $this->assertSame(
            \App\Filament\Clusters\ProductsCluster::class,
            \App\Filament\Admin\Resources\GradeResource::getCluster()
        );
    }
}
