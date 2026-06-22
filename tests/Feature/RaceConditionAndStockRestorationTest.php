<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Province;
use App\Models\User;
use App\Services\MidtransService;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->category = Category::create(['name' => 'Running', 'slug' => 'running']);
    $this->customer = User::factory()->create();
});

test('checkout fails and rolls back if stock becomes insufficient during transaction lock', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Race Shoe',
        'slug' => 'race-shoe',
        'description' => 'Test description',
        'price' => 100000,
        'stock' => 5,
        'is_active' => true,
        'weight' => 500,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'size' => '42',
        'color' => 'Red',
        'stock' => 1,
    ]);

    $province = Province::create(['id' => 1, 'name' => 'DKI Jakarta']);
    $city = City::create(['id' => 151, 'province_id' => 1, 'name' => 'Jakarta Barat', 'type' => 'Kota', 'postal_code' => '11510']);

    // Add to cart
    CartItem::create([
        'user_id' => $this->customer->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'size' => '42',
        'color' => 'Red',
    ]);

    $midtransMock = Mockery::mock(MidtransService::class);
    // Since placeOrder won't succeed, getSnapToken won't be called.
    $this->app->instance(MidtransService::class, $midtransMock);

    $this->actingAs($this->customer);

    // Eagerly test the cart component
    $component = Volt::test('pages.shop.cart')
        ->set('recipientName', 'John Doe')
        ->set('phoneNumber', '08123456789')
        ->set('addressLine', 'Test Address')
        ->set('provinceId', $province->id)
        ->set('cityId', $city->id)
        ->set('courier', 'jne')
        ->set('selectedService', 'REG')
        ->set('shippingCost', 10000);

    // Simulate concurrent checkout: decrement variant stock database column directly to 0
    $variant->update(['stock' => 0]);

    // Now call placeOrder
    $component->call('placeOrder')
        ->assertDispatched('notify', function ($eventName, $params) {
            return $params['type'] === 'error' && str_contains($params['message'], 'Stok tidak mencukupi');
        });

    // Verify order was NOT created (transaction rolled back)
    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);

    // Verify cart item still exists (not deleted)
    expect(CartItem::count())->toBe(1);
});

test('cancelling an order via midtrans webhook restores the specific variant stock correctly', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Race Shoe',
        'slug' => 'race-shoe',
        'description' => 'Test description',
        'price' => 100000,
        'stock' => 5,
        'is_active' => true,
        'weight' => 500,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'size' => '42',
        'color' => 'Red',
        'stock' => 5,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'INV/20260622/12345',
        'subtotal_amount' => 100000,
        'shipping_cost' => 10000,
        'discount_amount' => 0,
        'shipping_discount_amount' => 0,
        'total_amount' => 110000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'status' => 'pending',
        'shipping_recipient_name' => 'John Doe',
        'shipping_phone_number' => '08123456789',
        'shipping_address_line' => 'Test Address',
        'shipping_province' => 'DKI Jakarta',
        'shipping_city' => 'Jakarta Barat',
        'shipping_postal_code' => '11510',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'size' => '42',
        'color' => 'Red',
        'name' => $product->name,
        'price' => 100000,
        'quantity' => 2,
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'gross_amount' => 110000,
        'status' => 'pending',
    ]);

    // Send cancel webhook
    $response = $this->postJson(route('midtrans.webhook'), [
        'order_id' => 'INV-20260622-12345', // order number is normalized (slashes replaced with hyphens)
        'transaction_status' => 'cancel',
        'payment_type' => 'gopay',
        'transaction_id' => 'midtrans-tx-123',
    ]);

    $response->assertStatus(200);

    // Check variant stock is restored: 5 + 2 = 7
    $variant->refresh();
    expect($variant->stock)->toBe(7);

    // Verify order status updated to cancelled
    $order->refresh();
    expect($order->status)->toBe('cancelled');

    // Verify payment status updated to cancel
    $payment->refresh();
    expect($payment->status)->toBe('cancel');
});

test('admin cancelling an order restores the specific variant stock correctly', function () {
    $product = Product::create([
        'category_id' => $this->category->id,
        'name' => 'Race Shoe',
        'slug' => 'race-shoe',
        'description' => 'Test description',
        'price' => 100000,
        'stock' => 5,
        'is_active' => true,
        'weight' => 500,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'size' => '42',
        'color' => 'Red',
        'stock' => 5,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'INV/20260622/54321',
        'subtotal_amount' => 100000,
        'shipping_cost' => 10000,
        'discount_amount' => 0,
        'shipping_discount_amount' => 0,
        'total_amount' => 110000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
        'status' => 'pending',
        'shipping_recipient_name' => 'John Doe',
        'shipping_phone_number' => '08123456789',
        'shipping_address_line' => 'Test Address',
        'shipping_province' => 'DKI Jakarta',
        'shipping_city' => 'Jakarta Barat',
        'shipping_postal_code' => '11510',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_variant_id' => $variant->id,
        'size' => '42',
        'color' => 'Red',
        'name' => $product->name,
        'price' => 100000,
        'quantity' => 1,
    ]);

    $payment = Payment::create([
        'order_id' => $order->id,
        'gross_amount' => 110000,
        'status' => 'pending',
    ]);

    // Create an admin user to perform the action
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    // Call Volt component for admin order management
    Volt::test('pages.admin.orders')
        ->call('selectOrder', $order->id)
        ->set('orderStatus', 'cancelled')
        ->call('updateOrder')
        ->assertHasNoErrors();

    // Check variant stock is restored: 5 + 1 = 6
    $variant->refresh();
    expect($variant->stock)->toBe(6);

    // Verify order status updated to cancelled
    $order->refresh();
    expect($order->status)->toBe('cancelled');

    // Verify payment status updated to cancel
    $payment->refresh();
    expect($payment->status)->toBe('cancel');
});
