<?php

namespace Database\Seeders;

use App\Models\Voucher;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. New User Promo (COBAINBARU): Potongan harga langsung Rp 20.000
        Voucher::create([
            'code' => 'COBAINBARU',
            'type' => 'fixed',
            'value' => 20000.00,
            'max_discount' => 20000.00,
            'min_spend' => 0.00,
            'limit_total' => 1000,
            'limit_per_user' => 1,
            'used_count' => 0,
            'expires_at' => now()->addDays(90),
            'is_active' => true,
        ]);

        // 2. Free Shipping Promo (FREEONGKIR): Potongan ongkir s.d Rp 20.000, min belanja Rp 150.000
        Voucher::create([
            'code' => 'FREEONGKIR',
            'type' => 'shipping',
            'value' => 20000.00,
            'max_discount' => 20000.00,
            'min_spend' => 150000.00,
            'limit_total' => 2000,
            'limit_per_user' => 1,
            'used_count' => 0,
            'expires_at' => now()->addDays(90),
            'is_active' => true,
        ]);

        // 3. Extra Percent Discount Promo (DISKONHEBOH): Potongan 15%, maks diskon Rp 50.000, min belanja Rp 100.000
        Voucher::create([
            'code' => 'DISKONHEBOH',
            'type' => 'percentage',
            'value' => 15.00,
            'max_discount' => 50000.00,
            'min_spend' => 100000.00,
            'limit_total' => 500,
            'limit_per_user' => 1,
            'used_count' => 0,
            'expires_at' => now()->addDays(60),
            'is_active' => true,
        ]);
    }
}
