<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->category = Category::create(['name' => 'Running', 'slug' => 'running']);

    // Create test user
    $this->user = User::factory()->create();
});

test('product computes stock correctly from variants', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Shoe Test',
        'slug' => 'shoe-test',
        'description' => 'Description',
        'price' => 100000,
        'stock' => 50, // base stock
        'is_active' => true,
        'weight' => 500,
    ]);

    // No variants yet: stock = database column
    expect($product->stock)->toBe(50);

    // Create variants
    ProductVariant::create([
        'product_id' => $product->id,
        'size' => '40',
        'color' => 'Hitam',
        'stock' => 10,
    ]);

    ProductVariant::create([
        'product_id' => $product->id,
        'size' => '41',
        'color' => 'Putih',
        'stock' => 15,
    ]);

    // Refresh model
    $product->refresh();

    // Now stock is the sum of variants (10 + 15 = 25)
    expect($product->stock)->toBe(25);
});

test('product computes average rating and reviews count correctly', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Shoe Test',
        'slug' => 'shoe-test',
        'description' => 'Description',
        'price' => 100000,
        'is_active' => true,
        'weight' => 500,
    ]);

    // Create reviews
    Review::create([
        'user_id' => $this->user->id,
        'product_id' => $product->id,
        'rating' => 5,
        'comment' => 'Excellent shoe!',
    ]);

    Review::create([
        'user_id' => $this->user->id,
        'product_id' => $product->id,
        'rating' => 4,
        'comment' => 'Good fit.',
    ]);

    $product->refresh();

    expect($product->reviews_count)->toBe(2);
    expect($product->average_rating)->toBe(4.5);
});

test('logged in user can submit a review and select variants in detail modal', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Shoe Test',
        'slug' => 'shoe-test',
        'description' => 'Description',
        'price' => 100000,
        'is_active' => true,
        'weight' => 500,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'size' => '42',
        'color' => 'Biru',
        'stock' => 8,
    ]);

    $this->actingAs($this->user);

    Volt::test('pages.shop.index')
        ->call('openDetailModal', $product->id)
        ->assertSee('Shoe Test')
        // Test variant selectors
        ->call('selectColor', 'Biru')
        ->assertSet('selectedColor', 'Biru')
        ->assertSet('selectedSize', '42') // automatically selected
        // Test review form submission
        ->set('reviewRating', 5)
        ->set('reviewComment', 'Bagus sekali sepatunya!')
        ->call('submitReview')
        ->assertHasNoErrors();

    // Verify review saved
    expect(Review::where('product_id', $product->id)->count())->toBe(1);
    expect(Review::where('product_id', $product->id)->first()->comment)->toBe('Bagus sekali sepatunya!');
});

test('cart checks variant stock limit', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Shoe Test',
        'slug' => 'shoe-test',
        'description' => 'Description',
        'price' => 100000,
        'is_active' => true,
        'weight' => 500,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'size' => '42',
        'color' => 'Biru',
        'stock' => 2, // low stock
    ]);

    $this->actingAs($this->user);

    // Initial cart item addition
    Volt::test('pages.shop.index')
        ->call('openDetailModal', $product->id)
        ->call('selectColor', 'Biru')
        ->assertSet('selectedColor', 'Biru')
        ->assertSet('selectedSize', '42')
        ->call('addToCart', $product->id, $variant->id)
        ->assertHasNoErrors();

    expect(CartItem::count())->toBe(1);
    expect(CartItem::first()->quantity)->toBe(1);

    // Increase quantity in cart drawer
    $cartItem = CartItem::first();
    Volt::test('layout.cart-drawer')
        ->call('increment', $cartItem->id)
        ->assertHasNoErrors();

    expect(CartItem::first()->quantity)->toBe(2);

    // Try to exceed stock limit
    Volt::test('layout.cart-drawer')
        ->call('increment', $cartItem->id)
        ->assertDispatched('notify');

    expect(CartItem::first()->quantity)->toBe(2); // stock cap enforced
});
