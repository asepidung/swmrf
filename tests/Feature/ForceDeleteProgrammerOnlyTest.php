<?php

namespace Tests\Feature;

use App\Models\User;
use Mockery;
use Tests\TestCase;

/** FD-01 (keputusan Owner): hapus PERMANEN dokumen transaksi hanya untuk programmer, apa pun izin hapus yang dipegang. */
class ForceDeleteProgrammerOnlyTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function policies(): array
    {
        $daftar = ['Boning', 'Repack', 'Tally', 'DeliveryOrder', 'DeliveryOrderReceipt', 'DeliveryPlan', 'GoodsReceiptMaterial', 'GoodsReceiptProduct',
            'MaterialRequisition', 'ProductRequisition', 'PurchaseCattle', 'PurchaseProduct', 'CattleReceiving', 'CattleWeighing', 'Carcass',
            'QcReport', 'MaterialUsageHeader', 'SalesOrder'];

        return collect($daftar)->mapWithKeys(fn ($p) => [$p => [$p]])->all();
    }

    /** @dataProvider policies */
    public function test_force_delete_is_for_programmers_only(string $nama): void
    {
        $policy = app('App\\Policies\\'.$nama.'Policy');
        $model = app('App\\Models\\'.$nama);

        // Pemegang SEMUA izin, tetapi bukan programmer.
        $biasa = Mockery::mock(User::class)->makePartial();
        $biasa->role = 'employee';
        $biasa->shouldReceive('hasPermission')->andReturn(true);

        $programmer = new User(['role' => 'programmer']);

        $this->assertFalse($policy->forceDelete($biasa, $model), "$nama: forceDelete");
        $this->assertFalse($policy->forceDeleteAny($biasa), "$nama: forceDeleteAny");
        $this->assertTrue($policy->forceDelete($programmer, $model), "$nama: programmer forceDelete");
        $this->assertTrue($policy->forceDeleteAny($programmer), "$nama: programmer forceDeleteAny");
    }
}
