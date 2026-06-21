<?php

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherService;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->voucherService = new VoucherService;
    $this->customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
});

test('voucher with inactive status is invalid', function () {
    $voucher = Voucher::create([
        'code' => 'INACTIVE',
        'type' => 'fixed',
        'value' => 10000,
        'is_active' => false,
    ]);

    $res = $this->voucherService->validate('INACTIVE', 50000, $this->customer->id);
    expect($res['isValid'])->toBeFalse();
    expect($res['message'])->toContain('no longer active');
});

test('voucher expired is invalid', function () {
    $voucher = Voucher::create([
        'code' => 'EXPIRED',
        'type' => 'fixed',
        'value' => 10000,
        'expires_at' => now()->subDay(),
    ]);

    $res = $this->voucherService->validate('EXPIRED', 50000, $this->customer->id);
    expect($res['isValid'])->toBeFalse();
    expect($res['message'])->toContain('expired');
});

test('voucher min spend is respected', function () {
    $voucher = Voucher::create([
        'code' => 'MINSPEND',
        'type' => 'fixed',
        'value' => 10000,
        'min_spend' => 100000,
    ]);

    $res = $this->voucherService->validate('MINSPEND', 50000, $this->customer->id);
    expect($res['isValid'])->toBeFalse();
    expect($res['message'])->toContain('Minimum spend');

    $res2 = $this->voucherService->validate('MINSPEND', 120000, $this->customer->id);
    expect($res2['isValid'])->toBeTrue();
});

test('voucher total limit is respected', function () {
    $voucher = Voucher::create([
        'code' => 'TOTALLIMIT',
        'type' => 'fixed',
        'value' => 10000,
        'limit_total' => 2,
        'used_count' => 2,
    ]);

    $res = $this->voucherService->validate('TOTALLIMIT', 50000, $this->customer->id);
    expect($res['isValid'])->toBeFalse();
    expect($res['message'])->toContain('usage limit has been reached');
});

test('voucher per user limit is respected', function () {
    $voucher = Voucher::create([
        'code' => 'USERLIMIT',
        'type' => 'fixed',
        'value' => 10000,
        'limit_per_user' => 1,
    ]);

    // Valid initially
    $res = $this->voucherService->validate('USERLIMIT', 50000, $this->customer->id);
    expect($res['isValid'])->toBeTrue();

    // Create an order by this user with this voucher
    $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Running Shoe',
        'slug' => 'running-shoe',
        'description' => 'Test shoe description',
        'price' => 120000,
        'stock' => 10,
        'weight' => 500,
    ]);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'INV/TEST',
        'subtotal_amount' => 120000,
        'shipping_cost' => 15000,
        'discount_amount' => 10000,
        'total_amount' => 125000,
        'shipping_recipient_name' => 'John',
        'shipping_phone_number' => '0812',
        'shipping_address_line' => 'Address',
        'shipping_postal_code' => '12345',
    ]);
    $order->vouchers()->attach($voucher->id, ['applied_discount' => 10000]);

    // Validation should fail now
    $res2 = $this->voucherService->validate('USERLIMIT', 50000, $this->customer->id);
    expect($res2['isValid'])->toBeFalse();
    expect($res2['message'])->toContain('reached the usage limit');

    // If order is cancelled, it should be valid again
    $order->update(['status' => 'cancelled']);
    $res3 = $this->voucherService->validate('USERLIMIT', 50000, $this->customer->id);
    expect($res3['isValid'])->toBeTrue();
});

test('calculates correct product discount values', function () {
    $voucherFixed = Voucher::create([
        'code' => 'FIXED',
        'type' => 'fixed',
        'value' => 15000,
    ]);

    $voucherPct = Voucher::create([
        'code' => 'PERCENT',
        'type' => 'percentage',
        'value' => 10, // 10%
        'max_discount' => 12000,
    ]);

    // Fixed cut
    $discountFixed = $this->voucherService->calculateProductDiscount($voucherFixed, 50000);
    expect($discountFixed)->toEqual(15000);

    // Fixed cut greater than subtotal
    $discountFixed2 = $this->voucherService->calculateProductDiscount($voucherFixed, 10000);
    expect($discountFixed2)->toEqual(10000);

    // Percentage cut below max
    $discountPct = $this->voucherService->calculateProductDiscount($voucherPct, 50000);
    expect($discountPct)->toEqual(5000);

    // Percentage cut hits max cap
    $discountPct2 = $this->voucherService->calculateProductDiscount($voucherPct, 150000);
    expect($discountPct2)->toEqual(12000);
});

test('calculates correct shipping discount values', function () {
    $voucherShip = Voucher::create([
        'code' => 'FREESHIP',
        'type' => 'shipping',
        'value' => 20000,
    ]);

    $discountShip = $this->voucherService->calculateShippingDiscount($voucherShip, 15000);
    expect($discountShip)->toEqual(15000);

    $discountShip2 = $this->voucherService->calculateShippingDiscount($voucherShip, 25000);
    expect($discountShip2)->toEqual(20000);
});

test('can apply and remove vouchers in livewire cart component', function () {
    $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Running Shoe',
        'slug' => 'running-shoe',
        'description' => 'Test shoe description',
        'price' => 100000,
        'stock' => 10,
        'weight' => 500,
    ]);

    // Add item to cart
    CartItem::create([
        'user_id' => $this->customer->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $voucherDisc = Voucher::create([
        'code' => 'DISC10',
        'type' => 'fixed',
        'value' => 10000,
        'min_spend' => 50000,
    ]);

    $voucherShip = Voucher::create([
        'code' => 'FREEONGKIR',
        'type' => 'shipping',
        'value' => 20000,
    ]);

    $this->actingAs($this->customer);

    Volt::test('pages.shop.cart')
        ->set('voucherCode', 'DISC10')
        ->call('applyVoucher')
        ->assertSet('appliedProductVoucher.id', $voucherDisc->id)
        ->assertSet('productDiscount', 10000)
        ->set('voucherCode', 'FREEONGKIR')
        ->call('applyVoucher')
        ->assertSet('appliedShippingVoucher.id', $voucherShip->id)
        ->set('shippingCost', 15000)
        ->assertSet('shippingDiscount', 15000)
        // Check dynamic total subtotal (100000 - 10000) + (15000 - 15000) = 90000
        ->assertSet('total', 90000)
        // Remove product voucher
        ->call('removeVoucher', 'product')
        ->assertSet('appliedProductVoucher', null)
        ->assertSet('total', 100000); // 100000 + 0
});
