<?php

use App\Models\User;
use App\Models\Voucher;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $this->customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
});

test('guest cannot access admin vouchers page', function () {
    $this->get('/admin/vouchers')
        ->assertRedirect('/login');
});

test('non-admin user cannot access admin vouchers page', function () {
    $this->actingAs($this->customer)
        ->get('/admin/vouchers')
        ->assertStatus(403);
});

test('admin can access admin vouchers page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/vouchers')
        ->assertStatus(200);
});

test('admin can create a voucher', function () {
    $this->actingAs($this->admin);

    Volt::test('pages.admin.vouchers')
        ->set('code', 'NEWYEAR50')
        ->set('type', 'percentage')
        ->set('value', 50)
        ->set('max_discount', 50000)
        ->set('min_spend', 100000)
        ->set('limit_total', 100)
        ->set('limit_per_user', 2)
        ->call('saveVoucher')
        ->assertHasNoErrors();

    $voucher = Voucher::where('code', 'NEWYEAR50')->first();
    expect($voucher)->not->toBeNull();
    expect($voucher->type)->toEqual('percentage');
    expect($voucher->value)->toEqual(50);
    expect($voucher->max_discount)->toEqual(50000);
    expect($voucher->limit_per_user)->toEqual(2);
});

test('admin can edit a voucher', function () {
    $voucher = Voucher::create([
        'code' => 'PROMO10',
        'type' => 'percentage',
        'value' => 10,
    ]);

    $this->actingAs($this->admin);

    Volt::test('pages.admin.vouchers')
        ->set('editingVoucherId', $voucher->id)
        ->set('code', 'PROMO15')
        ->set('type', 'percentage')
        ->set('value', 15)
        ->call('saveVoucher')
        ->assertHasNoErrors();

    $voucher->refresh();
    expect($voucher->code)->toEqual('PROMO15');
    expect($voucher->value)->toEqual(15);
});

test('admin can toggle active status and delete a voucher', function () {
    $voucher = Voucher::create([
        'code' => 'PROMO10',
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);

    Volt::test('pages.admin.vouchers')
        ->call('toggleActive', $voucher->id);

    $voucher->refresh();
    expect($voucher->is_active)->toBeFalse();

    Volt::test('pages.admin.vouchers')
        ->call('deleteVoucher', $voucher->id);

    expect(Voucher::find($voucher->id))->toBeNull();
});
