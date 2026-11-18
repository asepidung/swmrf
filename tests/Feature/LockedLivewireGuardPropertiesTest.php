<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GoodsReceiptMaterialResource\Pages\CreateGoodsReceiptMaterial;
use App\Filament\Admin\Resources\GoodsReceiptProductResource\Pages\NewGoodsReceiptProduct;
use Livewire\Attributes\Locked;
use Tests\TestCase;

/**
 * LW-01: properti Livewire publik yang dipakai sebagai penjaga tidak boleh bisa diubah dari browser.
 * Penjaga "orang lain sudah menerima PO ini" membandingkan jumlah penerimaan sekarang dengan `receiptsAtOpen`; bila browser
 * bisa menimpa nilainya, penjaga itu dilewati dan dua penerimaan untuk PO yang sama bisa lahir.
 */
class LockedLivewireGuardPropertiesTest extends TestCase
{
    /** @return array<string, array{0: class-string, 1: string}> */
    public static function guardProperties(): array
    {
        return [
            'GR Material: receiptsAtOpen' => [CreateGoodsReceiptMaterial::class, 'receiptsAtOpen'],
            'GR Material: poId' => [CreateGoodsReceiptMaterial::class, 'poId'],
            'GR Produk: receiptsAtOpen' => [NewGoodsReceiptProduct::class, 'receiptsAtOpen'],
        ];
    }

    /**
     * @test
     * @dataProvider guardProperties
     */
    public function a_guard_property_is_locked_against_the_browser(string $class, string $property): void
    {
        $locked = (new \ReflectionProperty($class, $property))->getAttributes(Locked::class);

        $this->assertNotEmpty($locked, "$class::\$$property harus #[Locked].");
    }
}
