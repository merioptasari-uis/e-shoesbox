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
    'current_image_path' => null,
    'additional_images' => [],
    'current_additional_images' => [],
]);

rules([
    'category_id' => ['required', 'exists:categories,id'],
    'name' => ['required', 'string', 'max:255'],
    'description' => ['required', 'string'],
    'price' => ['required', 'numeric', 'min:0'],
    'discount_price' => ['nullable', 'numeric', 'min:0'],
    'stock' => ['required', 'integer', 'min:0'],
    'weight' => ['required', 'integer', 'min:0'],
    'additional_images' => ['nullable', 'array'],
    'additional_images.*' => ['nullable', 'image', 'max:2048'],
]);

$openCreateModal = function () {
    $this->resetErrorBag();
    $this->reset(['editingProductId', 'category_id', 'name', 'description', 'price', 'discount_price', 'stock', 'weight', 'current_image_path', 'additional_images', 'current_additional_images']);
    // Set default category if exists
    $firstCategory = Category::first();
    if ($firstCategory) {
        $this->category_id = $firstCategory->id;
    }
    $this->isModalOpen = true;
};

$openEditModal = function ($id) {
    $this->resetErrorBag();
    $product = Product::with('images')->findOrFail($id);
    
    $this->editingProductId = $product->id;
    $this->category_id = $product->category_id;
    $this->name = $product->name;
    $this->description = $product->description;
    $this->price = $product->price;
    $this->discount_price = $product->discount_price;
    $this->stock = $product->stock;
    $this->weight = $product->weight;
    $this->current_image_path = $product->image_path;
    $this->additional_images = [];
    $this->current_additional_images = $product->images;
    
    $this->isModalOpen = true;
};

$saveProduct = function () {
    if ($this->discount_price !== '' && $this->discount_price !== null && (float) $this->discount_price >= (float) $this->price) {
        $this->addError('discount_price', 'Harga diskon harus lebih kecil dari harga asli.');
        return;
    }

    $validated = $this->validate();
    unset($validated['additional_images']);
    $validated['discount_price'] = $this->discount_price !== '' && $this->discount_price !== null ? $this->discount_price : null;
    $validated['slug'] = Str::slug($this->name);
    
    // Copy the additional images array so we can shift files
    $uploadedFiles = $this->additional_images;
    
    if ($this->editingProductId) {
        $product = Product::findOrFail($this->editingProductId);
        
        // If the product currently has no main image, and we have uploaded new images
        if (!$product->image_path && !empty($uploadedFiles)) {
            $firstImg = array_shift($uploadedFiles);
            $path = $firstImg->store('products', 'public');
            $validated['image_path'] = $path;
        }
        
        $product->update($validated);
        session()->flash('message', 'Produk berhasil diperbarui!');
    } else {
        // Creating a new product
        if (!empty($uploadedFiles)) {
            $firstImg = array_shift($uploadedFiles);
            $path = $firstImg->store('products', 'public');
            $validated['image_path'] = $path;
        }
        
        $product = Product::create($validated);
        session()->flash('message', 'Produk berhasil dibuat!');
    }
    
    // Save remaining additional images if uploaded
    if (!empty($uploadedFiles)) {
        foreach ($uploadedFiles as $img) {
            $path = $img->store('products', 'public');
            \App\Models\ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
            ]);
        }
    }
    
    $this->isModalOpen = false;
};

$deleteProduct = function ($id) {
    $product = Product::with('images')->findOrFail($id);
    if ($product->image_path) {
        Storage::disk('public')->delete($product->image_path);
    }
    foreach ($product->images as $img) {
        Storage::disk('public')->delete($img->image_path);
    }
    $product->delete();
    session()->flash('message', 'Produk berhasil dihapus!');
};

$deleteAdditionalImage = function ($imageId) {
    $img = \App\Models\ProductImage::findOrFail($imageId);
    Storage::disk('public')->delete($img->image_path);
    $img->delete();
    
    if ($this->editingProductId) {
        $this->current_additional_images = \App\Models\ProductImage::where('product_id', $this->editingProductId)->get();
    }
    
    session()->flash('message', 'Foto tambahan berhasil dihapus!');
};

$deleteMainImage = function () {
    if (!$this->editingProductId) {
        $this->current_image_path = null;
        return;
    }
    
    $product = Product::findOrFail($this->editingProductId);
    if ($product->image_path) {
        Storage::disk('public')->delete($product->image_path);
        $product->update(['image_path' => null]);
        $this->current_image_path = null;
    }
    
    // Promote first additional image if exists
    $firstAdditional = \App\Models\ProductImage::where('product_id', $product->id)->first();
    if ($firstAdditional) {
        $product->update(['image_path' => $firstAdditional->image_path]);
        $firstAdditional->delete();
        
        $this->current_image_path = $product->image_path;
        $this->current_additional_images = \App\Models\ProductImage::where('product_id', $product->id)->get();
    }
    
    session()->flash('message', 'Foto utama berhasil dihapus!');
};

$setMainImage = function ($imageId) {
    if (!$this->editingProductId) {
        return;
    }
    $product = Product::findOrFail($this->editingProductId);
    $additionalImg = \App\Models\ProductImage::findOrFail($imageId);
    
    $oldMainPath = $product->image_path;
    $newMainPath = $additionalImg->image_path;
    
    if ($oldMainPath) {
        // Swap paths
        $product->update(['image_path' => $newMainPath]);
        $additionalImg->update(['image_path' => $oldMainPath]);
    } else {
        // Product has no main image, set this additional image as main and delete the additional image record
        $product->update(['image_path' => $newMainPath]);
        $additionalImg->delete();
    }
    
    // Refresh current lists
    $this->current_image_path = $product->image_path;
    $this->current_additional_images = \App\Models\ProductImage::where('product_id', $product->id)->get();
    
    session()->flash('message', 'Foto utama berhasil diubah!');
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
            {{ __('Manajemen Produk') }}
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
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari produk..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <div>
                        <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition cursor-pointer">
                            ➕ Tambah Produk Baru
                        </button>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50/70 dark:bg-gray-900/30">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Produk</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Harga</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stok</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Berat</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
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
                                        {{ $product->stock }} unit
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                        {{ $product->weight }} gram
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
                                        <button wire:click="deleteProduct({{ $product->id }})" wire:confirm="Apakah Anda yakin ingin menghapus produk ini?" class="text-rose-600 dark:text-rose-400 hover:text-rose-950 dark:hover:text-rose-300 cursor-pointer">
                                            Hapus
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="text-lg">Produk tidak ditemukan.</div>
                                        <div class="text-xs text-gray-400 mt-1">Coba perluas kata pencarian Anda.</div>
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
                                {{ $editingProductId ? 'Edit Produk' : 'Tambah Produk Baru' }}
                            </h3>

                            <div class="space-y-4">
                                <!-- Product Name -->
                                <div>
                                    <x-input-label for="form_name" :value="__('Nama Produk')" />
                                    <x-text-input wire:model="name" id="form_name" class="block mt-1 w-full" type="text" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                                </div>

                                <!-- Category Selection -->
                                <div>
                                    <x-input-label for="form_category" :value="__('Kategori')" />
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
                                        <x-input-label for="form_price" :value="__('Harga (Rp)')" />
                                        <x-text-input wire:model="price" id="form_price" class="block mt-1 w-full" type="number" required />
                                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                                    </div>
                                    <!-- Discount Price -->
                                    <div>
                                        <x-input-label for="form_discount_price" :value="__('Harga Diskon (Rp) - Opsional')" />
                                        <x-text-input wire:model="discount_price" id="form_discount_price" class="block mt-1 w-full" type="number" />
                                        <x-input-error :messages="$errors->get('discount_price')" class="mt-1" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Stock -->
                                    <div>
                                        <x-input-label for="form_stock" :value="__('Stok')" />
                                        <x-text-input wire:model="stock" id="form_stock" class="block mt-1 w-full" type="number" required />
                                        <x-input-error :messages="$errors->get('stock')" class="mt-1" />
                                    </div>
                                    <!-- Weight -->
                                    <div>
                                        <x-input-label for="form_weight" :value="__('Berat (gram)')" />
                                        <x-text-input wire:model="weight" id="form_weight" class="block mt-1 w-full" type="number" required />
                                        <x-input-error :messages="$errors->get('weight')" class="mt-1" />
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <x-input-label for="form_description" :value="__('Deskripsi')" />
                                    <textarea wire:model="description" id="form_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-350 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                                </div>

                                <!-- Integrated Premium Product Images Section -->
                                <div class="border-t border-gray-100 dark:border-gray-700 pt-4 mt-4">
                                    <x-input-label :value="__('Foto-foto Produk (Bisa pilih lebih dari satu)')" />
                                    
                                    <!-- Existing saved images gallery -->
                                    @if ($current_image_path || (!empty($current_additional_images) && count($current_additional_images) > 0))
                                        <div class="mt-2">
                                            <span class="text-[10px] text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wider block mb-1">Galeri Foto Produk Saat Ini:</span>
                                            <div class="flex flex-wrap gap-3 mb-4">
                                                <!-- Main Image -->
                                                @if ($current_image_path)
                                                    <div class="relative w-20 h-20 rounded-xl overflow-hidden border-2 border-indigo-600 dark:border-indigo-50 group shadow-md">
                                                        <img src="{{ Storage::url($current_image_path) }}" class="w-full h-full object-cover" />
                                                        <span class="absolute bottom-0 inset-x-0 bg-indigo-600/90 text-[9px] text-white text-center py-0.5 font-bold uppercase tracking-wider">Utama</span>
                                                        <button type="button" wire:click="deleteMainImage" class="absolute top-1 right-1 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs shadow-md transition cursor-pointer" title="Hapus foto utama">
                                                            ✕
                                                        </button>
                                                    </div>
                                                @endif

                                                <!-- Additional Images -->
                                                @if (!empty($current_additional_images))
                                                    @foreach ($current_additional_images as $img)
                                                        <div class="relative w-20 h-20 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 group shadow-sm hover:border-indigo-400 transition">
                                                            <img src="{{ Storage::url($img->image_path) }}" class="w-full h-full object-cover" />
                                                            
                                                            <!-- Set as Main Button on Hover -->
                                                            <button type="button" wire:click="setMainImage({{ $img->id }})" class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-[10px] font-bold transition cursor-pointer">
                                                                Set Utama
                                                            </button>
                                                            
                                                            <!-- Delete Button -->
                                                            <button type="button" wire:click="deleteAdditionalImage({{ $img->id }})" class="absolute top-1 right-1 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs shadow-md transition cursor-pointer" title="Hapus foto">
                                                                ✕
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Display temporary previews of newly chosen additional images -->
                                    @if ($additional_images)
                                        <div class="mt-2">
                                            <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-wider block mb-1">Foto Baru (Belum Disimpan):</span>
                                            <div class="flex flex-wrap gap-2 mb-3">
                                                @foreach ($additional_images as $index => $img)
                                                    <div class="relative w-20 h-20 rounded-xl overflow-hidden border-2 border-indigo-200 dark:border-indigo-850 shadow-sm">
                                                        <img src="{{ $img->temporaryUrl() }}" class="w-full h-full object-cover" />
                                                        @if (!$current_image_path && $index === 0)
                                                            <span class="absolute bottom-0 inset-x-0 bg-emerald-600/90 text-[9px] text-white text-center py-0.5 font-bold uppercase tracking-wider">Calon Utama</span>
                                                        @else
                                                            <span class="absolute bottom-0 inset-x-0 bg-indigo-600/80 text-[9px] text-white text-center py-0.5 font-bold uppercase tracking-wider">Baru</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <input type="file" wire:model="additional_images" id="form_additional_images" multiple class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-900/30 file:text-indigo-700 dark:file:text-indigo-400 hover:file:bg-indigo-100" />
                                    <x-input-error :messages="$errors->get('additional_images.*')" class="mt-1" />
                                    <x-input-error :messages="$errors->get('additional_images')" class="mt-1" />

                                    <!-- Loading status -->
                                    <div wire:loading wire:target="additional_images" class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">Mengunggah gambar...</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900/30 px-6 py-4 sm:px-6 sm:flex sm:flex-row-reverse gap-3">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm cursor-pointer">
                                Simpan Produk
                            </button>
                            <button type="button" wire:click="$set('isModalOpen', false)" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-700 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm cursor-pointer">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
