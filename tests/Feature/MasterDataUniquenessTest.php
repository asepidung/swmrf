<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Master data tidak boleh punya identitas kembar.
 *
 * Validasi `->unique()` di form Filament saja tidak mengikat: dua permintaan
 * yang tiba bersamaan bisa sama-sama lolos, dan penyisipan lewat seeder,
 * import, atau tinker melewatinya sama sekali. Yang mengikat adalah index
 * unique di database, dan itulah yang diuji di sini.
 */
class MasterDataUniquenessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array<int, string>> */
    public static function masterDataIdentities(): array
    {
        return [
            'nama supplier' => ['suppliers', 'name'],
            'nama customer' => ['customers', 'name'],
            'nama material' => ['materials', 'name'],
            'kode material' => ['materials', 'code'],
            'nama gudang' => ['warehouses', 'name'],
            'kode gudang' => ['warehouses', 'code'],
            'nama grade' => ['grades', 'name'],
            'nama produk' => ['products', 'name'],
            'kode produk' => ['products', 'code'],
            'nama kategori produk' => ['product_categories', 'name'],
            'prefix kategori produk' => ['product_categories', 'prefix'],
            'nama kategori material' => ['material_categories', 'name'],
            'nama satuan material' => ['material_units', 'name'],
            'nama kelas sapi' => ['cattle_classes', 'name'],
            'nama grup customer' => ['customer_groups', 'name'],
            'nama segmen customer' => ['customer_segments', 'name'],
            'username' => ['users', 'username'],
            'inisial bank' => ['bank_accounts', 'initial'],
            'nomor rekening bank' => ['bank_accounts', 'account_number'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider masterDataIdentities
     */
    public function it_guards_master_data_identities_with_a_unique_index(string $table, string $column)
    {
        $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ada.");
        $this->assertTrue(Schema::hasColumn($table, $column), "Kolom {$table}.{$column} tidak ada.");

        $indexes = collect(Schema::getIndexes($table))
            ->filter(fn (array $index): bool => $index['unique'] ?? false)
            ->flatMap(fn (array $index): array => $index['columns'])
            ->all();

        $this->assertContains(
            $column,
            $indexes,
            "{$table}.{$column} adalah identitas master data, jadi wajib punya index unique di database."
        );
    }

    /**
     * Dua orang boleh bernama sama. Yang menjadi identitas pengguna adalah
     * username, bukan nama. Ini dicatat sebagai keputusan, bukan kelalaian.
     *
     * @test
     */
    public function it_deliberately_allows_two_users_to_share_the_same_name()
    {
        $indexes = collect(Schema::getIndexes('users'))
            ->filter(fn (array $index): bool => $index['unique'] ?? false)
            ->flatMap(fn (array $index): array => $index['columns'])
            ->all();

        $this->assertNotContains('name', $indexes, 'users.name sengaja TIDAK unique.');
    }

    /** @test */
    public function it_rejects_a_duplicate_supplier_name_at_the_database_level()
    {
        $row = [
            'name' => 'PT SUMBER SAPI',
            'address' => 'Bogor',
            'pic' => 'Budi',
            'top_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('suppliers')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('suppliers')->insert($row);
    }

    /** @test */
    public function it_rejects_a_duplicate_warehouse_name_at_the_database_level()
    {
        DB::table('warehouses')->insert(['code' => 'JGL', 'name' => 'JONGGOL', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(QueryException::class);

        DB::table('warehouses')->insert(['code' => 'PRM', 'name' => 'JONGGOL', 'created_at' => now(), 'updated_at' => now()]);
    }
}
