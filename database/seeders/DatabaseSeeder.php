<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
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
            'promo_tag' => 'Ramadhan',
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
            'promo_tag' => 'Natal',
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

        $additionalProducts = [
            // Running (8 shoes)
            [
                'category_id' => $running->id,
                'name' => 'Pegasus Run 40',
                'slug' => 'pegasus-run-40',
                'description' => 'Responsive running shoe with durable cushioning and engineered mesh upper.',
                'price' => 1800000.00,
                'stock' => 25,
                'weight' => 280,
                'is_active' => true,
            ],
            [
                'category_id' => $running->id,
                'name' => 'Flyknit React Element',
                'slug' => 'flyknit-react-element',
                'description' => 'Lightweight flyknit upper with React foam technology for smooth daily runs.',
                'price' => 2100000.00,
                'stock' => 12,
                'weight' => 270,
                'is_active' => true,
            ],
            [
                'category_id' => $running->id,
                'name' => 'Speedster Pro Carbon',
                'slug' => 'speedster-pro-carbon',
                'description' => 'Carbon-plated racing shoe optimized for speed and maximum energy return.',
                'price' => 3200000.00,
                'stock' => 8,
                'weight' => 220,
                'is_active' => true,
            ],
            [
                'category_id' => $running->id,
                'name' => 'TrailBlazer Elite',
                'slug' => 'trailblazer-elite',
                'description' => 'Rugged multi-surface outsole with waterproof protection for demanding trail runs.',
                'price' => 1950000.00,
                'stock' => 14,
                'weight' => 350,
                'is_active' => true,
            ],
            [
                'category_id' => $running->id,
                'name' => 'CushionMax v2',
                'slug' => 'cushionmax-v2',
                'description' => 'Plush double-stacked foam cushioning for maximum impact absorption on roads.',
                'price' => 1400000.00,
                'stock' => 18,
                'weight' => 310,
                'is_active' => true,
            ],
            [
                'category_id' => $running->id,
                'name' => 'Marathon Glide',
                'slug' => 'marathon-glide',
                'description' => 'Breathable mesh upper with midfoot wrap for long-distance training comfort.',
                'price' => 1650000.00,
                'stock' => 22,
                'weight' => 290,
                'is_active' => true,
            ],
            [
                'category_id' => $running->id,
                'name' => 'Aerolight Fly',
                'slug' => 'aerolight-fly',
                'description' => 'Minimalist barefoot-feel running shoe designed for natural gait and agility.',
                'price' => 1100000.00,
                'stock' => 15,
                'weight' => 180,
                'is_active' => true,
            ],
            [
                'category_id' => $running->id,
                'name' => 'StormRun Shield',
                'slug' => 'stormrun-shield',
                'description' => 'Weatherized running shoe with DWR shield and reflective design details.',
                'price' => 1850000.00,
                'stock' => 10,
                'weight' => 300,
                'is_active' => true,
            ],

            // Sneakers (9 shoes)
            [
                'category_id' => $sneakers->id,
                'name' => 'Airforce One Classic',
                'slug' => 'airforce-one-classic',
                'description' => 'Iconic premium leather streetwear sneaker with comfortable Air-sole cushioning.',
                'price' => 1500000.00,
                'stock' => 30,
                'weight' => 480,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'Court Royale Retro',
                'slug' => 'court-royale-retro',
                'description' => 'Clean and timeless tennis-inspired leather sneakers for everyday simplicity.',
                'price' => 800000.00,
                'stock' => 25,
                'weight' => 420,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'CloudWalk Lite',
                'slug' => 'cloudwalk-lite',
                'description' => 'Ultra-breathable knit sneakers with a flexible sole for lightweight daily comfort.',
                'price' => 950000.00,
                'stock' => 40,
                'weight' => 360,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'HighTop Vintage Canvas',
                'slug' => 'hightop-vintage-canvas',
                'description' => 'Retro high-top style canvas shoes with vulcanized soles and vintage logos.',
                'price' => 750000.00,
                'stock' => 15,
                'weight' => 520,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'Chunky Dad Cruiser',
                'slug' => 'chunky-dad-cruiser',
                'description' => 'Bold retro silhouette with chunky foam midsole for ultimate lifestyle aesthetics.',
                'price' => 1200000.00,
                'stock' => 12,
                'weight' => 550,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'SlipStream Canvas',
                'slug' => 'slipstream-canvas',
                'description' => 'Casual slip-on canvas shoes featuring elastic goring for quick on-and-off.',
                'price' => 600000.00,
                'stock' => 35,
                'weight' => 320,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'NightRider Reflective',
                'slug' => 'nightrider-reflective',
                'description' => 'Futuristic low-top sneakers featuring high-visibility 3M reflective panels.',
                'price' => 1450000.00,
                'stock' => 18,
                'weight' => 460,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'SkatePro Vulc',
                'slug' => 'skatepro-vulc',
                'description' => 'Durable suede skate shoes with rubber toe caps and sticky waffle traction.',
                'price' => 900000.00,
                'stock' => 20,
                'weight' => 440,
                'is_active' => true,
            ],
            [
                'category_id' => $sneakers->id,
                'name' => 'Heritage Court 88',
                'slug' => 'heritage-court-88',
                'description' => 'Retro tennis shoes made with premium full-grain leather and suede accents.',
                'price' => 1150000.00,
                'stock' => 16,
                'weight' => 430,
                'is_active' => true,
            ],

            // Casual (8 shoes)
            [
                'category_id' => $casual->id,
                'name' => 'SmartLoafers Suede',
                'slug' => 'smartloafers-suede',
                'description' => 'Refined slip-on loafers in premium suede with comfortable leather lining.',
                'price' => 1100000.00,
                'stock' => 12,
                'weight' => 460,
                'is_active' => true,
                'promo_tag' => 'Idul Fitri',
            ],
            [
                'category_id' => $casual->id,
                'name' => 'Chelsea Classic Leather',
                'slug' => 'chelsea-classic-leather',
                'description' => 'Timeless leather Chelsea boots with elastic side panels and pull tabs.',
                'price' => 1750000.00,
                'stock' => 8,
                'weight' => 600,
                'is_active' => true,
                'promo_tag' => 'Tahun Baru',
            ],
            [
                'category_id' => $casual->id,
                'name' => 'BoatShoe Navigator',
                'slug' => 'boatshoe-navigator',
                'description' => 'Hand-sewn leather boat shoes with non-marking, slip-resistant rubber outsoles.',
                'price' => 1250000.00,
                'stock' => 14,
                'weight' => 480,
                'is_active' => true,
                'promo_tag' => 'Imlek',
            ],
            [
                'category_id' => $casual->id,
                'name' => 'Canvas Espadrilles',
                'slug' => 'canvas-espadrilles',
                'description' => 'Lightweight slip-on canvas shoes featuring a traditional braided jute midsole.',
                'price' => 550000.00,
                'stock' => 25,
                'weight' => 280,
                'is_active' => true,
            ],
            [
                'category_id' => $casual->id,
                'name' => 'Derby Modern Classic',
                'slug' => 'derby-modern-classic',
                'description' => 'Clean leather derby dress shoes ideal for office and formal events.',
                'price' => 1400000.00,
                'stock' => 10,
                'weight' => 520,
                'is_active' => true,
            ],
            [
                'category_id' => $casual->id,
                'name' => 'Monkstrap Prestige',
                'slug' => 'monkstrap-prestige',
                'description' => 'Double monkstrap dress shoes crafted from polished calfskin leather.',
                'price' => 1850000.00,
                'stock' => 6,
                'weight' => 580,
                'is_active' => true,
            ],
            [
                'category_id' => $casual->id,
                'name' => 'DesertBoot Heritage',
                'slug' => 'desertboot-heritage',
                'description' => 'Iconic desert boots made with soft suede uppers and comfortable crepe soles.',
                'price' => 1350000.00,
                'stock' => 11,
                'weight' => 540,
                'is_active' => true,
            ],
            [
                'category_id' => $casual->id,
                'name' => 'SlipOn Oasis Textures',
                'slug' => 'slipon-oasis-textures',
                'description' => 'Summer casual slip-on shoes with textured fabric uppers and cushioned insoles.',
                'price' => 700000.00,
                'stock' => 20,
                'weight' => 350,
                'is_active' => true,
            ],
        ];

        foreach ($additionalProducts as $p) {
            Product::create($p);
        }

        // 5. Seed Product Variants for all products
        $sizes = ['39', '40', '41', '42', '43'];
        $colors = ['Hitam', 'Putih', 'Abu-abu', 'Biru'];
        $products = Product::all();
        foreach ($products as $prod) {
            $variantCount = rand(3, 4);
            $selectedCombinations = [];
            for ($i = 0; $i < $variantCount; $i++) {
                $size = $sizes[array_rand($sizes)];
                $color = $colors[array_rand($colors)];
                $combo = "$size-$color";
                if (in_array($combo, $selectedCombinations)) {
                    continue;
                }
                $selectedCombinations[] = $combo;

                ProductVariant::create([
                    'product_id' => $prod->id,
                    'size' => $size,
                    'color' => $color,
                    'stock' => rand(5, 15),
                ]);
            }
        }

        // 6. Seed Product Reviews
        $users = User::where('role', 'customer')->get();
        if ($users->isNotEmpty()) {
            $comments = [
                5 => ['Sepatu sangat nyaman dipakai dan empuk banget!', 'Kualitas premium, jahitan rapi, dan pengiriman super cepat.', 'Sangat memuaskan, pas di kaki dan desainnya trendi.'],
                4 => ['Bagus sekali, ukuran pas tapi kardusnya agak penyok.', 'Nyaman dipakai jalan jauh, recommended seller!', 'Kualitas oke sesuai harga, respon admin ramah.'],
                3 => ['Ukuran agak sempit dibanding standar, tapi bahannya bagus.', 'Lumayan untuk harga segini, tapi lemnya kurang rapi sedikit.'],
            ];

            foreach ($products as $prod) {
                $reviewCount = rand(2, 3);
                for ($i = 0; $i < $reviewCount; $i++) {
                    $user = $users->random();
                    $rating = rand(3, 5);
                    $commentList = $comments[$rating];
                    $comment = $commentList[array_rand($commentList)];

                    Review::create([
                        'user_id' => $user->id,
                        'product_id' => $prod->id,
                        'rating' => $rating,
                        'comment' => $comment,
                    ]);
                }
            }
        }

        // 7. Seed Active Campaigns
        Campaign::create([
            'title' => 'Ramadhan Berkarya Mega Sale',
            'subtitle' => 'Dapatkan diskon hingga 70% untuk sneakers pilihan selama bulan suci.',
            'description' => 'Diskon khusus Ramadhan untuk model running dan sneakers.',
            'badge_text' => 'PROMO BERKAH',
            'promo_tag' => 'Ramadhan',
            'button_text' => 'Belanja Sekarang',
            'button_link' => '#catalog',
            'bg_gradient' => 'emerald',
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(15),
            'is_active' => true,
        ]);

        Campaign::create([
            'title' => 'Festival Hari Raya Promo',
            'subtitle' => 'Lengkapi langkah suci Anda dengan koleksi sneakers terbaik kami.',
            'description' => 'Promo hari raya.',
            'badge_text' => 'SPESIAL MUDIK',
            'promo_tag' => 'Idul Fitri',
            'button_text' => 'Mulai Belanja',
            'button_link' => '#catalog',
            'bg_gradient' => 'rose',
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(20),
            'is_active' => true,
        ]);

        // 8. Seed Vouchers
        $this->call(VoucherSeeder::class);
    }
}
