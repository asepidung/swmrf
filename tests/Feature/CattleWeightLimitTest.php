<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CattleReceivingResource;
use App\Models\CattleClass;
use App\Models\CattleReceiving;
use App\Models\CattleReceivingItem;
use App\Models\PurchaseCattle;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Berat di atas batas DITOLAK, tidak pernah dibetulkan sendiri.
 *
 * Keputusan Project Owner: batas 800 kg tetap berlaku, tetapi nilai yang
 * melebihi batas tidak boleh dijepit menjadi 800. Kalau 900 diam-diam
 * menjadi 800, operator tidak pernah tahu ia salah ketik, dan yang tersimpan
 * adalah berat yang tidak pernah ada sapinya.
 *
 * Sejak hutang dihitung dari berat ini, kesalahan itu langsung menjadi
 * tagihan yang salah ke supplier -- tanpa satu pun gejala di layar. Sistem
 * yang membetulkan sendiri isian pengguna menyembunyikan kesalahan; sistem
 * yang menolak mengembalikannya untuk diperbaiki.
 */
class CattleWeightLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Batasnya tidak boleh dipasang lewat `maxValue()`.
     *
     * `maxValue()` ikut memasang atribut HTML `max` pada
     * `<input type="number">`, dan atribut itulah yang membuka jalan bagi
     * nilai terjepit diam-diam -- misalnya lewat tombol panah -- tanpa pesan
     * apa pun.
     */
    public function test_the_limit_is_a_validation_rule_not_a_browser_clamp(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/CattleReceivingResource.php'));

        // Ambil hanya bagian field beratnya, supaya komentar di tempat lain
        // tidak ikut terbaca.
        $field = substr($source, strpos($source, "TextInput::make('initial_weight')"));
        $field = substr($field, 0, strpos($field, "TextInput::make('notes')"));

        $this->assertStringNotContainsString('->maxValue(', $field);
        $this->assertStringContainsString("'max:800'", $field);
        $this->assertStringContainsString('validationMessages', $field);
    }

    /** Pesannya menyebut batasnya, supaya operator tahu harus mengubah apa. */
    public function test_the_message_tells_the_operator_what_the_limit_is(): void
    {
        $id = json_decode(file_get_contents(base_path('lang/id.json')), true);

        $key = 'Weight is above the :max kg limit. Please check the number again.';

        $this->assertArrayHasKey($key, $id);
        $this->assertStringContainsString(':max', $id[$key]);
    }

    /**
     * Dan yang tersimpan adalah angka yang benar-benar diketik.
     *
     * Ini yang paling penting: tidak ada satu pun lapisan -- mutator, cast,
     * atau model event -- yang diam-diam memotong nilainya ke batas atas.
     * Kalau ada, penolakan di form tidak ada gunanya.
     */
    public function test_nothing_silently_clamps_the_stored_weight(): void
    {
        $user = User::create([
            'name' => 'Operator', 'username' => 'operator_weight', 'password' => 'secret-password',
            'gender' => 'L', 'role' => 'programmer', 'is_active' => true,
        ]);
        $this->actingAs($user);

        $supplier = Supplier::create([
            'name' => 'FEEDLOT JAYA', 'address' => 'Bogor', 'pic' => 'Doni', 'top_days' => 30,
        ]);

        $po = PurchaseCattle::create([
            'supplier_id' => $supplier->id,
            'shipping_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $receiving = CattleReceiving::create([
            'purchase_cattle_id' => $po->id,
            'supplier_id' => $supplier->id,
            'receive_date' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        $item = $receiving->items()->create([
            'cattle_class_id' => CattleClass::create(['name' => 'BALI', 'is_active' => true])->id,
            'eartag' => 'ID-9001',
            'initial_weight' => 900,
        ]);

        $this->assertSame(900, (int) $item->fresh()->initial_weight);
    }
}
