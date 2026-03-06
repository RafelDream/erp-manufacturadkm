<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Master
            'view-master', 'create-master', 'update-master', 'delete-master',
            // Purchase
            'view-purchase', 'create-purchase', 'update-purchase',
            // Inventory
            'view-inventory', 'create-production', 'update-stock', 'qc-check',
            // Sales
            'view-sales', 'create-sales',
            // Delivery
            'create-delivery', 'update-delivery',
            // Report
            'view-report',
            // Master data
            'manage-units', 'manage-products', 'manage-raw-materials',
            // Account Payable & Payment (BARU)
            'view-payable',     // Lihat hutang & aging report
            'create-payable',   // Buat hutang dari TTF
            'view-payment',     // Lihat pembayaran
            'create-payment',   // Buat draft pembayaran
            'confirm-payment',  // Konfirmasi pembayaran (hutang lunas)
            // Journal / Accounting (BARU)
            'view-journal',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
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
                'name'       => $role,
                'guard_name' => 'web',
            ]);
        }

        // Super Admin — semua permission
        Role::findByName('super-admin')->givePermissionTo(Permission::all());

        // Admin Operasional — termasuk kelola hutang & konfirmasi bayar
        Role::findByName('admin-operasional')->givePermissionTo([
            'view-master', 'create-master', 'update-master',
            'view-purchase', 'create-purchase', 'update-purchase',
            'view-inventory', 'update-stock',
            'manage-products', 'manage-raw-materials',
            'view-payable', 'create-payable',
            'view-payment', 'create-payment', 'confirm-payment',
            'view-journal',
        ]);

        // Admin Penjualan
        Role::findByName('admin-penjualan')->givePermissionTo([
            'view-sales', 'create-sales', 'create-delivery',
        ]);

        // Staff Gudang
        Role::findByName('staff-gudang')->givePermissionTo([
            'view-inventory', 'update-stock',
        ]);

        // Staff Produksi
        Role::findByName('staff-produksi')->givePermissionTo([
            'view-inventory', 'create-production',
        ]);

        // QC
        Role::findByName('qc')->givePermissionTo([
            'qc-check',
        ]);

        // Kurir
        Role::findByName('kurir')->givePermissionTo([
            'update-delivery',
        ]);

        // Owner — lihat semua laporan termasuk hutang & jurnal
        Role::findByName('owner')->givePermissionTo([
            'view-report', 'view-sales', 'view-purchase',
            'view-payable', 'view-payment', 'view-journal',
        ]);
    }
}