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
        $modules = [
            'Filament/Clusters/ProductsCluster',
            'Filament/Clusters/CustomersCluster',
            'Filament/Clusters/Materials',
            'Filament/Admin/Resources/GradeResource',
            'Filament/Admin/Resources/WarehouseResource',
            'Filament/Admin/Resources/BankAccountResource',
            'Filament/Admin/Resources/SupplierResource',
            'Filament/Admin/Resources/UserResource',
            'Filament/Admin/Resources/MaterialResource',
            'Filament/Admin/Resources/MaterialCategoryResource',
            'Filament/Admin/Resources/MaterialUnitResource',
            'Filament/Admin/Resources/CattleClassResource',
        ];

        $paths = [];
        foreach ($modules as $module) {
            $paths[] = app_path($module . '.php');
            $paths[] = app_path($module);
        }

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
     *
     * PENTING: Filament membaca setelan ini dari RESOURCE, bukan dari kelas
     * Cluster. Menyetelnya di Cluster saja tidak mengubah tampilan sama sekali.
     * Versi awal test ini keliru memeriksa kelas Cluster, sehingga lolos
     * padahal menunya masih di samping.
     *
     * @test
     */
    public function it_places_cluster_sub_navigation_on_top()
    {
        $resources = [
            \App\Filament\Clusters\ProductsCluster\Resources\ProductResource::class,
            \App\Filament\Clusters\ProductsCluster\Resources\ProductCategoryResource::class,
            \App\Filament\Admin\Resources\GradeResource::class,
            \App\Filament\Clusters\CustomersCluster\Resources\CustomerResource::class,
            \App\Filament\Clusters\CustomersCluster\Resources\CustomerGroupResource::class,
            \App\Filament\Clusters\CustomersCluster\Resources\CustomerSegmentResource::class,
        ];

        foreach ($resources as $resource) {
            $this->assertSame(
                \Filament\Pages\SubNavigationPosition::Top,
                $resource::getSubNavigationPosition(),
                "Sub-menu {$resource} harus berada di atas, bukan di samping."
            );
        }
    }

    /**
     * Data master yang baru dibuat sudah pasti aktif, dan seluruh kolom
     * is_active-nya memang DEFAULT 1 di database. Togglenya di halaman Create
     * hanya menambah satu perhentian Tab yang tidak berguna.
     *
     * @test
     */
    public function it_hides_the_active_toggle_on_every_master_data_create_form()
    {
        $files = [
            'Filament/Admin/Resources/BankAccountResource.php',
            'Filament/Admin/Resources/GradeResource.php',
            'Filament/Admin/Resources/MaterialResource.php',
            'Filament/Admin/Resources/SupplierResource.php',
            'Filament/Admin/Resources/UserResource.php',
            'Filament/Admin/Resources/WarehouseResource.php',
            'Filament/Clusters/CustomersCluster/Resources/CustomerResource.php',
            'Filament/Clusters/ProductsCluster/Resources/ProductResource.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(app_path($file));

            if (! str_contains($source, "Toggle::make('is_active')")) {
                continue;
            }

            $chain = substr($source, strpos($source, "Toggle::make('is_active')"), 400);

            $this->assertStringContainsString(
                "->visibleOn('edit')",
                $chain,
                "Toggle is_active di {$file} masih tampil di halaman Create."
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
