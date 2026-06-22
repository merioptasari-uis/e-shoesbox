<?php

use App\Models\Campaign;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $this->customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
});

test('guest cannot access admin campaigns page', function () {
    $this->get('/admin/campaigns')
        ->assertRedirect('/login');
});

test('non-admin user cannot access admin campaigns page', function () {
    $this->actingAs($this->customer)
        ->get('/admin/campaigns')
        ->assertStatus(403);
});

test('admin can access admin campaigns page', function () {
    $this->actingAs($this->admin)
        ->get('/admin/campaigns')
        ->assertStatus(200);
});

test('admin can create a campaign', function () {
    $this->actingAs($this->admin);

    Volt::test('pages.admin.campaigns')
        ->set('title', 'Mega Promo Idul Fitri')
        ->set('subtitle', 'Diskon Sepatu Istimewa')
        ->set('description', 'Campaign detail description')
        ->set('badge_text', 'Hari Raya')
        ->set('promo_tag', 'Idul Fitri')
        ->set('button_text', 'Belanja')
        ->set('button_link', '#catalog')
        ->set('bg_gradient', 'rose')
        ->set('is_active', true)
        ->call('saveCampaign')
        ->assertHasNoErrors();

    $campaign = Campaign::where('title', 'Mega Promo Idul Fitri')->first();
    expect($campaign)->not->toBeNull();
    expect($campaign->subtitle)->toEqual('Diskon Sepatu Istimewa');
    expect($campaign->promo_tag)->toEqual('Idul Fitri');
    expect($campaign->bg_gradient)->toEqual('rose');
});

test('admin can edit a campaign', function () {
    $campaign = Campaign::create([
        'title' => 'Initial Title',
        'button_text' => 'Lihat',
        'button_link' => '#',
        'bg_gradient' => 'indigo',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);

    Volt::test('pages.admin.campaigns')
        ->set('editingCampaignId', $campaign->id)
        ->set('title', 'Updated Title')
        ->set('button_text', 'Lihat')
        ->set('button_link', '#')
        ->set('bg_gradient', 'emerald')
        ->call('saveCampaign')
        ->assertHasNoErrors();

    $campaign->refresh();
    expect($campaign->title)->toEqual('Updated Title');
    expect($campaign->bg_gradient)->toEqual('emerald');
});

test('admin can toggle active status and delete a campaign', function () {
    $campaign = Campaign::create([
        'title' => 'Toggle Campaign',
        'button_text' => 'Lihat',
        'button_link' => '#',
        'bg_gradient' => 'indigo',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin);

    Volt::test('pages.admin.campaigns')
        ->call('toggleActive', $campaign->id);

    $campaign->refresh();
    expect($campaign->is_active)->toBeFalse();

    Volt::test('pages.admin.campaigns')
        ->call('deleteCampaign', $campaign->id);

    expect(Campaign::find($campaign->id))->toBeNull();
});

test('storefront displays active campaigns and hides slideshow when none active', function () {
    // 1. Storefront with no campaigns - should hide slideshow and show static promo cards
    $this->get('/')
        ->assertStatus(200)
        ->assertDontSee('Idul Fitri Mega Promo! Diskon Hingga 70%')
        ->assertDontSee('Mid-Year Sneaker Festival')
        ->assertDontSee('Ekstra Cashback 10% Hingga Rp 50.000')
        ->assertSee('Promo Khusus Pengguna Baru')
        ->assertSee('Promo Bebas Ongkir Toko');

    // 2. Create active campaign
    $campaign = Campaign::create([
        'title' => 'Custom Storefront Promo 999',
        'subtitle' => 'Special discount only today',
        'button_text' => 'Beli',
        'button_link' => '#catalog',
        'bg_gradient' => 'amber',
        'is_active' => true,
    ]);

    // Now it should see the active campaign title and NOT the fallback title
    $this->get('/')
        ->assertStatus(200)
        ->assertSee('Custom Storefront Promo 999')
        ->assertSee('Special discount only today')
        ->assertDontSee('Idul Fitri Mega Promo! Diskon Hingga 70%');

    // 3. Set campaign to inactive
    $campaign->update(['is_active' => false]);

    $this->get('/')
        ->assertStatus(200)
        ->assertDontSee('Custom Storefront Promo 999')
        ->assertDontSee('Idul Fitri Mega Promo! Diskon Hingga 70%');

    // 4. Active but expired campaign
    Campaign::create([
        'title' => 'Expired Promo 888',
        'button_text' => 'Beli',
        'button_link' => '#catalog',
        'bg_gradient' => 'amber',
        'start_date' => Carbon::now()->subDays(5),
        'end_date' => Carbon::now()->subDays(1),
        'is_active' => true,
    ]);

    $this->get('/')
        ->assertStatus(200)
        ->assertDontSee('Expired Promo 888')
        ->assertDontSee('Idul Fitri Mega Promo! Diskon Hingga 70%');

    // 5. Active and not-yet-started campaign
    Campaign::create([
        'title' => 'Future Promo 777',
        'button_text' => 'Beli',
        'button_link' => '#catalog',
        'bg_gradient' => 'amber',
        'start_date' => Carbon::now()->addDays(1),
        'end_date' => Carbon::now()->addDays(5),
        'is_active' => true,
    ]);

    $this->get('/')
        ->assertStatus(200)
        ->assertDontSee('Future Promo 777')
        ->assertDontSee('Idul Fitri Mega Promo! Diskon Hingga 70%');
});
