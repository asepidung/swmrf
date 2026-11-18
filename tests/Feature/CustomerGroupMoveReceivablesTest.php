<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Receivable;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CU-01 (keputusan Owner): memindah pelanggan ke grup lain membawa piutang BELUM LUNAS-nya; yang lunas tetap di grup lama. */
class CustomerGroupMoveReceivablesTest extends TestCase
{
    use RefreshDatabase;

    private function piutang(Customer $c, CustomerGroup $g, string $status): Receivable
    {
        $user = User::factory()->create();
        $so = SalesOrder::create(['customer_id' => $c->id, 'delivery_date' => now()->toDateString(), 'created_by' => $user->id, 'status' => 'completed']);
        $inv = Invoice::create([
            'customer_id' => $c->id, 'sales_order_id' => $so->id, 'invoice_date' => now()->toDateString(), 'term_of_payment' => 30,
            'status' => $status, 'subtotal' => 1000, 'charge' => 0, 'down_payment' => 0, 'created_by' => $user->id,
        ]);

        Invoice::whereKey($inv->id)->update(['status' => $status]); // lewati penghitung ulang status

        return Receivable::create(['invoice_id' => $inv->id, 'customer_id' => $c->id, 'customer_group_id' => $g->id]);
    }

    /** @test */
    public function moving_a_customer_to_another_group_moves_its_unpaid_receivables_only(): void
    {
        $lama = CustomerGroup::create(['name' => 'LAMA']);
        $baru = CustomerGroup::create(['name' => 'BARU']);
        $segmen = CustomerSegment::create(['name' => 'RETAIL', 'is_active' => true]);
        $c = Customer::create(['name' => 'PINDAH', 'address' => 'X', 'top' => 30, 'customer_group_id' => $lama->id, 'customer_segment_id' => $segmen->id]);
        $lain = Customer::create(['name' => 'TETAP', 'address' => 'X', 'top' => 30, 'customer_group_id' => $lama->id, 'customer_segment_id' => $segmen->id]);

        $belum = $this->piutang($c, $lama, 'Belum Dibayar');
        $lunas = $this->piutang($c, $lama, Invoice::STATUS_PAID);
        $milikLain = $this->piutang($lain, $lama, 'Belum Dibayar');

        $c->update(['name' => 'PINDAH 2']);
        $this->assertSame($lama->id, $belum->fresh()->customer_group_id, 'ganti nama bukan pindah grup');

        $c->update(['customer_group_id' => $baru->id]);

        $this->assertSame($baru->id, $belum->fresh()->customer_group_id, 'piutang belum lunas ikut pindah');
        $this->assertSame($lama->id, $lunas->fresh()->customer_group_id, 'yang lunas tetap sebagai riwayat');
        $this->assertSame($lama->id, $milikLain->fresh()->customer_group_id, 'pelanggan lain tidak terpengaruh');
    }
}
