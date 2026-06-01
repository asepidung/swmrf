<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeletedRecordsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'view_deleted_purchase_cattles', 'module_name' => 'PO Cattle', 'description' => 'View deleted PO cattle'],
            ['name' => 'view_deleted_cattle_receivings', 'module_name' => 'Cattle Receiving', 'description' => 'View deleted cattle receivings'],
            ['name' => 'view_deleted_cattle_weighings', 'module_name' => 'Cattle Weighing', 'description' => 'View deleted cattle weighings'],
        ];

        foreach ($permissions as $perm) {
            \App\Models\Permission::updateOrCreate(['name' => $perm['name']], $perm);
        }
    }
}
