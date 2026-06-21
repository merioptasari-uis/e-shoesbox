<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Livewire\Volt\Volt;

test('guest cannot access admin products page', function () {
    $this->get('/admin/products')
        ->assertRedirect('/login');
});

test('guest cannot access admin orders page', function () {
    $this->get('/admin/orders')
        ->assertRedirect('/login');
});

test('non-admin user cannot access admin products page', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get('/admin/products')
        ->assertStatus(403);
});

test('non-admin user cannot access admin orders page', function () {
    $user = User::factory()->create(['role' => 'customer']);

    $this->actingAs($user)
        ->get('/admin/orders')
        ->assertStatus(403);
});

test('admin can access admin products page and see products', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $category = Category::create([
        'name' => 'Running Test',
        'slug' => 'running-test',
        'description' => 'Test desc',
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Running Shoe Test',
        'slug' => 'running-shoe-test',
        'description' => 'Test product desc',
        'price' => 100000,
        'stock' => 10,
        'weight' => 300,
    ]);

    $this->actingAs($admin)
        ->get('/admin/products')
        ->assertOk()
        ->assertSee('Running Shoe Test');
});

test('admin can access admin orders page', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get('/admin/orders')
        ->assertOk();
});

test('admin can create product via Volt component', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $category = Category::create([
        'name' => 'Running Test',
        'slug' => 'running-test',
        'description' => 'Test desc',
    ]);

    $this->actingAs($admin);

    Volt::test('pages.admin.products')
        ->set('category_id', $category->id)
        ->set('name', 'New Shoe')
        ->set('description', 'Cool description')
        ->set('price', 150000)
        ->set('stock', 5)
        ->set('weight', 250)
        ->call('saveProduct')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('products', [
        'name' => 'New Shoe',
        'price' => 150000,
    ]);
});

test('admin can edit a product', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $category = Category::create([
        'name' => 'Running Test',
        'slug' => 'running-test',
        'description' => 'Test desc',
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Old Name',
        'slug' => 'old-name',
        'description' => 'Old description',
        'price' => 100000,
        'stock' => 10,
        'weight' => 300,
    ]);

    $this->actingAs($admin);

    Volt::test('pages.admin.products')
        ->call('openEditModal', $product->id)
        ->assertSet('name', 'Old Name')
        ->set('name', 'Updated Name')
        ->call('saveProduct')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Name',
    ]);
});

test('admin can delete a product', function () {
    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $category = Category::create([
        'name' => 'Running Test',
        'slug' => 'running-test',
        'description' => 'Test desc',
    ]);
    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'To Be Deleted',
        'slug' => 'to-be-deleted',
        'description' => 'Deleted description',
        'price' => 100000,
        'stock' => 10,
        'weight' => 300,
    ]);

    $this->actingAs($admin);

    Volt::test('pages.admin.products')
        ->call('deleteProduct', $product->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('products', [
        'id' => $product->id,
    ]);
});
