<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Seed RajaOngkir Geography
        $this->call(RajaOngkirSeeder::class);

        // 1. Create Admin
        User::create([
            'name' => 'Store Admin',
            'email' => 'admin@e-shoesbox.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => '08123456789',
            'remember_token' => Str::random(10),
        ]);

        // 2. Create standard Customer
        User::create([
            'name' => 'Demo Customer',
            'email' => 'customer@e-shoesbox.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'customer',
            'phone' => '08129876543',
            'remember_token' => Str::random(10),
        ]);

        // 3. Create Categories
        $running = Category::create([
            'name' => 'Running',
            'slug' => 'running',
            'description' => 'Comfortable running and athletic shoes.',
        ]);

        $sneakers = Category::create([
            'name' => 'Sneakers',
            'slug' => 'sneakers',
            'description' => 'Stylish and daily wear sneakers.',
        ]);

        $casual = Category::create([
            'name' => 'Casual',
            'slug' => 'casual',
            'description' => 'Relaxed shoes for everyday lifestyle.',
        ]);

        // 4. Create Products
        Product::create([
            'category_id' => $running->id,
            'name' => 'AlphaRunner 3000',
            'slug' => 'alpharunner-3000',
            'description' => 'High-performance running shoe with breathable mesh and responsive cushioning.',
            'price' => 1250000.00,
            'stock' => 15,
            'weight' => 320, // grams
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $sneakers->id,
            'name' => 'StreetVibe Retro',
            'slug' => 'streetvibe-retro',
            'description' => 'Classic canvas sneakers with durable rubber outsoles and retro design lines.',
            'price' => 850000.00,
            'stock' => 20,
            'weight' => 450, // grams
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $casual->id,
            'name' => 'UrbanLoafers Premium',
            'slug' => 'urbanloafers-premium',
            'description' => 'Elegant slip-on casual shoes handcrafted from premium synthetic leather.',
            'price' => 950000.00,
            'stock' => 10,
            'weight' => 500, // grams
            'is_active' => true,
        ]);
    }
}
