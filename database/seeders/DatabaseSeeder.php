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

            // Cattle Class Module
            ['name' => 'view_cattle_classes', 'module_name' => 'Cattle Classes', 'description' => 'View cattle classes'],
            ['name' => 'create_cattle_classes', 'module_name' => 'Cattle Classes', 'description' => 'Create cattle classes'],
            ['name' => 'edit_cattle_classes', 'module_name' => 'Cattle Classes', 'description' => 'Edit cattle classes'],
            ['name' => 'delete_cattle_classes', 'module_name' => 'Cattle Classes', 'description' => 'Delete cattle classes'],

            // Customers Module
            ['name' => 'view_customers', 'module_name' => 'Customers', 'description' => 'View customers'],
            ['name' => 'create_customers', 'module_name' => 'Customers', 'description' => 'Create customers'],
            ['name' => 'edit_customers', 'module_name' => 'Customers', 'description' => 'Edit customers'],
            ['name' => 'delete_customers', 'module_name' => 'Customers', 'description' => 'Delete customers'],

            // Customer Segments Module
            ['name' => 'view_customer_segments', 'module_name' => 'Customer Segments', 'description' => 'View customer segments'],
            ['name' => 'create_customer_segments', 'module_name' => 'Customer Segments', 'description' => 'Create customer segments'],
            ['name' => 'edit_customer_segments', 'module_name' => 'Customer Segments', 'description' => 'Edit customer segments'],
            ['name' => 'delete_customer_segments', 'module_name' => 'Customer Segments', 'description' => 'Delete customer segments'],

            // Customer Groups Module
            ['name' => 'view_customer_groups', 'module_name' => 'Customer Groups', 'description' => 'View customer groups'],
            ['name' => 'create_customer_groups', 'module_name' => 'Customer Groups', 'description' => 'Create customer groups'],
            ['name' => 'edit_customer_groups', 'module_name' => 'Customer Groups', 'description' => 'Edit customer groups'],
            ['name' => 'delete_customer_groups', 'module_name' => 'Customer Groups', 'description' => 'Delete customer groups'],

            // Product Categories Module
            ['name' => 'view_product_categories', 'module_name' => 'Product Categories', 'description' => 'View product categories'],
            ['name' => 'create_product_categories', 'module_name' => 'Product Categories', 'description' => 'Create product categories'],
            ['name' => 'edit_product_categories', 'module_name' => 'Product Categories', 'description' => 'Edit product categories'],
            ['name' => 'delete_product_categories', 'module_name' => 'Product Categories', 'description' => 'Delete product categories'],

            // Products Module
            ['name' => 'view_products', 'module_name' => 'Products', 'description' => 'View products'],
            ['name' => 'create_products', 'module_name' => 'Products', 'description' => 'Create products'],
            ['name' => 'edit_products', 'module_name' => 'Products', 'description' => 'Edit products'],
            ['name' => 'delete_products', 'module_name' => 'Products', 'description' => 'Delete products'],
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
