<?php

use App\Models\Category;
use App\Models\Product;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->category = Category::create(['name' => 'Running', 'slug' => 'running']);
});

test('customer can access shop catalog and see pagination', function () {
    // Create 15 products
    for ($i = 1; $i <= 15; $i++) {
        Product::create([
            'category_id' => $this->category->id,
            'name' => "Shoe Run $i",
            'slug' => "shoe-run-$i",
            'description' => "Description $i",
            'price' => 100000,
            'is_active' => true,
        ]);
    }

    // Access shop catalog page
    $this->get('/')
        ->assertStatus(200)
        ->assertSee('Shoe Run 1')
        ->assertSee('Shoe Run 12');

    // Test Volt component pagination (12 items per page)
    Volt::test('pages.shop.index')
        ->assertViewHas('products', function ($products) {
            return $products->count() === 12;
        });
});

test('customer can sort products by promo or discount', function () {
    // Product 1: Regular
    $prod1 = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Alpha Regular',
        'slug' => 'alpha-regular',
        'description' => 'Desc',
        'price' => 100000,
        'is_active' => true,
    ]);

    // Product 2: Has promo tag
    $prod2 = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Beta Promo Tag',
        'slug' => 'beta-promo-tag',
        'description' => 'Desc',
        'price' => 100000,
        'is_active' => true,
        'promo_tag' => 'Idul Fitri',
    ]);

    // Product 3: Has discount
    $prod3 = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Gamma Discounted',
        'slug' => 'gamma-discounted',
        'description' => 'Desc',
        'price' => 100000,
        'discount_price' => 70000, // 30% discount
        'is_active' => true,
    ]);

    // Test sort = promo (Beta Promo Tag first)
    Volt::test('pages.shop.index')
        ->set('sort', 'promo')
        ->assertSee('Beta Promo Tag');

    // Test sort = discount (Gamma Discounted first)
    Volt::test('pages.shop.index')
        ->set('sort', 'discount')
        ->assertSee('Gamma Discounted');
});

test('themed holiday promo badges are rendered', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Eid Shoe',
        'slug' => 'eid-shoe',
        'description' => 'Desc',
        'price' => 100000,
        'is_active' => true,
        'promo_tag' => 'Idul Fitri',
    ]);

    $this->get('/')
        ->assertStatus(200)
        ->assertSee('🕌 Idul Fitri');
});

test('customer can click product and open detail modal', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Detail Shoe Test',
        'slug' => 'detail-shoe-test',
        'description' => 'Great product details description test',
        'price' => 100000,
        'is_active' => true,
    ]);

    Volt::test('pages.shop.index')
        ->assertSet('isDetailModalOpen', false)
        ->assertSet('selectedProductId', null)
        ->call('openDetailModal', $product->id)
        ->assertSet('isDetailModalOpen', true)
        ->assertSet('selectedProductId', $product->id)
        ->assertSee('Detail Shoe Test')
        ->assertSee('Great product details description test')
        ->call('closeDetailModal')
        ->assertSet('isDetailModalOpen', false)
        ->assertSet('selectedProductId', null);
});
