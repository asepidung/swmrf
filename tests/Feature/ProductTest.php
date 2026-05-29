<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_forces_uppercase_category_and_product_names()
    {
        $category = ProductCategory::create(['name' => 'primary cuts']);
        $this->assertEquals('PRIMARY CUTS', $category->name);

        $product = Product::create([
            'code' => '100100',
            'name' => 'shortloin steak',
            'category_id' => $category->id,
            'structure_type' => 'main',
            'is_active' => true,
        ]);
        $this->assertEquals('SHORTLOIN STEAK', $product->name);
    }

    /** @test */
    public function it_generates_correct_codes_for_main_and_sub_products()
    {
        $category = ProductCategory::create(['name' => 'PRIMARY CUTS']);
        $catId = $category->id;
        
        // Mocking Filament Form Set/Get logic
        // First main product code generation
        $setValues = [];
        $set = function ($key, $value) use (&$setValues) {
            $setValues[$key] = $value;
        };
        $getValues = [
            'structure_type' => 'main',
            'category_id' => $catId,
        ];
        $get = function ($key) use (&$getValues) {
            return $getValues[$key] ?? null;
        };

        // Call the static updateCode from ProductResource
        \App\Filament\Clusters\ProductsCluster\Resources\ProductResource::updateCode($set, $get);
        $expected1 = (string) (($catId * 100000) + 100);
        $this->assertEquals($expected1, $setValues['code']);

        // Create the product
        $mainProduct1 = Product::create([
            'code' => $setValues['code'],
            'name' => 'MAIN PRODUCT ONE',
            'category_id' => $catId,
            'structure_type' => 'main',
        ]);

        // Second main product code generation
        $setValues = [];
        $getValues = [
            'structure_type' => 'main',
            'category_id' => $catId,
        ];
        \App\Filament\Clusters\ProductsCluster\Resources\ProductResource::updateCode($set, $get);
        $expected2 = (string) (($catId * 100000) + 200);
        $this->assertEquals($expected2, $setValues['code']);

        // Create second product
        $mainProduct2 = Product::create([
            'code' => $setValues['code'],
            'name' => 'MAIN PRODUCT TWO',
            'category_id' => $catId,
            'structure_type' => 'main',
        ]);

        // First sub product under mainProduct1
        $setValues = [];
        $getValues = [
            'structure_type' => 'sub',
            'parent_id' => $mainProduct1->id,
        ];
        \App\Filament\Clusters\ProductsCluster\Resources\ProductResource::updateCode($set, $get);
        $expectedSub1 = (string) ((int) $mainProduct1->code + 1);
        $this->assertEquals($expectedSub1, $setValues['code']);

        // Create first sub product
        $subProduct1 = Product::create([
            'code' => $setValues['code'],
            'name' => 'SUB PRODUCT ONE',
            'category_id' => $catId,
            'structure_type' => 'sub',
            'parent_id' => $mainProduct1->id,
        ]);

        // Second sub product under mainProduct1
        $setValues = [];
        $getValues = [
            'structure_type' => 'sub',
            'parent_id' => $mainProduct1->id,
        ];
        \App\Filament\Clusters\ProductsCluster\Resources\ProductResource::updateCode($set, $get);
        $expectedSub2 = (string) ((int) $mainProduct1->code + 2);
        $this->assertEquals($expectedSub2, $setValues['code']);
    }
}
