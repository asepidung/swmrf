<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource;
use Tests\TestCase;

/** Nama biaya tambahan hasil impor legacy harus tetap terbaca di form, bukan jatuh ke teks cadangan. */
class InvoiceChargeNameOptionsTest extends TestCase
{
    public function test_the_standard_charge_names_are_always_offered(): void
    {
        $this->assertSame(['Ice Gell', 'Styrofoam', 'Delivery Cost'], array_keys(InvoiceResource::chargeNameOptions()));
    }

    public function test_a_stored_legacy_name_becomes_an_option_so_it_is_not_shown_as_empty(): void
    {
        $options = InvoiceResource::chargeNameOptions('STYROFOAM BESAR GARUDA');

        $this->assertArrayHasKey('STYROFOAM BESAR GARUDA', $options);
        $this->assertCount(4, $options);
    }

    public function test_a_standard_or_empty_value_adds_nothing(): void
    {
        $this->assertCount(3, InvoiceResource::chargeNameOptions('Ice Gell'));
        $this->assertCount(3, InvoiceResource::chargeNameOptions(null));
        $this->assertCount(3, InvoiceResource::chargeNameOptions('  '));
    }
}
