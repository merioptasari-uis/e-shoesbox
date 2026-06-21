<?php

use App\Http\Controllers\Api\MidtransWebhookController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'pages.shop.index')->name('shop.index');
Volt::route('cart', 'pages.shop.cart')->middleware(['auth'])->name('cart');
Volt::route('order/{order}', 'pages.shop.order-details')->middleware(['auth'])->name('order.details');
Route::post('api/midtrans/notification', [MidtransWebhookController::class, 'handle'])->name('midtrans.webhook');

Route::view('dashboard', 'dashboard')
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
});

require __DIR__.'/auth.php';
