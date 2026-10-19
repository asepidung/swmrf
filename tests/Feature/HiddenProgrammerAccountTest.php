<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ActivityLogResource;
use App\Filament\Admin\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Models\Costing;
use App\Models\Permission;
use App\Models\Scopes\HideProgrammerAccounts;
use App\Models\User;
use App\Notifications\TaskAlert;
use App\Support\TaskNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Akun programmer (saepullrock) tertutup di dalam aplikasi bagi semua orang kecuali dirinya sendiri: daftar
 * pengguna, pilihan pengguna, relasi pembuat, dan Riwayat Aktivitas. Keputusan Owner, 19 Oktober 2026.
 */
class HiddenProgrammerAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $programmer;

    private User $employee;

    private User $logViewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->programmer = User::factory()->create(['username' => 'saepullrock', 'role' => 'programmer', 'is_active' => true]);
        $this->employee = User::factory()->create(['username' => 'karyawan', 'role' => 'employee', 'is_active' => true]);

        $this->logViewer = User::factory()->create(['username' => 'pelihat-log', 'role' => 'employee', 'is_active' => true]);
        $this->logViewer->permissions()->attach(
            Permission::firstOrCreate(['name' => 'view_activity_logs'], ['module_name' => 'Activity Logs', 'description' => 'View activity logs'])->id
        );
    }

    /** @test */
    public function an_employee_cannot_read_the_programmer_account_any_way_the_application_reads_users(): void
    {
        $this->actingAs($this->employee);

        $this->assertNull(User::find($this->programmer->id));
        $this->assertNull(User::where('username', 'saepullrock')->first());
        $this->assertNotContains('saepullrock', User::pluck('username')->all());
        $this->assertSame(0, User::where('role', 'programmer')->count());
        $this->assertContains('karyawan', User::pluck('username')->all());
    }

    /** @test */
    public function a_document_created_by_the_programmer_shows_no_creator_to_an_employee_and_does_not_break(): void
    {
        $costing = Costing::create(['costing_date' => '2026-10-19', 'boning_id' => \App\Models\Boning::create([
            'boning_date' => '2026-10-19', 'created_by' => $this->programmer->id,
        ])->id, 'created_by' => $this->programmer->id]);

        $this->actingAs($this->employee);
        $this->assertNull(Costing::find($costing->id)->createdBy, 'Pembuat programmer tidak terbaca oleh karyawan.');

        $this->actingAs($this->programmer);
        $this->assertSame('saepullrock', Costing::find($costing->id)->createdBy?->username, 'Pemiliknya tetap melihat dirinya.');
    }

    /** @test */
    public function the_programmer_sees_only_himself_and_no_other_programmer(): void
    {
        $other = User::factory()->create(['username' => 'programmer-lain', 'role' => 'programmer', 'is_active' => true]);

        $this->actingAs($this->programmer);

        $this->assertNotNull(User::find($this->programmer->id));
        $this->assertNull(User::find($other->id), 'Programmer lain pun tidak boleh melihat.');
        $this->assertNotNull(User::find($this->employee->id));
    }

    /** Layar Riwayat hanya menampilkan baris yang punya objek (subject), jadi tiap riwayat uji dibuat dengan objek. */
    private function logged(?User $causer, string $text, ?User $subject = null): Activity
    {
        $builder = $causer ? activity()->causedBy($causer) : activity();

        return $builder->performedOn($subject ?? $this->employee)->log($text);
    }

    /** @test */
    public function the_activity_log_hides_everything_the_programmer_did_and_everything_done_to_his_account(): void
    {
        $biasa = $this->logged($this->employee, 'aksi karyawan');
        $oleh = $this->logged($this->programmer, 'aksi programmer');
        $terhadap = $this->logged($this->employee, 'ubah akun programmer', $this->programmer);
        $sistem = $this->logged(null, 'aksi sistem');

        $this->actingAs($this->logViewer);

        Livewire::test(ListActivityLogs::class)
            ->assertCanSeeTableRecords([$biasa, $sistem])
            ->assertCanNotSeeTableRecords([$oleh, $terhadap]);

        $ids = ActivityLogResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($biasa->id, $ids);
        $this->assertContains($sistem->id, $ids);
        $this->assertNotContains($oleh->id, $ids);
        $this->assertNotContains($terhadap->id, $ids);
    }

    /** @test */
    public function the_programmer_still_sees_his_own_history_and_not_that_of_another_programmer(): void
    {
        $other = User::factory()->create(['role' => 'programmer', 'is_active' => true]);
        $miliknya = $this->logged($this->programmer, 'aksi saya');
        $orangLain = $this->logged($other, 'aksi programmer lain');

        $this->actingAs($this->programmer);

        $ids = ActivityLogResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($miliknya->id, $ids);
        $this->assertNotContains($orangLain->id, $ids);
    }

    /** @test */
    public function task_alerts_still_reach_the_programmer_when_an_employee_triggers_them(): void
    {
        Notification::fake();
        $this->programmer->pushSubscriptions()->create(['endpoint' => 'https://fcm.googleapis.com/x', 'public_key' => 'k', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm']);

        $this->actingAs($this->employee);

        $sent = TaskNotifier::notifyPermissionHolders('review_material_requisitions', 'Judul', 'Isi', url('/admin'), 'tag', $this->employee->id);

        $this->assertSame(1, $sent, 'Programmer tetap penerima meski tertutup bagi karyawan.');
        Notification::assertSentTo($this->programmer, TaskAlert::class);
    }

    /** @test */
    public function the_scope_is_off_when_nobody_is_logged_in_so_login_and_artisan_still_find_the_account(): void
    {
        Auth::logout();

        $this->assertNotNull(User::where('username', 'saepullrock')->first());
        $this->assertContains($this->programmer->id, HideProgrammerAccounts::hiddenIds());
    }

    /** @test */
    public function the_programmer_can_log_in_and_open_the_admin_panel(): void
    {
        $this->actingAs($this->programmer)->get('/admin')->assertSuccessful();
    }

    /** @test */
    public function the_user_management_list_never_shows_the_programmer_even_to_himself(): void
    {
        $this->actingAs($this->programmer);

        $this->assertSame(0, \App\Filament\Admin\Resources\UserResource::getEloquentQuery()->where('username', 'saepullrock')->count());
    }
}
