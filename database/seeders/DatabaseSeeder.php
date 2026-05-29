<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Permissions
        $permissions = [
            ['name' => 'view_users', 'description' => 'View users'],
            ['name' => 'create_users', 'description' => 'Create users'],
            ['name' => 'edit_users', 'description' => 'Edit users'],
            ['name' => 'delete_users', 'description' => 'Delete users'],
            ['name' => 'reset_password', 'description' => 'Reset password'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['name' => $perm['name']], $perm);
        }

        // 2. Seed Programmer (Superuser)
        User::updateOrCreate(
            ['username' => 'programmer'],
            [
                'name' => 'Programmer SWM',
                'password' => Hash::make('programmerpassword'),
                'gender' => 'L',
                'role' => 'programmer',
                'is_active' => true,
            ]
        );

        // 3. Seed Employee (Normal User)
        $employee = User::updateOrCreate(
            ['username' => 'employee'],
            [
                'name' => 'Karyawan SWM',
                'password' => Hash::make('1234'), // default password to trigger change
                'gender' => 'P',
                'role' => 'employee',
                'is_active' => true,
            ]
        );

        // Give some initial permissions to employee (e.g. view_users)
        $viewUsersPermission = Permission::where('name', 'view_users')->first();
        if ($viewUsersPermission) {
            $employee->permissions()->sync([$viewUsersPermission->id]);
        }
    }
}
