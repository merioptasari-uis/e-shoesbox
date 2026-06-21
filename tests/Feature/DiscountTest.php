<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    $this->category = Category::create(['name' => 'Running', 'slug' => 'running']);
});

test('product returns correct selling price and discount state', function () {
    $productWithoutDiscount = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Shoe Regular',
        'slug' => 'shoe-regular',
        'description' => 'Description regular',
        'price' => 100000,
    ]);

    $productWithDiscount = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Shoe Promo',
        'slug' => 'shoe-promo',
        'description' => 'Description promo',
        'price' => 100000,
        'discount_price' => 85000,
    ]);

    expect($productWithoutDiscount->selling_price)->toEqual(100000);
    expect($productWithoutDiscount->has_discount)->toBeFalse();
    expect($productWithoutDiscount->discount_percentage)->toEqual(0);

    expect($productWithDiscount->selling_price)->toEqual(85000);
    expect($productWithDiscount->has_discount)->toBeTrue();
    expect($productWithDiscount->discount_percentage)->toEqual(15);
});

test('cart subtotal uses selling price for calculations', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Discounted Shoe',
        'slug' => 'discounted-shoe',
        'description' => 'Test description',
        'price' => 100000,
        'discount_price' => 80000,
    ]);

    CartItem::create([
        'user_id' => $this->customer->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $this->actingAs($this->customer);

    Volt::test('pages.shop.cart')
        ->assertSet('subtotal', 160000); // 80000 * 2
});

test('placeOrder uses discounted selling price for order items', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Discounted Shoe',
        'slug' => 'discounted-shoe',
        'description' => 'Test description',
        'price' => 100000,
        'discount_price' => 75000,
        'stock' => 5,
    ]);

    $province = Province::create(['id' => 1, 'name' => 'DKI Jakarta']);
    $city = City::create(['id' => 151, 'province_id' => 1, 'name' => 'Jakarta Barat', 'type' => 'Kota', 'postal_code' => '11510']);

    CartItem::create([
        'user_id' => $this->customer->id,
        'product_id' => $product->id,
        'quantity' => 2,
    ]);

    $this->actingAs($this->customer);

    Volt::test('pages.shop.cart')
        ->set('recipientName', 'John Doe')
        ->set('phoneNumber', '08123456789')
        ->set('addressLine', 'Test Address')
        ->set('provinceId', $province->id)
        ->set('cityId', $city->id)
        ->set('courier', 'jne')
        ->set('selectedService', 'REG')
        ->set('shippingCost', 10000)
        ->call('placeOrder')
        ->assertHasNoErrors();

    // Verify order was created with discount price
    $order = Order::latest()->first();
    expect($order->subtotal_amount)->toEqual(150000); // 75000 * 2
    expect($order->total_amount)->toEqual(160000); // 150000 + 10000

    $orderItem = $order->items->first();
    expect($orderItem->price)->toEqual(75000);
});

test('admin can manage discount_price on products panel', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $this->actingAs($admin);

    // Test creating a product with discount price
    Volt::test('pages.admin.products')
        ->set('category_id', $this->category->id)
        ->set('name', 'New Shoe')
        ->set('description', 'Cool description')
        ->set('price', 200000)
        ->set('discount_price', 180000)
        ->set('stock', 10)
        ->set('weight', 400)
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::where('slug', 'new-shoe')->first();
    expect($product->discount_price)->toEqual(180000);

    // Test validation: discount price must be less than price
    Volt::test('pages.admin.products')
        ->set('editingProductId', $product->id)
        ->set('price', 200000)
        ->set('discount_price', 210000)
        ->call('saveProduct')
        ->assertHasErrors(['discount_price']);
});
