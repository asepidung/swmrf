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

            // Material Adjustments Module
            ['name' => 'view_material_adjustments', 'module_name' => 'Material Adjustments', 'description' => 'View material adjustments'],
            ['name' => 'create_material_adjustments', 'module_name' => 'Material Adjustments', 'description' => 'Create material adjustments'],
            ['name' => 'edit_material_adjustments', 'module_name' => 'Material Adjustments', 'description' => 'Edit material adjustments'],
            ['name' => 'delete_material_adjustments', 'module_name' => 'Material Adjustments', 'description' => 'Delete material adjustments'],
            ['name' => 'view_deleted_material_adjustments', 'module_name' => 'Material Adjustments', 'description' => 'View deleted material adjustments'],

            // Stock Takes Module
            ['name' => 'view_stock_takes', 'module_name' => 'Stock Takes', 'description' => 'View stock takes'],
            ['name' => 'create_stock_takes', 'module_name' => 'Stock Takes', 'description' => 'Create stock takes'],
            ['name' => 'edit_stock_takes', 'module_name' => 'Stock Takes', 'description' => 'Edit stock takes'],
            ['name' => 'delete_stock_takes', 'module_name' => 'Stock Takes', 'description' => 'Delete stock takes'],
            ['name' => 'view_deleted_stock_takes', 'module_name' => 'Stock Takes', 'description' => 'View deleted stock takes'],

            // Material Requisition Module
            ['name' => 'view_material_requisitions', 'module_name' => 'Material Requisition', 'description' => 'View material requisitions'],
            ['name' => 'create_material_requisitions', 'module_name' => 'Material Requisition', 'description' => 'Create material requisitions'],
            ['name' => 'edit_material_requisitions', 'module_name' => 'Material Requisition', 'description' => 'Edit material requisitions'],
            ['name' => 'delete_material_requisitions', 'module_name' => 'Material Requisition', 'description' => 'Delete material requisitions'],
            ['name' => 'review_material_requisitions', 'module_name' => 'Material Requisition', 'description' => 'Review material requisitions'],
            ['name' => 'approve_material_requisitions', 'module_name' => 'Material Requisition', 'description' => 'Approve material requisitions'],

            // Product Requisition Module
            ['name' => 'view_product_requisitions', 'module_name' => 'Product Requisition', 'description' => 'View beef requests'],
            ['name' => 'create_product_requisitions', 'module_name' => 'Product Requisition', 'description' => 'Create beef requests'],
            ['name' => 'edit_product_requisitions', 'module_name' => 'Product Requisition', 'description' => 'Edit beef requests'],
            ['name' => 'delete_product_requisitions', 'module_name' => 'Product Requisition', 'description' => 'Delete beef requests'],
            ['name' => 'review_product_requisitions', 'module_name' => 'Product Requisition', 'description' => 'Review beef requests'],
            ['name' => 'approve_product_requisitions', 'module_name' => 'Product Requisition', 'description' => 'Approve beef requests'],

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

            ['name' => 'set_opening_balance', 'module_name' => 'Bank Accounts', 'description' => 'Set opening balance'],
            ['name' => 'adjust_cash_balance', 'module_name' => 'Bank Accounts', 'description' => 'Record cash adjustment'],
            // Cash Book Module -- read-only, jadi cuma view.
            ['name' => 'view_cash_book', 'module_name' => 'Cash Book', 'description' => 'View cash book'],

            // Financial Loss Module
            ['name' => 'view_financial_losses', 'module_name' => 'Financial Loss', 'description' => 'View financial losses'],

            // Carcass Module
            ['name' => 'view_carcasses', 'module_name' => 'Carcass', 'description' => 'View carcasses'],
            ['name' => 'create_carcasses', 'module_name' => 'Carcass', 'description' => 'Create carcasses'],
            ['name' => 'edit_carcasses', 'module_name' => 'Carcass', 'description' => 'Edit carcasses'],
            ['name' => 'delete_carcasses', 'module_name' => 'Carcass', 'description' => 'Delete carcasses'],
            ['name' => 'view_deleted_carcasses', 'module_name' => 'Carcass', 'description' => 'View deleted carcasses'],
            
            // PO Material Module
            ['name' => 'view_purchase_materials', 'module_name' => 'PO Material', 'description' => 'View PO materials'],
            ['name' => 'print_purchase_materials', 'module_name' => 'PO Material', 'description' => 'Print PO materials'],
            ['name' => 'pay_purchase_materials', 'module_name' => 'PO Material', 'description' => 'Pay down payment on PO materials'],

            // PO Product (Beef) Module
            ['name' => 'view_purchase_products', 'module_name' => 'PO Beef', 'description' => 'View PO beef'],
            ['name' => 'print_purchase_products', 'module_name' => 'PO Beef', 'description' => 'Print PO beef'],
            ['name' => 'pay_purchase_products', 'module_name' => 'PO Beef', 'description' => 'Pay down payment on PO beef'],

            // GR Material Module
            ['name' => 'view_gr_materials', 'module_name' => 'GR Material', 'description' => 'View GR materials'],
            ['name' => 'create_gr_materials', 'module_name' => 'GR Material', 'description' => 'Create GR materials'],
            ['name' => 'edit_gr_materials', 'module_name' => 'GR Material', 'description' => 'Edit GR materials'],
            ['name' => 'delete_gr_materials', 'module_name' => 'GR Material', 'description' => 'Delete GR materials'],
            ['name' => 'view_deleted_gr_materials', 'module_name' => 'GR Material', 'description' => 'View deleted GR materials'],

            // GR Beef Module
            ['name' => 'view_goods_receipt_products', 'module_name' => 'GR Beef', 'description' => 'View GR beef'],
            ['name' => 'create_goods_receipt_products', 'module_name' => 'GR Beef', 'description' => 'Create GR beef'],
            ['name' => 'edit_goods_receipt_products', 'module_name' => 'GR Beef', 'description' => 'Edit GR beef'],
            ['name' => 'delete_goods_receipt_products', 'module_name' => 'GR Beef', 'description' => 'Delete GR beef'],
            ['name' => 'view_deleted_goods_receipt_products', 'module_name' => 'GR Beef', 'description' => 'View deleted GR beef'],

            // Payables Module
            ['name' => 'view_payables', 'module_name' => 'Payables', 'description' => 'View payables'],
            ['name' => 'view_deleted_payables', 'module_name' => 'Payables', 'description' => 'View deleted payables'],
            // Melihat tagihan dan MEMBAYAR tagihan adalah dua tingkat
            // wewenang yang berbeda. Sebelumnya tidak dibedakan sama sekali.
            ['name' => 'pay_payables', 'module_name' => 'Payables', 'description' => 'Pay payables'],

            // Material Stock Module
            ['name' => 'view_material_stocks', 'module_name' => 'Material Stocks', 'description' => 'View material stocks'],
            ['name' => 'view_deleted_material_stocks', 'module_name' => 'Material Stocks', 'description' => 'View deleted material stocks'],

            // Material Stock Movements Module
            ['name' => 'view_material_stock_movements', 'module_name' => 'Material Stock Movements', 'description' => 'View material stock movements'],
            ['name' => 'view_deleted_material_stock_movements', 'module_name' => 'Material Stock Movements', 'description' => 'View deleted material stock movements'],

            // Boning Module
            ['name' => 'view_bonings', 'module_name' => 'Boning', 'description' => 'View bonings'],
            ['name' => 'create_bonings', 'module_name' => 'Boning', 'description' => 'Create bonings'],
            ['name' => 'edit_bonings', 'module_name' => 'Boning', 'description' => 'Edit bonings'],
            ['name' => 'delete_bonings', 'module_name' => 'Boning', 'description' => 'Delete bonings'],
            ['name' => 'lock_bonings', 'module_name' => 'Boning', 'description' => 'Lock/Unlock boning batch'],

            // Beef Stock Module
            ['name' => 'view_beef_stocks', 'module_name' => 'Beef Stocks', 'description' => 'View beef stocks'],
            ['name' => 'view_deleted_beef_stocks', 'module_name' => 'Beef Stocks', 'description' => 'View deleted beef stocks'],

            // Beef Stock Movements Module
            ['name' => 'view_beef_stock_movements', 'module_name' => 'Beef Stock Movements', 'description' => 'View beef stock movements'],
            ['name' => 'view_deleted_beef_stock_movements', 'module_name' => 'Beef Stock Movements', 'description' => 'View deleted beef stock movements'],

            // Repack Module
            ['name' => 'view_repacks', 'module_name' => 'Repack', 'description' => 'View repacks'],
            ['name' => 'create_repacks', 'module_name' => 'Repack', 'description' => 'Create repacks'],
            ['name' => 'edit_repacks', 'module_name' => 'Repack', 'description' => 'Edit repacks'],
            ['name' => 'delete_repacks', 'module_name' => 'Repack', 'description' => 'Delete repacks'],
            ['name' => 'lock_repacks', 'module_name' => 'Repack', 'description' => 'Lock/Unlock repack batch'],
            ['name' => 'view_deleted_repacks', 'module_name' => 'Repack', 'description' => 'View deleted repacks'],

            // Price Lists Module
            ['name' => 'view_price_lists', 'module_name' => 'Price Lists', 'description' => 'View price lists'],
            ['name' => 'create_price_lists', 'module_name' => 'Price Lists', 'description' => 'Create price lists'],
            ['name' => 'edit_price_lists', 'module_name' => 'Price Lists', 'description' => 'Edit price lists'],
            ['name' => 'delete_price_lists', 'module_name' => 'Price Lists', 'description' => 'Delete price lists'],
            ['name' => 'view_deleted_price_lists', 'module_name' => 'Price Lists', 'description' => 'View deleted price lists'],

            // Sales Orders Module
            ['name' => 'view_sales_orders', 'module_name' => 'Sales Orders', 'description' => 'View sales orders'],
            ['name' => 'create_sales_orders', 'module_name' => 'Sales Orders', 'description' => 'Create sales orders'],
            ['name' => 'edit_sales_orders', 'module_name' => 'Sales Orders', 'description' => 'Edit sales orders'],
            ['name' => 'delete_sales_orders', 'module_name' => 'Sales Orders', 'description' => 'Delete sales orders'],
            ['name' => 'view_deleted_sales_orders', 'module_name' => 'Sales Orders', 'description' => 'View deleted sales orders'],

            // Tallies Module
            ['name' => 'view_tallies', 'module_name' => 'Tallies', 'description' => 'View tallies'],
            ['name' => 'create_tallies', 'module_name' => 'Tallies', 'description' => 'Create tallies'],
            ['name' => 'edit_tallies', 'module_name' => 'Tallies', 'description' => 'Edit tallies'],
            ['name' => 'delete_tallies', 'module_name' => 'Tallies', 'description' => 'Delete tallies'],
            ['name' => 'view_deleted_tallies', 'module_name' => 'Tallies', 'description' => 'View deleted tallies'],
            ['name' => 'lock_tallies', 'module_name' => 'Tallies', 'description' => 'Lock tallies'],

            // Delivery Plans Module
            ['name' => 'view_delivery_plans', 'module_name' => 'Delivery Plans', 'description' => 'View delivery plans'],
            ['name' => 'create_delivery_plans', 'module_name' => 'Delivery Plans', 'description' => 'Create delivery plans'],
            ['name' => 'edit_delivery_plans', 'module_name' => 'Delivery Plans', 'description' => 'Edit delivery plans'],
            ['name' => 'delete_delivery_plans', 'module_name' => 'Delivery Plans', 'description' => 'Delete delivery plans'],
            ['name' => 'view_deleted_delivery_plans', 'module_name' => 'Delivery Plans', 'description' => 'View deleted delivery plans'],

            // Delivery Orders Module
            ['name' => 'view_delivery_orders', 'module_name' => 'Delivery Orders', 'description' => 'View delivery orders'],
            ['name' => 'create_delivery_orders', 'module_name' => 'Delivery Orders', 'description' => 'Create delivery orders'],
            ['name' => 'edit_delivery_orders', 'module_name' => 'Delivery Orders', 'description' => 'Edit delivery orders'],
            ['name' => 'delete_delivery_orders', 'module_name' => 'Delivery Orders', 'description' => 'Delete delivery orders'],
            ['name' => 'view_deleted_delivery_orders', 'module_name' => 'Delivery Orders', 'description' => 'View deleted delivery orders'],
            ['name' => 'view_delivery_receipts', 'module_name' => 'Delivery Orders', 'description' => 'View delivery receipts'],

            // Invoices Module
            ['name' => 'view_invoices', 'module_name' => 'Invoices', 'description' => 'View invoices'],
            ['name' => 'create_invoices', 'module_name' => 'Invoices', 'description' => 'Create invoices'],
            ['name' => 'edit_invoices', 'module_name' => 'Invoices', 'description' => 'Edit invoices'],
            ['name' => 'delete_invoices', 'module_name' => 'Invoices', 'description' => 'Delete invoices'],
            ['name' => 'view_deleted_invoices', 'module_name' => 'Invoices', 'description' => 'View deleted invoices'],
            ['name' => 'tukar_faktur', 'module_name' => 'Invoices', 'description' => 'Perform invoice exchange (Tukar Faktur)'],

            // Receivables Module
            ['name' => 'view_receivables', 'module_name' => 'Receivables', 'description' => 'View receivables'],
            ['name' => 'view_deleted_receivables', 'module_name' => 'Receivables', 'description' => 'View deleted receivables'],
            ['name' => 'receive_receivables', 'module_name' => 'Receivables', 'description' => 'Receive payment for receivables'],

            // Mutations Module
            ['name' => 'view_mutations', 'module_name' => 'Mutations', 'description' => 'View mutations'],
            ['name' => 'create_mutations', 'module_name' => 'Mutations', 'description' => 'Create mutations'],
            ['name' => 'edit_mutations', 'module_name' => 'Mutations', 'description' => 'Edit mutations'],
            ['name' => 'delete_mutations', 'module_name' => 'Mutations', 'description' => 'Delete mutations'],
            ['name' => 'view_deleted_mutations', 'module_name' => 'Mutations', 'description' => 'View deleted mutations'],
            
            // Material Usages Module
            ['name' => 'view_material_usages', 'module_name' => 'Material Usages', 'description' => 'View material usages'],
            ['name' => 'create_material_usages', 'module_name' => 'Material Usages', 'description' => 'Create material usages'],
            ['name' => 'edit_material_usages', 'module_name' => 'Material Usages', 'description' => 'Edit material usages'],
            ['name' => 'delete_material_usages', 'module_name' => 'Material Usages', 'description' => 'Delete material usages'],
            
            // Material Stock Takes Module
            ['name' => 'view_material_stock_takes', 'module_name' => 'Material Stock Takes', 'description' => 'View material stock takes'],
            ['name' => 'create_material_stock_takes', 'module_name' => 'Material Stock Takes', 'description' => 'Create material stock takes'],
            ['name' => 'edit_material_stock_takes', 'module_name' => 'Material Stock Takes', 'description' => 'Edit material stock takes'],
            ['name' => 'delete_material_stock_takes', 'module_name' => 'Material Stock Takes', 'description' => 'Delete material stock takes'],
            
            // Permission Stock Takes (Beef) SENGAJA tidak diulang di sini --
            // seluruhnya sudah didaftarkan di blok "Stock Takes Module" di
            // atas, berikut view_deleted_stock_takes yang di blok ini malah
            // terlewat.

            // Sales Returns Module
            ['name' => 'view_sales_returns', 'module_name' => 'Sales Returns', 'description' => 'View sales returns'],
            ['name' => 'create_sales_returns', 'module_name' => 'Sales Returns', 'description' => 'Create sales returns'],
            ['name' => 'edit_sales_returns', 'module_name' => 'Sales Returns', 'description' => 'Edit sales returns'],
            ['name' => 'delete_sales_returns', 'module_name' => 'Sales Returns', 'description' => 'Delete sales returns'],
            
            // Beef Stock Aging Module
            ['name' => 'view_beef_stock_aging', 'module_name' => 'Beef Stock Aging', 'description' => 'View beef stock aging'],
            
            // view_activity_logs SENGAJA tidak didaftarkan lagi di sini.
            //
            // Ia sudah ada di atas dengan module_name 'Activity Logs'. Karena
            // permissions.name unique dan seeder ini memakai updateOrCreate,
            // entri kedua DIAM-DIAM MENIMPA yang pertama -- akibatnya modul
            // 'Activity Logs' tidak pernah benar-benar ada, dan 'System' cuma
            // berisi permission duplikat itu.

            // Grade Module
            ['name' => 'view_grades', 'module_name' => 'Grades', 'description' => 'View grades'],
            ['name' => 'create_grades', 'module_name' => 'Grades', 'description' => 'Create grades'],
            ['name' => 'edit_grades', 'module_name' => 'Grades', 'description' => 'Edit grades'],
            ['name' => 'delete_grades', 'module_name' => 'Grades', 'description' => 'Delete grades'],

            // Warehouse Module
            ['name' => 'view_warehouses', 'module_name' => 'Warehouses', 'description' => 'View warehouses'],
            ['name' => 'create_warehouses', 'module_name' => 'Warehouses', 'description' => 'Create warehouses'],
            ['name' => 'edit_warehouses', 'module_name' => 'Warehouses', 'description' => 'Edit warehouses'],
            ['name' => 'delete_warehouses', 'module_name' => 'Warehouses', 'description' => 'Delete warehouses'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['name' => $perm['name']], $perm);
        }

        // 2. Seed Programmer (Superuser)
        $programmer = User::firstOrNew(['username' => 'saepullrock']);
        if (!$programmer->exists) {
            $programmer->id = 100; // Set explicit ID
        }
        $programmer->name = 'Saepullrock';
        // Password bawaan sengaja '1234' agar middleware CheckPasswordChange
        // langsung memaksa penggantian pada login pertama. Repositori ini
        // publik, jadi akun superuser tidak boleh diseed dengan password yang
        // benar-benar dipakai.
        $programmer->password = '1234';
        $programmer->gender = 'L';
        $programmer->role = 'programmer';
        $programmer->is_active = true;
        $programmer->save();



        // Seed Warehouses
        \App\Models\Warehouse::updateOrCreate(['code' => 'JONGGOL'], ['name' => 'JONGGOL', 'is_active' => true]);
        \App\Models\Warehouse::updateOrCreate(['code' => 'PERUM'], ['name' => 'PERUM', 'is_active' => true]);

        // Seed Grades.
        //
        // ID DIKUNCI SECARA EKSPLISIT DAN TIDAK BOLEH BERUBAH. Digit grade pada
        // barcode 26 karakter mengacu langsung ke id di tabel ini, sehingga
        // menukar urutannya akan membuat seluruh barcode lama salah arti.
        $grades = [
            1 => 'CHILL',
            2 => 'FROZEN',
            3 => 'A',
            4 => 'B',
            5 => 'R',
        ];

        foreach ($grades as $gradeId => $gradeName) {
            \App\Models\Grade::updateOrCreate(
                ['id' => $gradeId],
                ['name' => $gradeName, 'is_active' => true]
            );
        }

        // Akun kas tunai. Dibuat lewat helper idempoten yang sama dipakai
        // saat pembayaran tunai pertama tercatat, supaya barisnya sudah
        // terlihat sejak awal tanpa menunggu transaksi pertama.
        \App\Models\BankAccount::cashAccount();
    }
}
