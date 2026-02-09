<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('chart_of_accounts')->delete();

        $accounts = [
            // ========== 1. ASET ==========
            
            // Kas & Bank
            ['code' => '1.1.01.01', 'name' => 'Kas Besar', 'type' => 'asset', 'category' => 'kas_bank', 'is_cash' => true],
            ['code' => '1.1.01.02', 'name' => 'Kas Kecil', 'type' => 'asset', 'category' => 'kas_bank', 'is_cash' => true],
            ['code' => '1.1.02.02', 'name' => 'Bank Mandiri', 'type' => 'asset', 'category' => 'kas_bank', 'is_cash' => true],
            ['code' => '1.1.02.04', 'name' => 'Bank BNI', 'type' => 'asset', 'category' => 'kas_bank', 'is_cash' => true],

            // Piutang
            ['code' => '1.2.01', 'name' => 'Piutang Usaha', 'type' => 'asset', 'category' => 'piutang', 'is_cash' => false],
            ['code' => '1.2.02', 'name' => 'Piutang supplier', 'type' => 'asset', 'category' => 'piutang', 'is_cash' => false],
            ['code' => '1.2.03', 'name' => 'Piutang Lain-lain', 'type' => 'asset', 'category' => 'piutang', 'is_cash' => false],
            
            // Persediaan
            ['code' => '1.3.01', 'name' => 'Persediaan Bahan Baku', 'type' => 'asset', 'category' => 'persediaan', 'is_cash' => false],
            ['code' => '1.3.03', 'name' => 'Persediaan Barang Jadi', 'type' => 'asset', 'category' => 'persediaan', 'is_cash' => false],
            
            // Aset Tetap
            ['code' => '1.4.01', 'name' => 'Tanah', 'type' => 'asset', 'category' => 'aset_tetap', 'is_cash' => false],
            ['code' => '1.4.02', 'name' => 'Bangunan', 'type' => 'asset', 'category' => 'aset_tetap', 'is_cash' => false],
            ['code' => '1.4.03', 'name' => 'Mesin Produksi', 'type' => 'asset', 'category' => 'aset_tetap', 'is_cash' => false],
            ['code' => '1.4.04', 'name' => 'Kendaraan', 'type' => 'asset', 'category' => 'aset_tetap', 'is_cash' => false],
            ['code' => '1.4.06', 'name' => 'Akumulasi Penyusutan', 'type' => 'asset', 'category' => 'aset_tetap', 'is_cash' => false],
            
            // ========== 2. KEWAJIBAN ==========
            // Utang Lancar
            ['code' => '2.1.01', 'name' => 'Utang Usaha', 'type' => 'liability', 'category' => 'utang_lancar', 'is_cash' => false],
            ['code' => '2.1.02', 'name' => 'Utang Gaji', 'type' => 'liability', 'category' => 'utang_lancar', 'is_cash' => false],
            ['code' => '2.1.03', 'name' => 'Utang Pajak', 'type' => 'liability', 'category' => 'utang_lancar', 'is_cash' => false],
            ['code' => '2.1.04', 'name' => 'Utang Lain-lain', 'type' => 'liability', 'category' => 'utang_lancar', 'is_cash' => false],
            
            // Utang Jangka Panjang
            ['code' => '2.2.01', 'name' => 'Utang Bank', 'type' => 'liability', 'category' => 'utang_jangka_panjang', 'is_cash' => false],
            ['code' => '2.2.02', 'name' => 'Utang Pembelian Aset', 'type' => 'liability', 'category' => 'utang_jangka_panjang', 'is_cash' => false],
            
            // ========== 3. MODAL ==========
            
            ['code' => '3.1.01', 'name' => 'Modal Pemilik', 'type' => 'equity', 'category' => 'modal', 'is_cash' => false],
            ['code' => '3.2.01', 'name' => 'Laba Ditahan', 'type' => 'equity', 'category' => 'modal', 'is_cash' => false],
            ['code' => '3.3.01', 'name' => 'Laba Tahun Berjalan', 'type' => 'equity', 'category' => 'modal', 'is_cash' => false],
            
            // ========== 4. PENDAPATAN ==========
            
            ['code' => '4.1.01', 'name' => 'Pendapatan Penjualan Air Galon', 'type' => 'revenue', 'category' => 'pendapatan_usaha', 'is_cash' => false],
            ['code' => '4.1.02', 'name' => 'Pendapatan Penjualan Air Cup', 'type' => 'revenue', 'category' => 'pendapatan_usaha', 'is_cash' => false],
            ['code' => '4.1.03', 'name' => 'Pendapatan Penjualan Air Botol', 'type' => 'revenue', 'category' => 'pendapatan_usaha', 'is_cash' => false],
            ['code' => '4.2.01', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue', 'category' => 'pendapatan_lain', 'is_cash' => false],
            
            // ========== 5. BEBAN ==========
            // Beban Produksi
            ['code' => '5.1.01', 'name' => 'Beban Bahan Baku', 'type' => 'expense', 'category' => 'beban_produksi', 'is_cash' => false],
            ['code' => '5.1.02', 'name' => 'Beban Bahan Penolong', 'type' => 'expense', 'category' => 'beban_produksi', 'is_cash' => false],
            ['code' => '5.1.04', 'name' => 'Beban Listrik Produksi', 'type' => 'expense', 'category' => 'beban_produksi', 'is_cash' => false],
            ['code' => '5.1.05', 'name' => 'Beban Pemeliharaan Mesin', 'type' => 'expense', 'category' => 'beban_produksi', 'is_cash' => false],
            
            // Beban Operasional
            ['code' => '5.2.01', 'name' => 'Beban Gaji Karyawan', 'type' => 'expense', 'category' => 'beban_operasional', 'is_cash' => false],
            ['code' => '5.2.02', 'name' => 'Beban Listrik', 'type' => 'expense', 'category' => 'beban_operasional', 'is_cash' => false],
            ['code' => '5.2.03', 'name' => 'Beban Air', 'type' => 'expense', 'category' => 'beban_operasional', 'is_cash' => false],
            ['code' => '5.2.06', 'name' => 'Beban Transportasi', 'type' => 'expense', 'category' => 'beban_operasional', 'is_cash' => false],
            ['code' => '5.2.07', 'name' => 'Beban Penyusutan', 'type' => 'expense', 'category' => 'beban_operasional', 'is_cash' => false],
            
            // Beban Penjualan
            ['code' => '5.3.01', 'name' => 'Beban Pengiriman', 'type' => 'expense', 'category' => 'beban_penjualan', 'is_cash' => false],
            ['code' => '5.3.02', 'name' => 'Beban Promosi', 'type' => 'expense', 'category' => 'beban_penjualan', 'is_cash' => false],
            ['code' => '5.3.03', 'name' => 'Beban Komisi Sales', 'type' => 'expense', 'category' => 'beban_penjualan', 'is_cash' => false],
            
            // Beban Lain-lain
            ['code' => '5.4.01', 'name' => 'Beban Bunga Bank', 'type' => 'expense', 'category' => 'beban_lain', 'is_cash' => false],
            ['code' => '5.4.02', 'name' => 'Beban Pajak', 'type' => 'expense', 'category' => 'beban_lain', 'is_cash' => false],
            ['code' => '5.4.03', 'name' => 'Beban Administrasi Bank', 'type' => 'expense', 'category' => 'beban_lain', 'is_cash' => false],
            ['code' => '5.4.04', 'name' => 'Beban Lain-lain', 'type' => 'expense', 'category' => 'beban_lain', 'is_cash' => false],
        ];

        foreach ($accounts as $account) {
            ChartOfAccount::create($account);
        }
    }
}