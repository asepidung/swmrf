<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat User Programmer
        $user = User::firstOrCreate(
            ['username' => 'programmer'],
            [
                'name' => 'Programmer Utama',
                'email' => 'programmer@swm.com',
                'password' => Hash::make('12345678'),
                'is_active' => true,
            ]
        );

        // 2. Buat Role Super Admin (PENTING!)
        $role = Role::firstOrCreate(['name' => 'super_admin']);

        // 3. Assign Role ke User
        $user->assignRole($role);
    }
}
