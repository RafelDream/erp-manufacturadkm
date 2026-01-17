<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@erp.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_active' => True,
            ] 
        );

        $user->assignRole('super-admin');
    }
}
