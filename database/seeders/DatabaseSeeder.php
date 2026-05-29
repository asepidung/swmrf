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
            // User Module
            ['name' => 'view_users', 'module_name' => 'Users', 'description' => 'View users'],
            ['name' => 'create_users', 'module_name' => 'Users', 'description' => 'Create users'],
            ['name' => 'edit_users', 'module_name' => 'Users', 'description' => 'Edit users'],
            ['name' => 'delete_users', 'module_name' => 'Users', 'description' => 'Delete users'],
            ['name' => 'reset_password', 'module_name' => 'Users', 'description' => 'Reset password'],
            
            // Supplier Module
            ['name' => 'view_suppliers', 'module_name' => 'Suppliers', 'description' => 'View suppliers'],
            ['name' => 'create_suppliers', 'module_name' => 'Suppliers', 'description' => 'Create suppliers'],
            ['name' => 'edit_suppliers', 'module_name' => 'Suppliers', 'description' => 'Edit suppliers'],
            ['name' => 'delete_suppliers', 'module_name' => 'Suppliers', 'description' => 'Delete suppliers'],

            // Materials Module
            ['name' => 'view_materials', 'module_name' => 'Logistic Items', 'description' => 'View logistic items'],
            ['name' => 'create_materials', 'module_name' => 'Logistic Items', 'description' => 'Create logistic items'],
            ['name' => 'edit_materials', 'module_name' => 'Logistic Items', 'description' => 'Edit logistic items'],
            ['name' => 'delete_materials', 'module_name' => 'Logistic Items', 'description' => 'Delete logistic items'],
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
