<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\Category;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public string $search = '';
    public ?string $category = null;
    public string $sort = 'latest';

    public ?int $selectedProductId = null;
    public int $selectedImageIndex = 0;
    public bool $isDetailModalOpen = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => null],
        'sort' => ['except' => 'latest'],
    ];

    public function openDetailModal(int $productId): void
    {
        $this->selectedProductId = $productId;
        $this->selectedImageIndex = 0;
        $this->isDetailModalOpen = true;
    }

    public function closeDetailModal(): void
    {
        $this->isDetailModalOpen = false;
        $this->selectedProductId = null;
    }

    public function selectImage(int $index): void
    {
        $this->selectedImageIndex = $index;
    }

    public function getSelectedProductProperty(): ?Product
    {
        if (!$this->selectedProductId) {
            return null;
        }
        return Product::with(['category', 'images'])->find($this->selectedProductId);
    }

    public function getProductGalleryProperty(): array
    {
        $product = $this->selectedProduct;
        if (!$product) {
            return [];
        }

        $gallery = [];
        if ($product->image_path) {
            $gallery[] = $product->image_path;
        }

        foreach ($product->images as $img) {
            $gallery[] = $img->image_path;
        }

        return $gallery;
    }

    public function with(): array
    {
        $query = Product::query()->where('is_active', true);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->category) {
            $query->whereHas('category', function ($q) {
                $q->where('slug', $this->category);
            });
        }

        if ($this->sort === 'price_asc') {
            $query->orderByRaw('COALESCE(discount_price, price) asc');
        } elseif ($this->sort === 'price_desc') {
            $query->orderByRaw('COALESCE(discount_price, price) desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return [
            'products' => $query->get(),
            'categories' => Category::all(),
        ];
    }

    public function selectCategory(?string $slug = null): void
    {
        $this->category = $slug;
    }

    public function addToCart(int $productId): void
    {
        if (!Auth::check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }

        $product = Product::findOrFail($productId);

        if ($product->stock <= 0) {
            $this->dispatch('notify', type: 'error', message: 'Produk ini sedang kehabisan stok!');
            return;
        }

        $cartItem = CartItem::where('user_id', Auth::id())
            ->where('product_id', $productId)
            ->first();

        $currentQty = $cartItem ? $cartItem->quantity : 0;

        if ($currentQty + 1 > $product->stock) {
            $this->dispatch('notify', type: 'error', message: 'Stok tidak mencukupi untuk menambah jumlah item!');
            return;
        }

        if ($cartItem) {
            $cartItem->increment('quantity');
        } else {
            CartItem::create([
                'user_id' => Auth::id(),
                'product_id' => $productId,
                'quantity' => 1,
            ]);
        }

        $this->dispatch('cart-updated');
        $this->dispatch('notify', type: 'success', message: 'Berhasil ditambahkan ke keranjang belanja!');
    }
};
?>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <!-- Hero Header -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 p-8 sm:p-12 shadow-xl">
            <div class="absolute inset-0 bg-white/10 backdrop-blur-[2px]"></div>
            <div class="relative z-10 max-w-2xl">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/20 text-white mb-4 backdrop-blur-md">
                    ✨ Selamat Datang di E-ShoesBox
                </span>
                <h1 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight leading-none mb-4">
                    Melangkah dengan Kenyamanan Premium
                </h1>
                <p class="text-lg text-purple-100 mb-6">
                    Temukan sneakers buatan tangan, sepatu lari performa tinggi, dan alas kaki kasual harian yang dirancang untuk langkah terbaik Anda.
                </p>
                <a href="#catalog" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-xl text-indigo-700 bg-white hover:bg-indigo-50 transition duration-150 ease-in-out shadow-md hover:scale-105 transform">
                    Jelajahi Koleksi
                </a>
            </div>
            <!-- Background Decorative Glows -->
            <div class="absolute right-0 top-0 -mr-20 -mt-20 w-80 h-80 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute left-1/3 bottom-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-purple-500/20 blur-3xl"></div>
        </div>
    </div>

    <!-- Main Section -->
    <div id="catalog" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="lg:grid lg:grid-cols-4 lg:gap-8">
                       <!-- Sidebar Filters -->
            <div class="hidden lg:block lg:col-span-1 space-y-6">
                <!-- Search -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Pencarian</h3>
                    <div class="relative">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari sepatu..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Kategori</h3>
                    <div class="space-y-2">
                        <button wire:click="selectCategory(null)" class="w-full text-left px-3 py-2 rounded-xl text-sm transition-all duration-150 flex items-center justify-between {{ is_null($category) ? 'bg-indigo-50 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            <span>Semua Sepatu</span>
                            <span class="text-xs bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full">{{ App\Models\Product::where('is_active', true)->count() }}</span>
                        </button>
                        @foreach($categories as $cat)
                            <button wire:click="selectCategory('{{ $cat->slug }}')" class="w-full text-left px-3 py-2 rounded-xl text-sm transition-all duration-150 flex items-center justify-between {{ $category === $cat->slug ? 'bg-indigo-50 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                <span>{{ $cat->name }}</span>
                                <span class="text-xs bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full">{{ $cat->products()->where('is_active', true)->count() }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Sorting -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Urutkan</h3>
                    <select wire:model.live="sort" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-955 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="latest">Terbaru</option>
                        <option value="price_asc">Harga: Terendah ke Tertinggi</option>
                        <option value="price_desc">Harga: Tertinggi ke Terendah</option>
                    </select>
                </div>
            </div>v>

            <!-- Product Grid Area -->
            <div class="lg:col-span-3 space-y-6"                <!-- Mobile Controls (Search & Sort) -->
                <div class="lg:hidden bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm border border-gray-100 dark:border-gray-700 space-y-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari sepatu..." class="w-full pl-4 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 sm:text-sm">
                    <div class="flex gap-2">
                        <select wire:model.live="category" class="w-1/2 border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 sm:text-sm">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="sort" class="w-1/2 border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 sm:text-sm">
                            <option value="latest">Terbaru</option>
                            <option value="price_asc">Harga Terendah</option>
                            <option value="price_desc">Harga Tertinggi</option>
                        </select>
                    </div>
                </div>

                <!-- Product Catalog Grid -->
                @if($products->isEmpty())
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-12 text-center shadow-sm border border-gray-100 dark:border-gray-700">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">Sepatu tidak ditemukan</h3>
                        <p class="mt-2 text-sm text-gray-500">Coba sesuaikan filter atau kata kunci pencarian Anda.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($products as $prod)
                            <div class="group bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-xl transition duration-300 flex flex-col h-full transform hover:-translate-y-1">
                                <!-- Image Card -->
                                <div wire:click="openDetailModal({{ $prod->id }})" class="relative pt-[100%] bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 dark:from-gray-800 dark:via-gray-700 dark:to-gray-850 overflow-hidden cursor-pointer">
                                    @if($prod->image_path)
                                        <img src="{{ asset('storage/' . $prod->image_path) }}" alt="{{ $prod->name }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition duration-300">
                                    @else
                                        <!-- Premium Graphic Placeholder -->
                                        <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center select-none">
                                            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md transform group-hover:rotate-12 transition duration-300">
                                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                            </div>
                                            <span class="mt-3 text-xs font-semibold text-indigo-650 dark:text-indigo-400 uppercase tracking-widest">{{ $prod->category->name }}</span>
                                        </div>
                                    @endif

                                    <!-- Stock Badge -->
                                    <div class="absolute top-4 left-4">
                                        @if($prod->stock > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-800">
                                                Stok Tersedia ({{ $prod->stock }})
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-100 dark:border-rose-800">
                                                Stok Habis
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Discount Badge -->
                                    @if($prod->has_discount)
                                        <div class="absolute top-4 right-4 z-10">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-gradient-to-r from-rose-500 to-amber-500 text-white shadow-md border border-white/20 animate-pulse">
                                                <span>🔥</span>
                                                <span>{{ $prod->discount_percentage }}% OFF</span>
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Content Card -->
                                <div class="p-6 flex flex-col flex-1">
                                    <div class="flex-1">
                                        <span class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold uppercase tracking-wider block mb-1">
                                            {{ $prod->category->name }}
                                        </span>
                                        <h2 wire:click="openDetailModal({{ $prod->id }})" class="text-lg font-bold text-gray-900 dark:text-gray-100 group-hover:text-indigo-650 transition duration-150 mb-2 cursor-pointer">
                                            {{ $prod->name }}
                                        </h2>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 mb-4">
                                            {{ $prod->description }}
                                        </p>
                                    </div>

                                    <!-- Purchase / Actions -->
                                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                                        <div>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-widest block font-bold">Harga</span>
                                            @if($prod->has_discount)
                                                <div class="flex flex-col">
                                                    <span class="text-xs text-rose-500 line-through leading-none font-medium">Rp {{ number_format($prod->price, 0, ',', '.') }}</span>
                                                    <span class="text-lg sm:text-xl font-black text-rose-600 dark:text-rose-400 mt-1 leading-none">
                                                        Rp {{ number_format($prod->selling_price, 0, ',', '.') }}
                                                    </span>
                                                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
                                                        Hemat Rp {{ number_format($prod->price - $prod->selling_price, 0, ',', '.') }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-xl font-black text-gray-950 dark:text-gray-100 mt-1 block">
                                                    Rp {{ number_format($prod->price, 0, ',', '.') }}
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <button 
                                            wire:click="addToCart({{ $prod->id }})" 
                                            {{ $prod->stock <= 0 ? 'disabled' : '' }} 
                                            class="inline-flex items-center justify-center p-3 rounded-2xl transition duration-150 {{ $prod->stock > 0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white hover:scale-105 shadow-md shadow-indigo-200 dark:shadow-none' : 'bg-gray-100 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}"
                                            title="Add to Cart"
                                        >
                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Premium Toast Handler -->
    <div x-data="{ notifications: [] }" 
         @notify.window="notifications.push({ id: Date.now(), type: $event.detail.type, message: $event.detail.message }); setTimeout(() => { notifications = notifications.filter(n => n.id !== notifications[0].id) }, 3000)"
         class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm">
        <template x-for="n in notifications" :key="n.id">
            <div x-show="true" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                 :class="n.type === 'success' ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
                 class="px-4 py-3 rounded-2xl shadow-xl flex items-center gap-2 border border-white/10 backdrop-blur-md font-semibold text-sm">
                 <svg x-show="n.type === 'success'" class="h-5 w-5 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                 <svg x-show="n.type !== 'success'" class="h-5 w-5 shrink-0 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                 <span x-text="n.message"></span>
            </div>
        </template>
    </div>

    <!-- Product Detail Modal -->
    @if($isDetailModalOpen && $this->selectedProduct)
        @php
            $product = $this->selectedProduct;
            $gallery = $this->productGallery;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div wire:click="closeDetailModal" class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity"></div>
            
            <!-- Modal Box -->
            <div class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 max-w-4xl w-full overflow-hidden z-10 transform transition-all duration-300 scale-100 flex flex-col md:flex-row max-h-[90vh] md:max-h-none overflow-y-auto md:overflow-visible">
                <!-- Close Button -->
                <button wire:click="closeDetailModal" class="absolute top-4 right-4 z-20 p-2 rounded-full bg-white/80 dark:bg-gray-700/80 text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white shadow hover:scale-105 transition">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <!-- Left Column: Gallery -->
                <div class="w-full md:w-1/2 p-6 bg-gradient-to-br from-indigo-50/50 via-purple-50/50 to-pink-50/50 dark:from-gray-850 dark:via-gray-800 dark:to-gray-750 flex flex-col justify-between">
                    <!-- Main Preview Image -->
                    <div class="relative pt-[100%] rounded-2xl overflow-hidden bg-white dark:bg-gray-700 shadow-inner">
                        @if(count($gallery) > 0)
                            <img src="{{ asset('storage/' . $gallery[$this->selectedImageIndex]) }}" alt="{{ $product->name }}" class="absolute inset-0 w-full h-full object-cover">
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center select-none text-gray-400">
                                <svg class="w-16 h-16 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                            </div>
                        @endif

                        <!-- Discount Badge inside Modal Preview -->
                        @if($product->has_discount)
                            <div class="absolute top-4 left-4 z-10">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-gradient-to-r from-rose-500 to-amber-500 text-white shadow-md border border-white/20 animate-pulse">
                                    <span>🔥</span>
                                    <span>{{ $product->discount_percentage }}% OFF</span>
                                </span>
                            </div>
                        @endif
                    </div>

                    <!-- Thumbnails Carousel/Grid -->
                    @if(count($gallery) > 1)
                        <div class="flex gap-2 mt-4 overflow-x-auto pb-2 scrollbar-thin">
                            @foreach($gallery as $index => $imgPath)
                                <button wire:click="selectImage({{ $index }})" class="relative w-16 h-16 rounded-xl overflow-hidden bg-white dark:bg-gray-700 shadow-sm border-2 shrink-0 transition {{ $this->selectedImageIndex === $index ? 'border-indigo-650 scale-105' : 'border-transparent opacity-70 hover:opacity-100' }}">
                                    <img src="{{ asset('storage/' . $imgPath) }}" alt="{{ $product->name }} Thumbnail {{ $index }}" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Right Column: Detail Content -->
                <div class="w-full md:w-1/2 p-8 flex flex-col justify-between">
                    <div>
                        <!-- Category name -->
                        <span class="text-xs text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-wider block mb-1">
                            {{ $product->category->name }}
                        </span>
                        
                        <!-- Product Title -->
                        <h2 class="text-2xl font-black text-gray-900 dark:text-white leading-tight mb-2">
                            {{ $product->name }}
                        </h2>

                        <!-- Stock Badge -->
                        <div class="mb-4">
                            @if($product->stock > 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-800">
                                    Stok Tersedia ({{ $product->stock }})
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-100 dark:border-rose-800">
                                    Stok Habis
                                </span>
                            @endif
                        </div>

                        <!-- Price Info -->
                        <div class="py-4 border-y border-gray-100 dark:border-gray-700 mb-6">
                            <span class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-widest block font-bold mb-1">Harga</span>
                            @if($product->has_discount)
                                <div class="flex items-baseline gap-2">
                                    <span class="text-2xl font-black text-rose-600 dark:text-rose-400">
                                        Rp {{ number_format($product->selling_price, 0, ',', '.') }}
                                    </span>
                                    <span class="text-sm text-gray-400 dark:text-gray-550 line-through">
                                        Rp {{ number_format($product->price, 0, ',', '.') }}
                                    </span>
                                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30 px-2 py-0.5 rounded-lg ml-2">
                                        Hemat Rp {{ number_format($product->price - $product->selling_price, 0, ',', '.') }}
                                    </span>
                                </div>
                            @else
                                <span class="text-2xl font-black text-gray-900 dark:text-white">
                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                </span>
                            @endif
                        </div>

                        <!-- Description -->
                        <div class="mb-6">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Deskripsi Produk</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed max-h-48 overflow-y-auto pr-2">
                                {{ $product->description }}
                            </p>
                        </div>

                        <!-- Specs -->
                        <div class="grid grid-cols-2 gap-4 mb-6 bg-gray-50 dark:bg-gray-750 p-4 rounded-2xl text-xs font-medium text-gray-600 dark:text-gray-350">
                            <div>
                                <span class="block text-gray-400 uppercase tracking-wider font-bold mb-0.5">Berat Pengiriman</span>
                                <span class="text-sm font-bold text-gray-800 dark:text-white">{{ $product->weight }} gram</span>
                            </div>
                            <div>
                                <span class="block text-gray-400 uppercase tracking-wider font-bold mb-0.5">Jumlah Stok</span>
                                <span class="text-sm font-bold text-gray-800 dark:text-white">{{ $product->stock }} unit</span>
                            </div>
                        </div>
                    </div>

                    <!-- Add to Cart Button inside Modal -->
                    <button 
                        wire:click="addToCart({{ $product->id }})" 
                        {{ $product->stock <= 0 ? 'disabled' : '' }} 
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-4 rounded-2xl text-sm font-black transition duration-150 {{ $product->stock > 0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-200 dark:shadow-none hover:scale-[1.01]' : 'bg-gray-150 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Tambah ke Keranjang Belanja</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
