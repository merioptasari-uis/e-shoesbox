<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

test('admin can upload multiple images and delete an additional image', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    $category = Category::create([
        'name' => 'Running Test',
        'slug' => 'running-test',
        'description' => 'Test desc',
    ]);

    $this->actingAs($admin);

    $file1 = UploadedFile::fake()->image('photo1.jpg');
    $file2 = UploadedFile::fake()->image('photo2.jpg');
    $file3 = UploadedFile::fake()->image('photo3.jpg');

    $component = Volt::test('pages.admin.products')
        ->set('category_id', $category->id)
        ->set('name', 'Multi Image Shoe')
        ->set('description', 'Cool description')
        ->set('price', 150000)
        ->set('stock', 5)
        ->set('weight', 250)
        ->set('additional_images', [$file1, $file2, $file3])
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::where('name', 'Multi Image Shoe')->first();
    expect($product)->not->toBeNull();
    expect($product->image_path)->not->toBeNull();
    expect($product->images)->toHaveCount(2);

    $firstImage = $product->images->first();

    // Test setting an additional image as main image (swap)
    $oldMainPath = $product->image_path;
    $newMainPath = $firstImage->image_path;

    Volt::test('pages.admin.products')
        ->call('openEditModal', $product->id)
        ->call('setMainImage', $firstImage->id)
        ->assertHasNoErrors();

    $product = $product->fresh();
    expect($product->image_path)->toBe($newMainPath);
    expect($product->images->where('image_path', $oldMainPath))->toHaveCount(1);

    // Test deleting the main image, which should promote the first additional image to main
    $remainingAdditional = $product->images->first();

    Volt::test('pages.admin.products')
        ->call('openEditModal', $product->id)
        ->call('deleteMainImage')
        ->assertHasNoErrors();

    $product = $product->fresh();
    expect($product->image_path)->toBe($remainingAdditional->image_path);
    expect($product->images)->toHaveCount(1); // One remains after promoting the other

    // Now test deleting one additional image
    // Let's recreate product with images to test deleting additional image
    $file4 = UploadedFile::fake()->image('photo4.jpg');
    $file5 = UploadedFile::fake()->image('photo5.jpg');
    Volt::test('pages.admin.products')
        ->call('openEditModal', $product->id)
        ->set('additional_images', [$file4, $file5])
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = $product->fresh();
    expect($product->images)->toHaveCount(3); // 1 remaining + 2 new
    $firstAdditional = $product->images->first();

    Volt::test('pages.admin.products')
        ->set('editingProductId', $product->id)
        ->call('deleteAdditionalImage', $firstAdditional->id)
        ->assertHasNoErrors();

    expect($product->fresh()->images)->toHaveCount(2);
});
