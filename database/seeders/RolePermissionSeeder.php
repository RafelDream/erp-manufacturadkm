<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view-master', 'create-master', 'update-master', 'delete-master',
            'view-purchase', 'create-purchase', 'update-purchase',
            'view-inventory', 'create-production', 'update-stock', 'qc-check',
            'view-sales', 'create-sales',
            'create-delivery', 'update-delivery',
            'view-report',   'manage-units', 'manage-products', 'manage-raw-materials',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                "guard_name" => 'web',
            ]);
        }

        $roles = [
            'super-admin',
            'admin-operasional',
            'admin-penjualan',
            'staff-gudang',
            'staff-produksi',
            'qc',
            'kurir',
            'owner',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);
        }

        //Assign permissions
        Role::findByName('super-admin')->givePermissionTo(Permission::all());

        Role::findByName('admin-operasional')->givePermissionTo([
            'view-master', 'create-master', 'update-master',
            'view-purchase', 'create-purchase', 'update-purchase',
            'view-inventory', 'update-stock', 'manage-products',
            'manage-raw-materials',
        ]);
        
        Role::findByName('admin-penjualan')->givePermissionTo([
            'view-sales', 'create-sales', 'create-delivery',
        ]);

        Role::findByName('staff-gudang')->givePermissionTo([
            'view-inventory', 'update-stock',
        ]);

        Role::findByName('staff-produksi')->givePermissionTo([
            'view-inventory', 'create-production',
        ]);

        Role::findByName('qc')->givePermissionTo([
            'qc-check',
        ]);

        Role::findByName('kurir')->givePermissionTo([
            'update-delivery',
        ]);

        Role::findByName('owner')->givePermissionTo([
            'view-report', 'view-sales', 'view-purchase',
        ]);



    }

}
