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
            // Bank Accounts Module
            ['name' => 'view_bank_accounts', 'module_name' => 'Bank Accounts', 'description' => 'View bank accounts'],
            ['name' => 'create_bank_accounts', 'module_name' => 'Bank Accounts', 'description' => 'Create bank accounts'],
            ['name' => 'edit_bank_accounts', 'module_name' => 'Bank Accounts', 'description' => 'Edit bank accounts'],
            ['name' => 'delete_bank_accounts', 'module_name' => 'Bank Accounts', 'description' => 'Delete bank accounts'],

            // PO Cattle Module
            ['name' => 'view_purchase_cattles', 'module_name' => 'PO Cattle', 'description' => 'View PO cattle'],
            ['name' => 'create_purchase_cattles', 'module_name' => 'PO Cattle', 'description' => 'Create PO cattle'],
            ['name' => 'edit_purchase_cattles', 'module_name' => 'PO Cattle', 'description' => 'Edit PO cattle'],
            ['name' => 'delete_purchase_cattles', 'module_name' => 'PO Cattle', 'description' => 'Delete PO cattle'],
            ['name' => 'view_deleted_purchase_cattles', 'module_name' => 'PO Cattle', 'description' => 'View deleted PO cattle'],

            // Activity Logs Module
            ['name' => 'view_activity_logs', 'module_name' => 'Activity Logs', 'description' => 'View activity logs'],
            // Cattle Receiving Module
            ['name' => 'view_cattle_receivings', 'module_name' => 'Cattle Receiving', 'description' => 'View cattle receivings'],
            ['name' => 'create_cattle_receivings', 'module_name' => 'Cattle Receiving', 'description' => 'Create cattle receivings'],
            ['name' => 'edit_cattle_receivings', 'module_name' => 'Cattle Receiving', 'description' => 'Edit cattle receivings'],
            ['name' => 'delete_cattle_receivings', 'module_name' => 'Cattle Receiving', 'description' => 'Delete cattle receivings'],
            ['name' => 'view_deleted_cattle_receivings', 'module_name' => 'Cattle Receiving', 'description' => 'View deleted cattle receivings'],

            // Cattle Weighing Module
            ['name' => 'view_cattle_weighings', 'module_name' => 'Cattle Weighing', 'description' => 'View cattle weighings'],
            ['name' => 'create_cattle_weighings', 'module_name' => 'Cattle Weighing', 'description' => 'Create cattle weighings'],
            ['name' => 'edit_cattle_weighings', 'module_name' => 'Cattle Weighing', 'description' => 'Edit cattle weighings'],
            ['name' => 'delete_cattle_weighings', 'module_name' => 'Cattle Weighing', 'description' => 'Delete cattle weighings'],
            ['name' => 'view_deleted_cattle_weighings', 'module_name' => 'Cattle Weighing', 'description' => 'View deleted cattle weighings'],

            // Financial Loss Module
            ['name' => 'view_financial_losses', 'module_name' => 'Financial Loss', 'description' => 'View financial losses'],

            // Carcass Module
            ['name' => 'view_carcasses', 'module_name' => 'Carcass', 'description' => 'View carcasses'],
            ['name' => 'create_carcasses', 'module_name' => 'Carcass', 'description' => 'Create carcasses'],
            ['name' => 'edit_carcasses', 'module_name' => 'Carcass', 'description' => 'Edit carcasses'],
            ['name' => 'delete_carcasses', 'module_name' => 'Carcass', 'description' => 'Delete carcasses'],
            ['name' => 'view_deleted_carcasses', 'module_name' => 'Carcass', 'description' => 'View deleted carcasses'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['name' => $perm['name']], $perm);
        }

        // 2. Seed Programmer (Superuser)
        User::updateOrCreate(
            ['username' => 'programmer'],
            [
                'name' => 'Programmer SWM',
                'password' => 'programmerpassword',
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
                'password' => '1234', // default password to trigger change
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
