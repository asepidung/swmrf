<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Perbaikan isian master data (Owner, 22 Oktober 2026): diskon customer, huruf besar supir/armada, urutan form Material.
 */
class MasterFormTweaksTest extends TestCase
{
    use RefreshDatabase;

    private function fieldSource(string $path, string $needle, int $length = 1200): string
    {
        $source = file_get_contents(app_path($path));

        return substr($source, strpos($source, $needle), $length);
    }

    public function test_a_customer_discount_of_five_percent_is_valid_and_only_over_100_is_refused(): void
    {
        $field = $this->fieldSource('Filament/Clusters/CustomersCluster/Resources/CustomerResource.php', "TextInput::make('default_discount')", 3000);
        $field = substr($field, 0, strpos($field, "Select::make('invoice_exchange')")); // hanya isian diskon, tanpa isian sesudahnya

        // Penyebab lama: ->maxLength(3) memasang `max:3`; bersama `integer` ia dibaca ANGKA, jadi 5 ditolak.
        $this->assertStringNotContainsString('->maxLength(3)', $field);
        $this->assertStringContainsString("'maxlength' => '3'", $field);

        $rules = ['default_discount' => ['integer', 'min:0', 'max:100']];

        foreach (['0', '5', '10', '100'] as $ok) {
            $this->assertTrue(Validator::make(['default_discount' => $ok], $rules)->passes(), "{$ok}% harus lolos");
        }

        foreach (['101', '-1', '2,5'] as $bad) {
            $this->assertTrue(Validator::make(['default_discount' => $bad], $rules)->fails(), "{$bad} harus ditolak");
        }
    }

    public function test_driver_and_fleet_names_are_always_stored_in_capitals(): void
    {
        $driver = Driver::create(['name' => ' budi santoso ', 'is_active' => true]);
        $vehicle = Vehicle::create(['vehicle_type' => 'Truk Box', 'police_number' => ' f 8489 hv ', 'is_active' => true]);

        $this->assertSame('BUDI SANTOSO', $driver->fresh()->name);
        $this->assertSame('TRUK BOX', $vehicle->fresh()->vehicle_type);
        $this->assertSame('F 8489 HV', $vehicle->fresh()->police_number);
    }

    public function test_the_forms_show_driver_and_fleet_names_in_capitals(): void
    {
        foreach (['Filament/Admin/Resources/DriverResource.php' => ["TextInput::make('name')"], 'Filament/Admin/Resources/VehicleResource.php' => ["TextInput::make('vehicle_type')", "TextInput::make('police_number')"]] as $path => $needles) {
            foreach ($needles as $needle) {
                $this->assertStringContainsString('text-transform:uppercase', $this->fieldSource($path, $needle, 400), "{$path} {$needle}");
            }
        }

        $quick = file_get_contents(app_path('Filament/Support/QuickCreate.php'));
        $driver = substr($quick, strpos($quick, "'driver' => ["), 200);
        $this->assertStringContainsString('uppercaseName', $driver, 'form cepat supir memakai isian huruf besar');
        $this->assertStringContainsString('text-transform:uppercase', substr($quick, strpos($quick, "'vehicle' => ["), 700));
    }

    public function test_the_material_form_asks_stock_visibility_before_the_minimum_stock_and_the_minimum_has_no_spinner(): void
    {
        $source = file_get_contents(app_path('Filament/Admin/Resources/MaterialResource.php'));
        $toggle = strpos($source, "Toggle::make('show_in_stock')");
        $min = strpos($source, "TextInput::make('min_stock')");

        $this->assertNotFalse($toggle);
        $this->assertLessThan($min, $toggle, '"Show in Stock List?" harus di atas "Min. Stock"');

        $field = substr($source, $min, 1200);
        $this->assertStringNotContainsString('->numeric()', substr($field, 0, strpos($field, "->visible(")), 'tanpa type=number (panah/roda tetikus)');
        $this->assertStringContainsString("'onfocus' => 'this.select()'", $field, 'nilai awal 0 terpilih semua saat difokus');
        $this->assertStringContainsString("'inputmode' => 'numeric'", $field);
        $this->assertStringContainsString("'integer'", $field);
    }
}
