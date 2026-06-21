<?php

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\rules;
use function Livewire\Volt\usesFileUploads;

usesFileUploads();
layout('layouts.app');

state([
    'search' => '',
    'isModalOpen' => false,
    'editingProductId' => null,
    
    // Form fields
    'category_id' => '',
    'name' => '',
    'description' => '',
    'price' => '',
    'discount_price' => '',
    'stock' => '',
    'weight' => '',
    'image' => null,
    'current_image_path' => null,
]);

rules([
    'category_id' => ['required', 'exists:categories,id'],
    'name' => ['required', 'string', 'max:255'],
    'description' => ['required', 'string'],
    'price' => ['required', 'numeric', 'min:0'],
    'discount_price' => ['nullable', 'numeric', 'min:0'],
    'stock' => ['required', 'integer', 'min:0'],
    'weight' => ['required', 'integer', 'min:0'],
    'image' => ['nullable', 'image', 'max:2048'], // Max 2MB
]);

$openCreateModal = function () {
    $this->resetErrorBag();
    $this->reset(['editingProductId', 'category_id', 'name', 'description', 'price', 'discount_price', 'stock', 'weight', 'image', 'current_image_path']);
    // Set default category if exists
    $firstCategory = Category::first();
    if ($firstCategory) {
        $this->category_id = $firstCategory->id;
    }
    $this->isModalOpen = true;
};

$openEditModal = function ($id) {
    $this->resetErrorBag();
    $product = Product::findOrFail($id);
    
    $this->editingProductId = $product->id;
    $this->category_id = $product->category_id;
    $this->name = $product->name;
    $this->description = $product->description;
    $this->price = $product->price;
    $this->discount_price = $product->discount_price;
    $this->stock = $product->stock;
    $this->weight = $product->weight;
    $this->current_image_path = $product->image_path;
    $this->image = null;
    
    $this->isModalOpen = true;
};

$saveProduct = function () {
    if ($this->discount_price !== '' && $this->discount_price !== null && (float) $this->discount_price >= (float) $this->price) {
        $this->addError('discount_price', 'Discount price must be less than original price.');
        return;
    }

    $validated = $this->validate();
    $validated['discount_price'] = $this->discount_price !== '' && $this->discount_price !== null ? $this->discount_price : null;
    
    // Handle image upload if provided
    if ($this->image) {
        $path = $this->image->store('products', 'public');
        $validated['image_path'] = $path;
    }
    
    $validated['slug'] = Str::slug($this->name);
    
    if ($this->editingProductId) {
        $product = Product::findOrFail($this->editingProductId);
        
        // Remove old image if a new one is uploaded
        if ($this->image && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        
        $product->update($validated);
        session()->flash('message', 'Product updated successfully!');
    } else {
        Product::create($validated);
        session()->flash('message', 'Product created successfully!');
    }
    
    $this->isModalOpen = false;
};

$deleteProduct = function ($id) {
    $product = Product::findOrFail($id);
    if ($product->image_path) {
        Storage::disk('public')->delete($product->image_path);
    }
    $product->delete();
    session()->flash('message', 'Product deleted successfully!');
};

$toggleActive = function ($id) {
    $product = Product::findOrFail($id);
    $product->update(['is_active' => !$product->is_active]);
};

// Computed property to retrieve filtered products list
$getProducts = function () {
    return Product::with('category')
        ->where('name', 'like', '%' . $this->search . '%')
        ->latest()
        ->get();
};

$getCategories = function () {
    return Category::orderBy('name')->get();
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Products Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Toast Alert Message -->
            @if (session()->has('message'))
                <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-300 border border-green-200 dark:border-green-800 flex items-center justify-between">
                    <span>{{ session('message') }}</span>
                    <button class="text-green-600 dark:text-green-400 hover:text-green-800" onclick="this.parentElement.remove()">✕</button>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                <!-- Toolbar controls -->
                <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gray-50/50 dark:bg-gray-800/50">
                    <div class="relative flex-1 max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            🔍
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search products..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition cursor-pointer">
                            ➕ Add New Product
                        </button>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Weight</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                            @forelse ($this->getProducts() as $product)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-4">
                                            <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-900 overflow-hidden flex items-center justify-center border border-gray-200 dark:border-gray-700">
                                                @if ($product->image_path)
                                                    <img src="{{ Storage::url($product->image_path) }}" alt="{{ $product->name }}" class="w-full h-full object-cover" />
                                                @else
                                                    <span class="text-gray-400 text-lg">👟</span>
                                                @endif
                                            </div>
                                            <div>
                                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $product->name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $product->slug }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-300">
                                            {{ $product->category->name }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        @if($product->discount_price)
                                            <div class="flex flex-col">
                                                <span class="text-xs text-rose-500 line-through">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                                                <span>Rp {{ number_format($product->discount_price, 0, ',', '.') }}</span>
                                            </div>
                                        @else
                                            Rp {{ number_format($product->price, 0, ',', '.') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ $product->stock }} units
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ $product->weight }} g
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button wire:click="toggleActive({{ $product->id }})" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $product->is_active ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $product->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold gap-2">
                                        <button wire:click="openEditModal({{ $product->id }})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-950 dark:hover:text-indigo-300 cursor-pointer mr-3">
                                            Edit
                                        </button>
                                        <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Are you sure you want to delete this product?" class="text-rose-600 dark:text-rose-400 hover:text-rose-950 dark:hover:text-rose-300 cursor-pointer">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="text-lg">No products found.</div>
                                        <div class="text-xs text-gray-400 mt-1">Try expanding your search query.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    @if ($isModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="$set('isModalOpen', false)"></div>

                <!-- Modal Panel -->
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-200 dark:border-gray-700">
                    <form wire:submit="saveProduct">
                        <div class="bg-white dark:bg-gray-800 px-6 pt-6 pb-4 sm:p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4" id="modal-title">
                                {{ $editingProductId ? 'Edit Product' : 'Add New Product' }}
                            </h3>

                            <div class="space-y-4">
                                <!-- Product Name -->
                                <div>
                                    <x-input-label for="form_name" :value="__('Product Name')" />
                                    <x-text-input wire:model="name" id="form_name" class="block mt-1 w-full" type="text" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                                </div>

                                <!-- Category Selection -->
                                <div>
                                    <x-input-label for="form_category" :value="__('Category')" />
                                    <select wire:model="category_id" id="form_category" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        @foreach ($this->getCategories() as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Price -->
                                    <div>
                                        <x-input-label for="form_price" :value="__('Price (Rp)')" />
                                        <x-text-input wire:model="price" id="form_price" class="block mt-1 w-full" type="number" required />
                                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                                    </div>
                                    <!-- Discount Price -->
                                    <div>
                                        <x-input-label for="form_discount_price" :value="__('Discount Price (Rp) - Optional')" />
                                        <x-text-input wire:model="discount_price" id="form_discount_price" class="block mt-1 w-full" type="number" />
                                        <x-input-error :messages="$errors->get('discount_price')" class="mt-1" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Stock -->
                                    <div>
                                        <x-input-label for="form_stock" :value="__('Stock')" />
                                        <x-text-input wire:model="stock" id="form_stock" class="block mt-1 w-full" type="number" required />
                                        <x-input-error :messages="$errors->get('stock')" class="mt-1" />
                                    </div>
                                    <!-- Weight -->
                                    <div>
                                        <x-input-label for="form_weight" :value="__('Weight (grams)')" />
                                        <x-text-input wire:model="weight" id="form_weight" class="block mt-1 w-full" type="number" required />
                                        <x-input-error :messages="$errors->get('weight')" class="mt-1" />
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <x-input-label for="form_description" :value="__('Description')" />
                                    <textarea wire:model="description" id="form_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-350 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                                </div>

                                <!-- Image Upload -->
                                <div>
                                    <x-input-label :value="__('Product Image')" />
                                    @if ($current_image_path)
                                        <div class="mt-2 mb-2 flex items-center gap-4">
                                            <img src="{{ Storage::url($current_image_path) }}" class="w-16 h-16 object-cover rounded-lg border" />
                                            <span class="text-xs text-gray-500">Current image</span>
                                        </div>
                                    @endif
                                    
                                    <input type="file" wire:model="image" id="form_image" class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/30 file:text-indigo-700 dark:file:text-indigo-400 hover:file:bg-indigo-100" />
                                    <x-input-error :messages="$errors->get('image')" class="mt-1" />

                                    <!-- Loading status -->
                                    <div wire:loading wire:target="image" class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Uploading image...</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 px-6 py-4 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm cursor-pointer">
                                Save Product
                            </button>
                            <button type="button" wire:click="$set('isModalOpen', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-700 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm cursor-pointer">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
