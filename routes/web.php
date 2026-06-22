<?php

use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\OrderPrintController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'pages.shop.index')->name('shop.index');
Volt::route('cart', 'pages.shop.cart')->middleware(['auth'])->name('cart');
Volt::route('order/{order}', 'pages.shop.order-details')->middleware(['auth'])->name('order.details');
Route::get('order/{order}/print', [OrderPrintController::class, 'printInvoice'])->middleware(['auth'])->name('order.print');
Route::post('api/midtrans/notification', [MidtransWebhookController::class, 'handle'])->name('midtrans.webhook');

Volt::route('dashboard', 'pages.dashboard')
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'admin'])->group(function () {
    Volt::route('admin/products', 'pages.admin.products')->name('admin.products');
    Volt::route('admin/orders', 'pages.admin.orders')->name('admin.orders');
    Volt::route('admin/vouchers', 'pages.admin.vouchers')->name('admin.vouchers');
    Volt::route('admin/campaigns', 'pages.admin.campaigns')->name('admin.campaigns');
    Route::get('admin/order/{order}/shipping-label', [OrderPrintController::class, 'printShippingLabel'])->name('admin.order.shipping-label');
});

require __DIR__.'/auth.php';
