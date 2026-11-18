<?php

namespace Tests\Feature;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PL-01: satu produk hanya sekali dalam satu price list, dijaga di basis data. */
class PriceListItemUniqueTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_same_product_cannot_be_in_one_price_list_twice(): void
    {
        $category = ProductCategory::create(['name' => 'MEAT', 'prefix' => 'MT', 'is_active' => true]);
        $a = Product::create(['name' => 'A', 'code' => 'MT001', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $b = Product::create(['name' => 'B', 'code' => 'MT002', 'category_id' => $category->id, 'structure_type' => 'main', 'is_active' => true]);
        $daftar = PriceList::create(['customer_group_id' => \App\Models\CustomerGroup::create(['name' => 'GRUP 1'])->id]);
        $lain = PriceList::create(['customer_group_id' => \App\Models\CustomerGroup::create(['name' => 'GRUP 2'])->id]);

        PriceListItem::create(['price_list_id' => $daftar->id, 'product_id' => $a->id, 'price' => 100]);
        // Kontrol positif: produk lain di list yang sama, dan produk yang sama di list lain, boleh.
        PriceListItem::create(['price_list_id' => $daftar->id, 'product_id' => $b->id, 'price' => 200]);
        PriceListItem::create(['price_list_id' => $lain->id, 'product_id' => $a->id, 'price' => 300]);

        $this->expectException(UniqueConstraintViolationException::class);
        PriceListItem::create(['price_list_id' => $daftar->id, 'product_id' => $a->id, 'price' => 999]);
    }
}
