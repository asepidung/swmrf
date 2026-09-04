<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'gender',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    /**
     * Check if the user is a programmer (superuser).
     */
    public function isProgrammer(): bool
    {
        return $this->role === 'programmer';
    }

    /**
     * The permissions that belong to the user.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    /**
     * Nama izin yang dipegang pengguna ini, diingat selama satu permintaan.
     *
     * @var array<string, true>|null
     */
    private ?array $izinTersimpan = null;

    /**
     * Punya izin ini atau tidak.
     *
     * Jawabannya DIINGAT sampai permintaan berakhir. Sebelumnya tiap
     * pemanggilan menembakkan satu kueri sendiri, dan pemanggilnya banyak:
     * hampir setiap tombol yang menggerakkan angka sungguhan dijaga izin, dan
     * jumlahnya terus bertambah -- Approve dan Unlock retur, batas susut
     * Repack, pembatalan pembayaran, dan seterusnya. Satu halaman daftar dua
     * puluh baris beraksi bisa menembakkan puluhan kueri untuk pertanyaan yang
     * jawabannya sama persis.
     *
     * Seluruh izinnya diambil sekali, bukan satu per satu, karena pengguna
     * yang ditanya satu izin hampir selalu ditanya izin lain di halaman yang
     * sama.
     *
     * Ingatannya HANYA seumur permintaan. Mengubah izin lewat form User
     * berlaku pada permintaan berikutnya, dan itu memang yang terjadi:
     * halamannya dimuat ulang sesudah disimpan.
     */
    public function hasPermission(string $permissionName): bool
    {
        if ($this->isProgrammer()) {
            return true;
        }

        if ($this->izinTersimpan === null) {
            $this->izinTersimpan = $this->permissions()
                ->pluck('name')
                ->flip()
                ->map(fn (): bool => true)
                ->all();
        }

        return isset($this->izinTersimpan[$permissionName]);
    }

    /**
     * Lupakan izin yang sudah diingat.
     *
     * Dipakai sesudah izinnya diubah dalam permintaan yang sama -- misalnya di
     * pengujian, yang menyematkan izin lalu langsung menanyakannya.
     */
    public function forgetCachedPermissions(): static
    {
        $this->izinTersimpan = null;

        return $this;
    }
}

