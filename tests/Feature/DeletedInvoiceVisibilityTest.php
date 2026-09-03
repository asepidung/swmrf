<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Apakah invoice yang sudah dihapus benar-benar tersembunyi?
 *
 * `InvoiceResource::getEloquentQuery()` mematikan `SoftDeletingScope`, jadi
 * baris terhapus IKUT terbawa ke dalam query dasar. Yang menyaringnya kembali
 * hanyalah `TrashedFilter`, dan filter itu dibungkus
 * `->visible(fn () => auth()->user()->hasPermission('view_deleted_invoices'))`.
 *
 * Pertanyaannya: filter yang tidak terlihat, apakah masih menyaring?
 *
 * Kalau tidak, akibatnya diam-diam: pengguna tanpa hak itu melihat invoice
 * yang sudah dihapus bercampur dengan yang hidup, tanpa penanda apa pun dan
 * tanpa cara membedakannya.
 */
class DeletedInvoiceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function buatInvoice(string $nomor, bool $dihapus): Invoice
    {
        $customer = Customer::firstOrCreate(
            ['name' => 'PELANGGAN UJI'],
            [
                'address' => 'Bogor',
                'top_days' => 30,
                'customer_segment_id' => CustomerSegment::firstOrCreate(['name' => 'RETAIL'])->id,
            ],
        );

        $so = SalesOrder::create([
            'customer_id' => $customer->id,
            'delivery_date' => now()->addDay()->toDateString(),
            'po_number' => 'PO-UJI',
            'status' => 'ready',
        ]);

        // Model Invoice memakai `auth()->id() ?? 1` sebagai pembuatnya, jadi
        // pengguna itu harus benar-benar ada sebelum barisnya dibuat.
        $pembuat = User::firstOrCreate(
            ['username' => 'pembuat_invoice'],
            [
                'name' => 'Pembuat', 'password' => 'secret-password',
                'gender' => 'L', 'role' => 'employee', 'is_active' => true,
            ],
        );

        $invoice = Invoice::create([
            'created_by' => $pembuat->id,
            'invoice_number' => $nomor,
            'customer_id' => $customer->id,
            'sales_order_id' => $so->id,
            'invoice_date' => now()->toDateString(),
            'term_of_payment' => 30,
            'status' => 'Belum Dibayar',
            'subtotal' => 1000000,
            'balance' => 1000000,
        ]);

        if ($dihapus) {
            $invoice->delete();
        }

        return $invoice;
    }

    private function buatPengguna(string $username, bool $bolehLihatTerhapus): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'password' => 'secret-password',
            'gender' => 'L',
            'role' => 'employee',
            'is_active' => true,
        ]);

        // Tanpa ini InvoicePolicy menolak halamannya sebelum tabelnya sempat
        // dibangun, dan pengujiannya tidak menguji apa pun.
        $user->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'view_invoices'],
                ['module_name' => 'Invoices', 'description' => 'View invoices'],
            )->id
        );

        if ($bolehLihatTerhapus) {
            $user->permissions()->attach(
                Permission::firstOrCreate(
                    ['name' => 'view_deleted_invoices'],
                    ['module_name' => 'Invoices', 'description' => 'View deleted invoices'],
                )->id
            );
        }

        return $user;
    }

    /** Yang tidak punya haknya TIDAK boleh melihat invoice terhapus. */
    public function test_a_user_without_the_permission_does_not_see_deleted_invoices(): void
    {
        $hidup = $this->buatInvoice('SWM-INV#26-0001', dihapus: false);
        $mati = $this->buatInvoice('SWM-INV#26-0002', dihapus: true);

        $biasa = $this->buatPengguna('tanpa_hak', bolehLihatTerhapus: false);

        Livewire::actingAs($biasa)
            ->test(ListInvoices::class)
            ->assertCanSeeTableRecords([$hidup])
            ->assertCanNotSeeTableRecords([$mati]);
    }

    /** Dan yang punya haknya tetap bisa memunculkannya lewat filter. */
    public function test_a_user_with_the_permission_can_still_reach_them(): void
    {
        $hidup = $this->buatInvoice('SWM-INV#26-0003', dihapus: false);
        $mati = $this->buatInvoice('SWM-INV#26-0004', dihapus: true);

        $pengawas = $this->buatPengguna('dengan_hak', bolehLihatTerhapus: true);

        Livewire::actingAs($pengawas)
            ->test(ListInvoices::class)
            ->assertCanSeeTableRecords([$hidup])
            ->assertCanNotSeeTableRecords([$mati])
            ->filterTable('trashed', 'with')
            ->assertCanSeeTableRecords([$hidup, $mati]);
    }
}
