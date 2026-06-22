<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->category = Category::create(['name' => 'Running', 'slug' => 'running']);
    $this->customer = User::factory()->create();
});

test('auto complete button updates status and completed_at for orders shipped over 14 days ago', function () {
    // Shipped 15 days ago (eligible)
    $orderA = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'INV/20260607/00001',
        'subtotal_amount' => 100000,
        'shipping_cost' => 10000,
        'discount_amount' => 0,
        'shipping_discount_amount' => 0,
        'total_amount' => 110000,
        'shipping_recipient_name' => 'John Doe',
        'shipping_phone_number' => '08123456789',
        'shipping_address_line' => 'Test Address',
        'shipping_postal_code' => '11510',
        'status' => 'shipping',
        'shipped_at' => Carbon::now()->subDays(15),
    ]);

    // Shipped 5 days ago (not eligible)
    $orderB = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'INV/20260617/00002',
        'subtotal_amount' => 100000,
        'shipping_cost' => 10000,
        'discount_amount' => 0,
        'shipping_discount_amount' => 0,
        'total_amount' => 110000,
        'shipping_recipient_name' => 'John Doe',
        'shipping_phone_number' => '08123456789',
        'shipping_address_line' => 'Test Address',
        'shipping_postal_code' => '11510',
        'status' => 'shipping',
        'shipped_at' => Carbon::now()->subDays(5),
    ]);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    Volt::test('pages.admin.orders')
        ->assertSet('eligibleForAutoCompletionCount', 1)
        ->call('completeEligibleOrders')
        ->assertHasNoErrors()
        ->assertDispatched('notify', function ($eventName, $params) {
            return $params['type'] === 'success' && str_contains($params['message'], 'Berhasil menyelesaikan 1 pesanan');
        });

    $orderA->refresh();
    $orderB->refresh();

    expect($orderA->status)->toBe('completed');
    expect($orderA->completed_at)->not->toBeNull();
    expect($orderA->completed_at->isToday())->toBeTrue();

    expect($orderB->status)->toBe('shipping');
    expect($orderB->completed_at)->toBeNull();
});

test('legacy orders with Null shipped_at but updated_at over 14 days ago are also completed', function () {
    // Shipped 15 days ago (Null shipped_at but updated_at 15 days ago)
    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'INV/20260607/00003',
        'subtotal_amount' => 100000,
        'shipping_cost' => 10000,
        'discount_amount' => 0,
        'shipping_discount_amount' => 0,
        'total_amount' => 110000,
        'shipping_recipient_name' => 'John Doe',
        'shipping_phone_number' => '08123456789',
        'shipping_address_line' => 'Test Address',
        'shipping_postal_code' => '11510',
        'status' => 'shipping',
        'shipped_at' => null,
    ]);

    // Force updated_at to 15 days ago in the database
    $order->updated_at = Carbon::now()->subDays(15);
    $order->saveQuietly(); // avoid updating updated_at timestamp

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    Volt::test('pages.admin.orders')
        ->assertSet('eligibleForAutoCompletionCount', 1)
        ->call('completeEligibleOrders')
        ->assertHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe('completed');
    expect($order->completed_at)->not->toBeNull();
});
