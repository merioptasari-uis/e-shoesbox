<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\Category;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public ?string $category = null;
    public string $sort = 'latest';
    public ?int $minPrice = null;
    public ?int $maxPrice = null;

    public ?int $selectedProductId = null;
    public int $selectedImageIndex = 0;
    public bool $isDetailModalOpen = false;

    public ?string $selectedColor = null;
    public ?string $selectedSize = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'category' => ['except' => null],
        'sort' => ['except' => 'latest'],
        'minPrice' => ['except' => null],
        'maxPrice' => ['except' => null],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function updatedMinPrice(): void
    {
        $this->resetPage();
    }

    public function updatedMaxPrice(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->category = null;
        $this->sort = 'latest';
        $this->minPrice = null;
        $this->maxPrice = null;
        $this->resetPage();
    }

    public function openDetailModal(int $productId): void
    {
        $this->selectedProductId = $productId;
        $this->selectedImageIndex = 0;

        $product = Product::with('variants')->find($productId);
        if ($product && $product->variants->isNotEmpty()) {
            $this->selectedColor = $product->variants->first()->color;
            $this->selectedSize = $product->variants->first()->size;
        } else {
            $this->selectedColor = null;
            $this->selectedSize = null;
        }

        $this->isDetailModalOpen = true;
    }

    public function closeDetailModal(): void
    {
        $this->isDetailModalOpen = false;
        $this->selectedProductId = null;
        $this->selectedColor = null;
        $this->selectedSize = null;
    }

    public function selectImage(int $index): void
    {
        $this->selectedImageIndex = $index;
    }

    public function selectColor(string $color): void
    {
        $this->selectedColor = $color;
        $product = $this->selectedProduct;
        if ($product) {
            $availableSize = $product->variants->where('color', $color)->first()?->size;
            if ($availableSize) {
                $this->selectedSize = $availableSize;
            }
        }
    }

    public function selectSize(string $size): void
    {
        $this->selectedSize = $size;
    }

    #[Computed]
    public function selectedProduct(): ?Product
    {
        if (!$this->selectedProductId) {
            return null;
        }
        return Product::with(['category', 'images', 'variants', 'reviews.user'])->find($this->selectedProductId);
    }

    #[Computed]
    public function selectedVariant(): ?\App\Models\ProductVariant
    {
        $product = $this->selectedProduct;
        if (!$product || !$this->selectedColor || !$this->selectedSize) {
            return null;
        }
        return $product->variants
            ->where('color', $this->selectedColor)
            ->where('size', $this->selectedSize)
            ->first();
    }

    #[Computed]
    public function productGallery(): array
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
        $query = Product::query()->with(['category', 'variants'])->where('is_active', true);

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

        if ($this->minPrice !== null && $this->minPrice >= 0) {
            $query->whereRaw('COALESCE(discount_price, price) >= ?', [$this->minPrice]);
        }

        if ($this->maxPrice !== null && $this->maxPrice >= 0) {
            $query->whereRaw('COALESCE(discount_price, price) <= ?', [$this->maxPrice]);
        }

        if ($this->sort === 'price_asc') {
            $query->orderByRaw('COALESCE(discount_price, price) asc');
        } elseif ($this->sort === 'price_desc') {
            $query->orderByRaw('COALESCE(discount_price, price) desc');
        } elseif ($this->sort === 'promo') {
            $query->orderByRaw('CASE WHEN promo_tag IS NOT NULL THEN 0 ELSE 1 END')
                  ->orderBy('created_at', 'desc');
        } elseif ($this->sort === 'discount') {
            $query->orderByRaw('CASE WHEN discount_price IS NOT NULL AND discount_price > 0 THEN 0 ELSE 1 END')
                  ->orderByRaw('(price - COALESCE(discount_price, price)) / price desc')
                  ->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return [
            'products' => $query->paginate(12),
            'categories' => Category::all(),
            'campaigns' => App\Models\Campaign::active()->get(),
        ];
    }

    public function selectCategory(?string $slug = null): void
    {
        $this->category = $slug;
    }

    public function addToCart(int $productId, ?int $variantId = null): void
    {
        if (!Auth::check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }

        $product = Product::findOrFail($productId);

        if ($product->variants()->exists() && !$variantId) {
            $this->openDetailModal($productId);
            return;
        }

        if ($variantId) {
            $variant = $product->variants()->findOrFail($variantId);
            if ($variant->stock <= 0) {
                $this->dispatch('notify', type: 'error', message: 'Varian ini sedang kehabisan stok!');
                return;
            }

            $cartItem = CartItem::where('user_id', Auth::id())
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->first();

            $currentQty = $cartItem ? $cartItem->quantity : 0;

            if ($currentQty + 1 > $variant->stock) {
                $this->dispatch('notify', type: 'error', message: 'Stok tidak mencukupi untuk menambah jumlah item!');
                return;
            }

            if ($cartItem) {
                $cartItem->increment('quantity');
            } else {
                CartItem::create([
                    'user_id' => Auth::id(),
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'quantity' => 1,
                ]);
            }
        } else {
            if ($product->stock <= 0) {
                $this->dispatch('notify', type: 'error', message: 'Produk ini sedang kehabisan stok!');
                return;
            }

            $cartItem = CartItem::where('user_id', Auth::id())
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
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
        }

        $this->dispatch('cart-updated');
        $this->dispatch('notify', type: 'success', message: 'Berhasil ditambahkan ke keranjang belanja!');
    }

    public function buyNow(int $productId, ?int $variantId = null): void
    {
        if (!Auth::check()) {
            $this->redirect(route('login'), navigate: true);
            return;
        }

        $product = Product::findOrFail($productId);

        if ($product->variants()->exists() && !$variantId) {
            $this->openDetailModal($productId);
            return;
        }

        if ($variantId) {
            $variant = $product->variants()->findOrFail($variantId);
            if ($variant->stock <= 0) {
                $this->dispatch('notify', type: 'error', message: 'Varian ini sedang kehabisan stok!');
                return;
            }
        } else {
            if ($product->stock <= 0) {
                $this->dispatch('notify', type: 'error', message: 'Produk ini sedang kehabisan stok!');
                return;
            }
        }

        $url = route('cart', [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'qty' => 1
        ]);

        $this->redirect($url, navigate: true);
    }
};
?>

@push('meta')
    <meta name="description" content="Temukan koleksi sepatu premium terlengkap dengan harga diskon dan promo menarik di e-shoesbox. Belanja aman dengan sistem pembayaran Midtrans dan estimasi ongkir RajaOngkir.">
    <meta name="keywords" content="e-shoesbox, sepatu premium, beli sepatu, diskon sepatu, toko sepatu online, rajaongkir, midtrans">
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="e-shoesbox - Toko Sepatu Premium & Flash Sale">
    <meta property="og:description" content="Temukan koleksi sepatu premium terlengkap dengan harga diskon dan promo menarik di e-shoesbox.">
    <meta property="og:image" content="{{ asset('favicon.svg') }}">
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="e-shoesbox - Toko Sepatu Premium & Flash Sale">
    <meta property="twitter:description" content="Temukan koleksi sepatu premium terlengkap dengan harga diskon dan promo menarik di e-shoesbox.">
    <meta property="twitter:image" content="{{ asset('favicon.svg') }}">
@endpush

@php
    $slidesCount = $campaigns->isEmpty() ? 3 : $campaigns->count();
@endphp
<div class="py-8 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <!-- Premium Banner & Promotion Carousel Hero -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Banner Slider (2 Columns on Large Screens) -->
            <div class="lg:col-span-2 relative overflow-hidden rounded-[32px] shadow-lg bg-gray-900 h-[280px] sm:h-[350px] group"
                 x-data="{ activeSlide: 0, totalSlides: {{ $slidesCount }}, timer: null }"
                 x-init="timer = setInterval(() => { activeSlide = (activeSlide + 1) % totalSlides }, 6000)"
                 @mouseenter="clearInterval(timer)"
                 @mouseleave="timer = setInterval(() => { activeSlide = (activeSlide + 1) % totalSlides }, 6000)">
                
                @if($campaigns->isNotEmpty())
                    @foreach($campaigns as $index => $camp)
                        @php
                            $gradientClasses = match($camp->bg_gradient) {
                                'emerald' => 'from-emerald-600 via-teal-650 to-indigo-700',
                                'rose' => 'from-rose-600 via-red-550 to-orange-500',
                                'amber' => 'from-amber-500 via-yellow-550 to-orange-600',
                                'purple' => 'from-purple-600 via-pink-600 to-rose-700',
                                default => 'from-indigo-600 via-purple-600 to-pink-500',
                            };
                            
                            $promoEmoji = match($camp->promo_tag) {
                                'Idul Fitri', 'Ramadhan' => '🕌',
                                'Natal' => '🎄',
                                'Imlek' => '🏮',
                                'Tahun Baru' => '🎆',
                                default => '✨',
                            };
                        @endphp
                        <div x-show="activeSlide === {{ $index }}" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 bg-gradient-to-r {{ $gradientClasses }} flex items-center p-8 sm:p-12" style="{{ $index === 0 ? '' : 'display: none;' }}">
                            <div class="relative z-10 max-w-md">
                                @if($camp->badge_text)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] sm:text-xs font-black bg-white/20 text-white mb-4 backdrop-blur-md">
                                        {{ $promoEmoji }} {{ strtoupper($camp->badge_text) }}
                                    </span>
                                @endif
                                <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight mb-3">
                                    {{ $camp->title }}
                                </h2>
                                @if($camp->subtitle)
                                    <p class="text-xs sm:text-sm text-white/95 mb-6">
                                        {{ $camp->subtitle }}
                                    </p>
                                @endif
                                <a href="{{ $camp->button_link }}" class="inline-flex items-center justify-center px-5 py-2.5 text-xs sm:text-sm font-bold rounded-2xl text-gray-800 bg-white hover:bg-gray-50 transition transform hover:scale-105 shadow-md">
                                    {{ $camp->button_text }} ➜
                                </a>
                            </div>
                            <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-white/10 blur-3xl"></div>
                            <span class="absolute right-12 bottom-12 text-8xl sm:text-9xl opacity-20 pointer-events-none select-none">{{ $promoEmoji }}</span>
                        </div>
                    @endforeach
                @else
                    <!-- Fallback Slide 1 (Holiday / Ramadhan Festive) -->
                    <div x-show="activeSlide === 0" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 bg-gradient-to-r from-emerald-600 via-teal-650 to-indigo-700 flex items-center p-8 sm:p-12">
                        <div class="relative z-10 max-w-md">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] sm:text-xs font-black bg-white/20 text-white mb-4 backdrop-blur-md">
                                🕌 FESTIVAL HARI RAYA
                            </span>
                            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight mb-3">
                                Idul Fitri Mega Promo! Diskon Hingga 70%
                            </h2>
                            <p class="text-xs sm:text-sm text-emerald-50 mb-6">
                                Lengkapi penampilan suci Anda dengan sneakers premium terbaik kami. Dapatkan diskon spesial & gratis ongkir.
                            </p>
                            <a href="#catalog" class="inline-flex items-center justify-center px-5 py-2.5 text-xs sm:text-sm font-bold rounded-2xl text-emerald-700 bg-white hover:bg-emerald-50 transition transform hover:scale-105 shadow-md">
                                Beli Sekarang ➜
                            </a>
                        </div>
                        <!-- Decorative elements -->
                        <div class="absolute -right-10 -bottom-10 w-64 h-64 rounded-full bg-white/10 blur-3xl"></div>
                        <span class="absolute right-12 bottom-12 text-8xl sm:text-9xl opacity-20 pointer-events-none select-none">🕌</span>
                    </div>

                    <!-- Fallback Slide 2 (Sneakers / Sports Fashion) -->
                    <div x-show="activeSlide === 1" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 flex items-center p-8 sm:p-12" style="display: none;">
                        <div class="relative z-10 max-w-md">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] sm:text-xs font-black bg-white/20 text-white mb-4 backdrop-blur-md">
                                ⚡ NEW RELEASES
                            </span>
                            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight mb-3">
                                Mid-Year Sneaker Festival
                            </h2>
                            <p class="text-xs sm:text-sm text-indigo-50 mb-6">
                                Temukan rilisan eksklusif dan varian warna terbaru dari model terlaris kami. Langkah lebih trendi, performa maksimal.
                            </p>
                            <a href="#catalog" class="inline-flex items-center justify-center px-5 py-2.5 text-xs sm:text-sm font-bold rounded-2xl text-indigo-700 bg-white hover:bg-indigo-50 transition transform hover:scale-105 shadow-md">
                                Lihat Koleksi ➜
                            </a>
                        </div>
                        <div class="absolute -left-10 -top-10 w-64 h-64 rounded-full bg-purple-500/25 blur-3xl"></div>
                        <span class="absolute right-16 bottom-10 text-8xl sm:text-9xl opacity-20 pointer-events-none select-none">👟</span>
                    </div>

                    <!-- Fallback Slide 3 (Voucher Promotion) -->
                    <div x-show="activeSlide === 2" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="absolute inset-0 bg-gradient-to-r from-rose-600 via-red-550 to-orange-500 flex items-center p-8 sm:p-12" style="display: none;">
                        <div class="relative z-10 max-w-md">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] sm:text-xs font-black bg-white/20 text-white mb-4 backdrop-blur-md">
                                🔥 EXTRA CASHBACK
                            </span>
                            <h2 class="text-2xl sm:text-4xl font-black text-white tracking-tight leading-tight mb-3">
                                Ekstra Cashback 10% Hingga Rp 50.000
                            </h2>
                            <p class="text-xs sm:text-sm text-red-50 mb-6">
                                Gunakan voucher belanja bertumpuk untuk hemat berlipat ganda. Makin banyak belanja, makin untung!
                            </p>
                            <a href="#catalog" class="inline-flex items-center justify-center px-5 py-2.5 text-xs sm:text-sm font-bold rounded-2xl text-red-700 bg-white hover:bg-red-50 transition transform hover:scale-105 shadow-md">
                                Klaim Voucher ➜
                            </a>
                        </div>
                        <div class="absolute -right-20 -top-20 w-80 h-80 rounded-full bg-orange-400/20 blur-3xl"></div>
                        <span class="absolute right-12 bottom-12 text-8xl sm:text-9xl opacity-20 pointer-events-none select-none">🧧</span>
                    </div>
                @endif

                <!-- Carousel Slide Indicators (Dots) -->
                <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex items-center gap-2 z-20">
                    <template x-for="i in {{ $slidesCount }}">
                        <button @click="activeSlide = i - 1" :class="activeSlide === i - 1 ? 'w-6 bg-white' : 'w-2 bg-white/50'" class="h-2 rounded-full transition-all duration-300"></button>
                    </template>
                </div>

                <!-- Navigation Arrows -->
                <button @click="activeSlide = (activeSlide - 1 + totalSlides) % totalSlides" class="absolute left-4 top-1/2 transform -translate-y-1/2 w-8 h-8 rounded-full bg-black/30 hover:bg-black/50 text-white flex items-center justify-center transition-all opacity-0 group-hover:opacity-100">
                    ❮
                </button>
                <button @click="activeSlide = (activeSlide + 1) % totalSlides" class="absolute right-4 top-1/2 transform -translate-y-1/2 w-8 h-8 rounded-full bg-black/30 hover:bg-black/50 text-white flex items-center justify-center transition-all opacity-0 group-hover:opacity-100">
                    ❯
                </button>
            </div>

            <!-- Right Promo Cards (1 Column) -->
            <div class="flex flex-col gap-4">
                <!-- Voucher Card with Copy Functionality -->
                <div x-data="{ copied: false }" class="relative overflow-hidden bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-800 rounded-3xl p-5 border border-indigo-100 dark:border-indigo-900/50 flex flex-col justify-between flex-1 group shadow-sm hover:shadow-md transition-shadow">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-500/10 rounded-full blur-xl"></div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-700 dark:text-indigo-400">KODE DISKON EKSTRA</span>
                            <span class="text-xs bg-indigo-200 dark:bg-indigo-950/50 text-indigo-800 dark:text-indigo-400 px-2 py-0.5 rounded-full font-bold">Terbatas</span>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white leading-snug mb-1">Promo Khusus Pengguna Baru</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Salin kode ini dan masukkan saat checkout untuk potongan harga langsung Rp 20.000!</p>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <div class="flex-1 bg-white dark:bg-gray-700 border-2 border-dashed border-indigo-300 dark:border-indigo-600 rounded-2xl px-3 py-2 text-center text-xs font-black text-indigo-700 dark:text-indigo-300 select-all tracking-wider font-mono">
                            COBAINBARU
                        </div>
                        <button @click="navigator.clipboard.writeText('COBAINBARU'); copied = true; setTimeout(() => copied = false, 2000)" 
                                :class="copied ? 'bg-emerald-600 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
                                class="px-4 py-2 text-xs font-bold rounded-2xl transition duration-150 transform hover:scale-105 flex items-center gap-1">
                            <span x-text="copied ? 'Tersalin!' : 'Salin'"></span>
                        </button>
                    </div>
                </div>

                <!-- Direct Promo Link Card -->
                <div class="relative overflow-hidden bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-950/15 dark:to-orange-950/15 rounded-3xl p-5 border border-amber-100 dark:border-amber-900/30 flex flex-col justify-between flex-1 group shadow-sm hover:shadow-md transition-shadow">
                    <div class="absolute bottom-0 right-0 w-24 h-24 bg-amber-500/10 rounded-full blur-xl"></div>
                    <div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-amber-700 dark:text-amber-400">INFO CASHBACK</span>
                            <span class="text-xs bg-amber-200 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 px-2 py-0.5 rounded-full font-bold">100% Asli</span>
                        </div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white leading-snug mb-1">Promo Bebas Ongkir Toko</h3>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">Gunakan Voucher Cashback & Bebas Ongkir saat belanja untuk diskon maksimal.</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-700 dark:text-amber-400">Min. Belanja Rp 150.000</span>
                        <a href="#catalog" class="text-xs font-black text-orange-600 dark:text-orange-400 hover:underline flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                            <span>Mulai Belanja</span> <span>➔</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Flash Sale Section (urges buying with countdown) -->
    @php
        $flashSaleProducts = $products->filter(fn($p) => $p->has_discount)->take(4);
    @endphp
    @if($flashSaleProducts->isNotEmpty())
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12">
            <div class="bg-gradient-to-r from-rose-50 to-orange-50 dark:from-red-950/15 dark:to-orange-950/15 rounded-[32px] p-6 sm:p-8 border border-red-100/50 dark:border-red-900/20 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl animate-bounce">⚡</span>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-black text-rose-600 dark:text-rose-400 tracking-tight flex items-center gap-2">
                                FLASH SALE HARI INI
                            </h2>
                            <p class="text-xs text-orange-600 dark:text-orange-300 font-semibold uppercase tracking-wider">Diskon Paling Heboh Spesial Jam Ini!</p>
                        </div>
                    </div>
                    <!-- Countdown Timer -->
                    <div x-data="{
                             hours: '02',
                             minutes: '48',
                             seconds: '15',
                             init() {
                                 let totalSeconds = 2 * 3600 + 48 * 60 + 15;
                                 setInterval(() => {
                                     if (totalSeconds > 0) totalSeconds--;
                                     let h = Math.floor(totalSeconds / 3600);
                                     let m = Math.floor((totalSeconds % 3600) / 60);
                                     let s = totalSeconds % 60;
                                     this.hours = String(h).padStart(2, '0');
                                     this.minutes = String(m).padStart(2, '0');
                                     this.seconds = String(s).padStart(2, '0');
                                 }, 1000);
                             }
                         }" 
                         class="flex items-center gap-2 font-mono text-xs sm:text-sm font-bold text-white bg-rose-600 px-4 py-2 rounded-2xl shadow-md self-start sm:self-auto">
                        <span class="uppercase text-[10px] tracking-wider font-extrabold mr-1">Sisa Waktu:</span>
                        <span x-text="hours" class="bg-black/20 px-2 py-0.5 rounded-lg"></span>
                        <span>:</span>
                        <span x-text="minutes" class="bg-black/20 px-2 py-0.5 rounded-lg"></span>
                        <span>:</span>
                        <span x-text="seconds" class="bg-black/20 px-2 py-0.5 rounded-lg"></span>
                    </div>
                </div>

                <!-- Flash Sale Products Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                    @foreach($flashSaleProducts as $prod)
                        @php
                            $ratingVal = $prod->average_rating;
                            $mockSalesCount = $prod->sales_count;
                            $stockLeft = $prod->stock;
                            $soldPercentage = $stockLeft > 0 ? (int) round(($mockSalesCount / ($mockSalesCount + $stockLeft)) * 100) : 100;
                        @endphp
                        <div wire:key="flash-sale-{{ $prod->id }}" class="group bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-xl transition duration-300 flex flex-col h-full transform hover:-translate-y-1 relative">
                            <!-- Image Card -->
                            <div wire:click="openDetailModal({{ $prod->id }})" class="relative pt-[100%] bg-gradient-to-br from-red-50 to-orange-50 dark:from-gray-700 dark:to-gray-800 overflow-hidden cursor-pointer">
                                @if($prod->image_path)
                                    <img src="{{ asset('storage/' . $prod->image_path) }}" alt="{{ $prod->name }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition duration-500" loading="lazy">
                                @else
                                    <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center select-none text-gray-400">
                                        <svg class="w-12 h-12 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    </div>
                                @endif
                                
                                <!-- Promo Tag (e.g. Idul Fitri) -->
                                @if($prod->promo_tag)
                                    <div class="absolute bottom-3 left-3 z-10">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-bold bg-rose-600 text-white shadow">
                                            🔥 {{ $prod->promo_tag }}
                                        </span>
                                    </div>
                                @endif

                                <!-- Discount Badge -->
                                <div class="absolute top-3 right-3 z-10">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-600 text-white shadow-md">
                                        {{ $prod->discount_percentage }}% OFF
                                    </span>
                                </div>
                            </div>

                            <!-- Content -->
                            <div class="p-4 flex flex-col flex-1">
                                <div class="flex-1">
                                    <h3 wire:click="openDetailModal({{ $prod->id }})" class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest block mb-1">{{ $prod->category->name }}</h3>
                                    <h2 wire:click="openDetailModal({{ $prod->id }})" class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-rose-600 transition cursor-pointer line-clamp-1 mb-1">
                                        {{ $prod->name }}
                                    </h2>
                                    
                                    <!-- Ratings and Sales -->
                                    <div class="flex items-center gap-1 text-[11px] text-gray-500 dark:text-gray-400 mb-2">
                                        <span class="text-amber-400">★</span>
                                        <span class="font-bold text-gray-800 dark:text-gray-200">{{ $ratingVal }}</span>
                                        <span>|</span>
                                        <span>Terjual {{ $mockSalesCount }}+</span>
                                    </div>

                                    <!-- Price -->
                                    <div class="flex flex-col mb-3">
                                        <span class="text-[10px] text-gray-400 dark:text-gray-500 line-through">Rp {{ number_format($prod->price, 0, ',', '.') }}</span>
                                        <span class="text-base font-black text-rose-600 dark:text-rose-400">
                                            Rp {{ number_format($prod->selling_price, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Progress bar for urgency -->
                                <div class="mt-2">
                                    <div class="flex justify-between text-[9px] font-bold mb-1">
                                        <span class="text-gray-500 dark:text-gray-400">Terjual {{ $soldPercentage }}%</span>
                                        <span class="text-rose-600">Sisa {{ $stockLeft }} unit!</span>
                                    </div>
                                    <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
                                        <div class="bg-gradient-to-r from-rose-500 to-orange-500 h-full rounded-full" style="width: {{ $soldPercentage }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Main Section (Product Catalog grid & Filters) -->
    <div id="catalog" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 scroll-mt-20">
        <div class="lg:grid lg:grid-cols-4 lg:gap-8">
            
            <!-- Left Sidebar Filters -->
            <div class="hidden lg:block lg:col-span-1 space-y-6">
                <!-- Search Box -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/80">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">PENCARIAN</h3>
                    <div class="relative">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari model sepatu..." class="w-full pl-10 pr-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500/25 focus:border-indigo-600 sm:text-sm transition-all duration-150">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                </div>

                <!-- Categories filter -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/80">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-4">KATEGORI</h3>
                    <div class="space-y-1.5">
                        <button wire:click="selectCategory(null)" class="w-full text-left px-3 py-2.5 rounded-xl text-xs sm:text-sm transition-all duration-150 flex items-center justify-between {{ is_null($category) ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 font-bold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                            <span class="flex items-center gap-2">
                                <span>👟</span>
                                <span>Semua Kategori</span>
                            </span>
                            <span class="text-[10px] bg-gray-150 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full font-bold">
                                {{ App\Models\Product::where('is_active', true)->count() }}
                            </span>
                        </button>
                        @foreach($categories as $cat)
                            @php
                                $categoryIcons = [
                                    'sneakers' => '👟',
                                    'running' => '🏃',
                                    'casual' => '👞',
                                    'sandals' => '🩴',
                                    'boots' => '🥾',
                                    'sport' => '⚽',
                                ];
                                $slug = strtolower($cat->slug);
                                $icon = '👟';
                                foreach($categoryIcons as $key => $ico) {
                                    if (str_contains($slug, $key)) {
                                        $icon = $ico;
                                        break;
                                    }
                                }
                            @endphp
                            <button wire:click="selectCategory('{{ $cat->slug }}')" class="w-full text-left px-3 py-2.5 rounded-xl text-xs sm:text-sm transition-all duration-150 flex items-center justify-between {{ $category === $cat->slug ? 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 font-bold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                                <span class="flex items-center gap-2">
                                    <span>{{ $icon }}</span>
                                    <span>{{ $cat->name }}</span>
                                </span>
                                <span class="text-[10px] bg-gray-150 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full font-bold">
                                    {{ $cat->products()->where('is_active', true)->count() }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Price Filter Range -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/80">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-4">FILTER HARGA</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest block mb-1">Harga Minimum</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-gray-400 select-none">Rp</span>
                                <input wire:model.live.debounce.600ms="minPrice" type="number" placeholder="Min" class="w-full pl-9 pr-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-indigo-500 sm:text-xs">
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest block mb-1">Harga Maksimum</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs font-bold text-gray-400 select-none">Rp</span>
                                <input wire:model.live.debounce.600ms="maxPrice" type="number" placeholder="Maks" class="w-full pl-9 pr-3 py-2 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-1 focus:ring-indigo-500 sm:text-xs">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sorting and Ordering -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700/80">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3">URUTKAN BY</h3>
                    <select wire:model.live="sort" class="w-full border-gray-200 dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-900 text-gray-950 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500/25 focus:border-indigo-600 sm:text-sm">
                        <option value="latest">Terbaru & Populer</option>
                        <option value="promo">Spesial Promo Raya</option>
                        <option value="discount">Diskon Terbesar</option>
                        <option value="price_asc">Harga: Terendah ke Tertinggi</option>
                        <option value="price_desc">Harga: Tertinggi ke Terendah</option>
                    </select>
                </div>

                <!-- Reset Filters Button -->
                @if(!empty($search) || !is_null($category) || $sort !== 'latest' || !is_null($minPrice) || !is_null($maxPrice))
                    <button wire:click="resetFilters" class="w-full flex items-center justify-center gap-2 py-3 px-4 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/20 dark:text-rose-400 rounded-2xl transition duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        <span>Hapus Semua Filter</span>
                    </button>
                @endif
            </div>

            <!-- Right Catalog Grid Area -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Mobile Navigation / Filters Dashboard -->
                <div class="lg:hidden bg-white dark:bg-gray-800 rounded-3xl p-4 shadow-sm border border-gray-100 dark:border-gray-700/80 space-y-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari sepatu..." class="w-full pl-4 pr-4 py-2 border border-gray-200 dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 sm:text-sm">
                    <div class="flex gap-2">
                        <select wire:model.live="category" class="w-1/2 border-gray-200 dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-900 text-gray-950 dark:text-gray-100 sm:text-xs">
                            <option value="">Semua Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->slug }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="sort" class="w-1/2 border-gray-200 dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-900 text-gray-950 dark:text-gray-100 sm:text-xs">
                            <option value="latest">Terbaru</option>
                            <option value="promo">Promo Spesial</option>
                            <option value="discount">Diskon Terbesar</option>
                            <option value="price_asc">Harga Terendah</option>
                            <option value="price_desc">Harga Tertinggi</option>
                        </select>
                    </div>
                </div>

                <!-- Product Catalog Grid -->
                @if($products->isEmpty())
                    <div class="bg-white dark:bg-gray-800 rounded-[32px] p-16 text-center shadow-sm border border-gray-100 dark:border-gray-700">
                        <svg class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="text-xl font-extrabold text-gray-900 dark:text-gray-100 mb-1">Sepatu Tidak Ditemukan</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Coba ganti filter pencarian atau range harga Anda.</p>
                        @if(!empty($search) || !is_null($category) || $sort !== 'latest' || !is_null($minPrice) || !is_null($maxPrice))
                            <button wire:click="resetFilters" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-2xl transition shadow-md">
                                Hapus Semua Filter
                            </button>
                        @endif
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                        @foreach($products as $prod)
                            @php
                                $ratingVal = $prod->average_rating;
                                $mockReviewsCount = $prod->reviews_count;
                                $mockSalesCount = $prod->sales_count;
                                $uniqueColors = $prod->variants->pluck('color')->unique()->values();
                            @endphp
                            <div wire:key="product-{{ $prod->id }}" x-data="{ isLiked: false }" class="group bg-white dark:bg-gray-800 rounded-[28px] shadow-sm border border-gray-100 dark:border-gray-700/80 overflow-hidden hover:shadow-2xl hover:shadow-indigo-100/50 dark:hover:shadow-none transition duration-300 flex flex-col h-full transform hover:-translate-y-2 relative">
                                
                                <!-- Heart/Wishlist Button -->
                                <button @click.stop="isLiked = !isLiked" class="absolute top-3 right-3 z-20 w-8 h-8 rounded-full bg-white/90 dark:bg-gray-700/90 text-gray-500 hover:text-rose-600 shadow flex items-center justify-center transition hover:scale-110">
                                    <svg :class="isLiked ? 'fill-rose-500 text-rose-500' : 'text-gray-500'" class="h-4.5 w-4.5 transition-colors duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </button>

                                <!-- Product Image Container -->
                                <div wire:click="openDetailModal({{ $prod->id }})" class="relative pt-[100%] bg-gradient-to-br from-indigo-50/50 via-purple-50/50 to-pink-50/50 dark:from-gray-800 dark:to-gray-900 overflow-hidden cursor-pointer">
                                    @if($prod->image_path)
                                        <img src="{{ asset('storage/' . $prod->image_path) }}" alt="{{ $prod->name }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition duration-500" loading="lazy">
                                    @else
                                        <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center select-none text-gray-400">
                                            <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md group-hover:rotate-12 transition duration-300">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                            </div>
                                            <span class="mt-2 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">{{ $prod->category->name }}</span>
                                        </div>
                                    @endif

                                    <!-- Elegant Hover Overlay -->
                                    <div class="absolute inset-0 bg-black/10 dark:bg-black/35 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-[1px] z-10">
                                        <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-gray-900 bg-white/95 dark:bg-gray-900/95 dark:text-white shadow-lg transform translate-y-2 group-hover:translate-y-0 transition duration-300">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Lihat Detail
                                        </span>
                                    </div>

                                    <!-- Stock Badge (Top-left) -->
                                    <div class="absolute top-3 left-3 z-10">
                                        @if($prod->stock > 0)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[9px] font-bold bg-emerald-50 text-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/20 shadow-sm">
                                                Ready
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[9px] font-bold bg-rose-50 text-rose-800 dark:bg-rose-950/80 dark:text-rose-400 border border-rose-100 dark:border-rose-800/20 shadow-sm">
                                                Habis
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Discount Badge (Top-right of image) -->
                                    @if($prod->has_discount)
                                        <div class="absolute top-3 right-12 z-10">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-rose-600 text-white shadow-md animate-pulse">
                                                {{ $prod->discount_percentage }}%
                                            </span>
                                        </div>
                                    @endif

                                    <!-- Promo Holiday Tag (Bottom-left of image) -->
                                    @if($prod->promo_tag)
                                        <div class="absolute bottom-3 left-3 z-10">
                                            @if($prod->promo_tag === 'Idul Fitri' || $prod->promo_tag === 'Ramadhan')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-extrabold bg-emerald-600/90 dark:bg-emerald-900/90 text-white dark:text-emerald-300 shadow backdrop-blur-sm border border-emerald-500/25">
                                                    🕌 {{ $prod->promo_tag }}
                                                </span>
                                            @elseif($prod->promo_tag === 'Natal')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-extrabold bg-rose-600/90 dark:bg-rose-900/90 text-white dark:text-rose-300 shadow backdrop-blur-sm border border-rose-500/25">
                                                    🎄 {{ $prod->promo_tag }}
                                                </span>
                                            @elseif($prod->promo_tag === 'Imlek')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-extrabold bg-amber-600/90 dark:bg-amber-900/90 text-white dark:text-amber-300 shadow backdrop-blur-sm border border-amber-500/25">
                                                    🏮 {{ $prod->promo_tag }}
                                                </span>
                                            @elseif($prod->promo_tag === 'Tahun Baru')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-extrabold bg-indigo-600/90 dark:bg-indigo-900/90 text-white dark:text-indigo-300 shadow backdrop-blur-sm border border-indigo-500/25">
                                                    🎆 {{ $prod->promo_tag }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[9px] font-extrabold bg-purple-600/90 dark:bg-purple-900/90 text-white dark:text-purple-300 shadow backdrop-blur-sm border border-purple-500/25">
                                                    ✨ {{ $prod->promo_tag }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <!-- Card Contents -->
                                <div class="p-4 sm:p-5 flex flex-col flex-1 bg-transparent">
                                    <div class="flex-1 bg-transparent">
                                        <div class="flex justify-between items-center mb-1.5 bg-transparent">
                                            <span class="text-[10px] text-indigo-650 dark:text-indigo-400 font-extrabold uppercase tracking-wider block">
                                                {{ $prod->category->name }}
                                            </span>
                                            <!-- Dynamic Color Variant Dots -->
                                            @if($uniqueColors->isNotEmpty())
                                                <div class="flex items-center gap-1 bg-transparent">
                                                    @foreach($uniqueColors->take(3) as $color)
                                                        @php
                                                            $hexColor = match(strtolower(trim($color))) {
                                                                'hitam', 'black' => '#1a1a1a',
                                                                'putih', 'white' => '#f3f4f6',
                                                                'merah', 'red' => '#ef4444',
                                                                'biru', 'blue' => '#3b82f6',
                                                                'hijau', 'green' => '#10b981',
                                                                'kuning', 'yellow' => '#f59e0b',
                                                                'abu-abu', 'abu', 'gray', 'grey' => '#9ca3af',
                                                                'cokelat', 'coklat', 'brown' => '#78350f',
                                                                'navy' => '#1e3a8a',
                                                                'pink', 'merah muda' => '#ec4899',
                                                                default => null
                                                            };
                                                        @endphp
                                                        @if($hexColor)
                                                            <span class="w-2.5 h-2.5 rounded-full border border-gray-200 dark:border-gray-600 block shadow-sm ring-1 ring-black/5" style="background-color: {{ $hexColor }};" title="{{ $color }}"></span>
                                                        @else
                                                            <span class="w-3.5 h-3.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-[8px] font-black flex items-center justify-center border border-gray-200 dark:border-gray-600 uppercase block" title="{{ $color }}">
                                                                {{ substr($color, 0, 1) }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                    @if($uniqueColors->count() > 3)
                                                        <span class="text-[9px] font-bold text-gray-400 dark:text-gray-500">+{{ $uniqueColors->count() - 3 }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <h2 wire:click="openDetailModal({{ $prod->id }})" class="text-sm sm:text-base font-bold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 transition cursor-pointer line-clamp-2 mb-2 min-h-[40px] leading-tight">
                                            {{ $prod->name }}
                                        </h2>
                                        
                                        <!-- Star Ratings and Sold Mockups -->
                                        <div class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400 mb-3 bg-transparent">
                                            <div class="flex items-center gap-0.5 px-1.5 py-0.5 rounded-lg bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 font-extrabold text-[10px]">
                                                <span>★</span>
                                                <span>{{ number_format($ratingVal, 1) }}</span>
                                            </div>
                                            <span class="text-gray-400 dark:text-gray-600 font-medium">({{ $mockReviewsCount }})</span>
                                            <span class="text-gray-300 dark:text-gray-750">•</span>
                                            <span class="text-gray-600 dark:text-gray-300 font-bold bg-gray-50 dark:bg-gray-700/40 px-1.5 py-0.5 rounded-lg text-[9px]">{{ $mockSalesCount }}+ terjual</span>
                                        </div>
                                    </div>

                                    <!-- Price & Cart Actions footer -->
                                    <div class="mt-2 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2 bg-transparent">
                                        <div class="min-w-0 bg-transparent">
                                            @if($prod->has_discount)
                                                <div class="flex flex-col bg-transparent">
                                                    <span class="text-[10px] text-rose-500 line-through leading-none font-medium">Rp {{ number_format($prod->price, 0, ',', '.') }}</span>
                                                    <span class="text-sm sm:text-base font-black text-rose-600 dark:text-rose-400 mt-1 leading-none">
                                                        Rp {{ number_format($prod->selling_price, 0, ',', '.') }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="text-sm sm:text-base font-black text-gray-950 dark:text-gray-100 mt-1.5 block leading-none bg-transparent">
                                                    Rp {{ number_format($prod->price, 0, ',', '.') }}
                                                </span>
                                            @endif
                                        </div>
                                        
                                        <button 
                                            wire:click="addToCart({{ $prod->id }})" 
                                            {{ $prod->stock <= 0 ? 'disabled' : '' }} 
                                            class="inline-flex items-center justify-center p-2.5 sm:p-3 rounded-xl transition duration-200 shrink-0 {{ $prod->stock > 0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white hover:scale-105 shadow-md shadow-indigo-100 dark:shadow-none' : 'bg-gray-100 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}"
                                            title="{{ $prod->variants->isNotEmpty() ? 'Pilih Varian' : 'Tambah Ke Keranjang' }}"
                                        >
                                            @if($prod->variants->isNotEmpty())
                                                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" /></svg>
                                            @else
                                                <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                                            @endif
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination Links styled neatly -->
                    <div class="mt-10">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Premium Toast Alert notifications (uses modern smooth Alpine transitions) -->
    <div x-data="{ notifications: [] }" 
         @notify.window="notifications.push({ id: Date.now(), type: $event.detail.type, message: $event.detail.message }); setTimeout(() => { notifications = notifications.filter(n => n.id !== notifications[0].id) }, 3500)"
         class="fixed bottom-6 right-6 z-50 flex flex-col gap-2.5 max-w-sm w-[90%] sm:w-auto">
        <template x-for="n in notifications" :key="n.id">
            <div x-show="true" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-250"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-y-3 scale-95"
                 :class="n.type === 'success' ? 'bg-emerald-600 text-white shadow-emerald-250 dark:shadow-none' : 'bg-rose-600 text-white shadow-rose-250 dark:shadow-none'"
                 class="px-4.5 py-3 rounded-2xl shadow-xl flex items-center gap-3 border border-white/10 backdrop-blur-md font-semibold text-xs sm:text-sm">
                 <div class="w-5 h-5 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                     <svg x-show="n.type === 'success'" class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                     <svg x-show="n.type !== 'success'" class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                 </div>
                 <span x-text="n.message" class="flex-1"></span>
            </div>
        </template>
    </div>

    <!-- Product Detail Modal (Redesigned with interactive Size/Color picker simulation) -->
    @if($isDetailModalOpen && $this->selectedProduct)
        @php
            $product = $this->selectedProduct;
            $gallery = $this->productGallery;
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div wire:click="closeDetailModal" class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm transition-opacity"></div>
            
            <!-- Modal Box Container -->
            <div class="relative bg-white dark:bg-gray-800 rounded-[32px] shadow-2xl border border-gray-150 dark:border-gray-700/80 max-w-4xl w-full overflow-y-auto z-10 transform transition-all duration-300 scale-100 flex flex-col max-h-[90vh]">
                
                <!-- Close Button -->
                <button wire:click="closeDetailModal" class="absolute top-4 right-4 z-20 p-2.5 rounded-full bg-white/90 dark:bg-gray-700/90 text-gray-500 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white shadow hover:scale-105 transition-all">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                <!-- Upper Section: Columns -->
                <div class="flex flex-col md:flex-row">
                    <!-- Left Column: Media Gallery -->
                    <div class="w-full md:w-1/2 p-6 bg-gradient-to-br from-indigo-50/30 via-purple-50/30 to-pink-50/30 dark:from-gray-900 dark:to-gray-950 flex flex-col justify-between">
                        <!-- Main Preview Image -->
                        <div class="relative pt-[100%] rounded-2xl overflow-hidden bg-white dark:bg-gray-755 shadow-inner border border-gray-100 dark:border-gray-700">
                            @if(count($gallery) > 0)
                                <img src="{{ asset('storage/' . $gallery[$this->selectedImageIndex]) }}" alt="{{ $product->name }}" class="absolute inset-0 w-full h-full object-cover">
                            @else
                                <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center select-none text-gray-400">
                                    <svg class="w-14 h-14 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                </div>
                            @endif

                            <!-- Top-left Discount percentage inside detail preview -->
                            @if($product->has_discount)
                                <div class="absolute top-4 left-4 z-10">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-gradient-to-r from-rose-600 to-orange-500 text-white shadow-md">
                                        🔥 {{ $product->discount_percentage }}% OFF
                                    </span>
                                </div>
                            @endif
                        </div>

                        <!-- Gallery Carousel Thumbnails -->
                        @if(count($gallery) > 1)
                            <div class="flex gap-2.5 mt-4 overflow-x-auto pb-2 scrollbar-thin">
                                @foreach($gallery as $index => $imgPath)
                                    <button wire:click="selectImage({{ $index }})" class="relative w-16 h-16 rounded-xl overflow-hidden bg-white dark:bg-gray-700 shadow-sm border-2 shrink-0 transition-all {{ $this->selectedImageIndex === $index ? 'border-indigo-650 scale-105 shadow-md' : 'border-transparent opacity-70 hover:opacity-100' }}">
                                        <img src="{{ asset('storage/' . $imgPath) }}" alt="Thumbnail {{ $index }}" class="w-full h-full object-cover" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Right Column: Detail Content and Buy Actions -->
                    <div class="w-full md:w-1/2 p-6 sm:p-8 flex flex-col justify-between bg-white dark:bg-gray-800">
                        <div>
                            <!-- Category name and breadcrumb link -->
                            <span class="text-xs text-indigo-600 dark:text-indigo-400 font-extrabold uppercase tracking-widest block mb-1">
                                {{ $product->category->name }}
                            </span>
                            
                            <!-- Product Title -->
                            <h2 class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white leading-tight mb-2">
                                {{ $product->name }}
                            </h2>

                            <!-- Ratings, Reviews, and Sold Volumes -->
                            <div class="flex items-center gap-2 mb-4 text-xs">
                                <div class="flex items-center text-amber-400">
                                    <span>★</span>
                                    <span class="ml-1 font-bold text-gray-800 dark:text-gray-200">{{ $product->average_rating }}</span>
                                </div>
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span class="text-gray-600 dark:text-gray-400 font-medium">{{ $product->reviews->count() }} Ulasan</span>
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span class="bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded font-extrabold">{{ $product->sales_count }}+ Terjual</span>
                            </div>

                            <!-- Special Tags Row (Bebas Ongkir, Cashback) -->
                            <div class="flex flex-wrap items-center gap-2 mb-4">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400 border border-green-150 dark:border-green-900/25">
                                    🚚 Bebas Ongkir
                                </span>
                                @if($product->promo_tag)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 border border-amber-150 dark:border-amber-900/25">
                                        ✨ {{ $product->promo_tag }}
                                    </span>
                                @endif
                            </div>

                            <!-- Price Info container -->
                            <div class="py-3 border-y border-gray-100 dark:border-gray-700 mb-5">
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase tracking-widest block font-bold mb-0.5">Harga Terbaik</span>
                                @if($product->has_discount)
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        <span class="text-2xl font-black text-rose-600 dark:text-rose-450 leading-none">
                                            Rp {{ number_format($product->selling_price, 0, ',', '.') }}
                                        </span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 line-through">
                                            Rp {{ number_format($product->price, 0, ',', '.') }}
                                        </span>
                                        <span class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 px-2 py-0.5 rounded-lg">
                                            Hemat Rp {{ number_format($product->price - $product->selling_price, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-2xl font-black text-gray-900 dark:text-white leading-none">
                                        Rp {{ number_format($product->price, 0, ',', '.') }}
                                    </span>
                                @endif
                            </div>

                            <!-- Interactive Options Selection (Dynamic Sizing & Colors) -->
                            @if($product->variants->isNotEmpty())
                                @php
                                    $colors = $product->variants->pluck('color')->unique();
                                    $sizes = $product->variants->where('color', $this->selectedColor)->pluck('size')->unique();
                                    $currentVariant = $this->selectedVariant;
                                    $variantStock = $currentVariant ? $currentVariant->stock : 0;
                                @endphp
                                <div class="space-y-4 mb-5">
                                    <!-- Color Picker -->
                                    <div>
                                        <h4 class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">PILIH WARNA: <span class="text-gray-800 dark:text-white font-extrabold">{{ $this->selectedColor }}</span></h4>
                                        <div class="flex items-center gap-2 flex-wrap bg-transparent">
                                            @foreach($colors as $color)
                                                @php
                                                    $hexColor = match(strtolower(trim($color))) {
                                                        'hitam', 'black' => '#1a1a1a',
                                                        'putih', 'white' => '#ffffff',
                                                        'merah', 'red' => '#ef4444',
                                                        'biru', 'blue' => '#3b82f6',
                                                        'hijau', 'green' => '#10b981',
                                                        'kuning', 'yellow' => '#f59e0b',
                                                        'abu-abu', 'abu', 'gray', 'grey' => '#9ca3af',
                                                        'cokelat', 'coklat', 'brown' => '#78350f',
                                                        'navy' => '#1e3a8a',
                                                        'pink', 'merah muda' => '#ec4899',
                                                        default => null
                                                    };
                                                @endphp
                                                <button 
                                                    wire:click="selectColor('{{ $color }}')"
                                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold border-2 transition duration-150 {{ $this->selectedColor === $color ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-100 dark:shadow-none' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-indigo-600 hover:scale-[1.02]' }}"
                                                >
                                                    @if($hexColor)
                                                        <span class="w-3 h-3 rounded-full border border-gray-200 dark:border-gray-650 shadow-inner block" style="background-color: {{ $hexColor }};"></span>
                                                    @endif
                                                    <span>{{ $color }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Sizing buttons selector -->
                                    <div>
                                        <h4 class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">PILIH UKURAN (EU): <span class="text-gray-800 dark:text-white font-extrabold">{{ $this->selectedSize }}</span></h4>
                                        <div class="flex flex-wrap gap-2 bg-transparent">
                                            @foreach($sizes as $size)
                                                @php
                                                    $vStock = $product->variants->where('color', $this->selectedColor)->where('size', $size)->first()?->stock ?? 0;
                                                @endphp
                                                <button 
                                                    wire:click="selectSize('{{ $size }}')" 
                                                    {{ $vStock <= 0 ? 'disabled' : '' }}
                                                    class="px-3.5 py-2 text-xs font-bold border-2 rounded-xl transition duration-150 relative {{ $this->selectedSize === $size ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-100 dark:shadow-none scale-105' : ($vStock <= 0 ? 'bg-gray-50 dark:bg-gray-850 text-gray-400 border-gray-250 dark:border-gray-750 cursor-not-allowed opacity-50' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-indigo-600') }}"
                                                >
                                                    {{ $size }}
                                                    @if($vStock <= 0)
                                                        <span class="absolute -top-1 -right-1 bg-rose-600 w-1.5 h-1.5 rounded-full"></span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Dynamic stock count for variant -->
                                    <div class="text-xs bg-transparent pt-1">
                                        @if($currentVariant)
                                            @if($variantStock > 0)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 dark:text-emerald-400 font-extrabold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    ✓ Stok Tersedia: {{ $variantStock }} unit
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-rose-800 bg-rose-50/50 dark:bg-rose-950/20 dark:text-rose-450 font-extrabold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                                    ✗ Stok Varian Habis!
                                                </span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-amber-800 bg-amber-50/50 dark:bg-amber-950/20 dark:text-amber-400 font-extrabold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-bounce"></span>
                                                Silakan pilih kombinasi warna dan ukuran.
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Product Description panel -->
                            <div class="mb-5">
                                <h3 class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Deskripsi Produk</h3>
                                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-300 leading-relaxed max-h-[120px] overflow-y-auto pr-2">
                                    {{ $product->description }}
                                </p>
                            </div>
                        </div>

                        <!-- Buy Actions Row -->
                        <div>
                            <!-- Delivery Estimate Info -->
                            <div class="flex items-center gap-2 mb-4 bg-gray-50 dark:bg-gray-900/40 p-3 rounded-2xl text-[10px] sm:text-xs font-medium text-gray-600 dark:text-gray-400 border border-gray-100 dark:border-gray-800">
                                <span>Est. pengiriman tiba dalam 2-4 hari kerja (JNE / J&T / Sicepat).</span>
                            </div>

                            <!-- Action Buttons: Add to Cart & Buy Now -->
                            @php
                                $selectedV = $this->selectedVariant;
                                $canAddToCart = $selectedV && $selectedV->stock > 0;
                            @endphp
                            <div class="flex gap-3">
                                <button 
                                    wire:click="addToCart({{ $product->id }}, {{ $selectedV?->id }})" 
                                    {{ !$canAddToCart ? 'disabled' : '' }} 
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold border-2 transition duration-150 {{ $canAddToCart ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-950/40 hover:scale-[1.01]' : 'border-gray-200 dark:border-gray-700 bg-gray-150 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}"
                                >
                                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    <span>+ Keranjang</span>
                                </button>
                                
                                <button 
                                    wire:click="buyNow({{ $product->id }}, {{ $selectedV?->id }})" 
                                    {{ !$canAddToCart ? 'disabled' : '' }} 
                                    class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition duration-150 {{ $canAddToCart ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-200 dark:shadow-none hover:scale-[1.01]' : 'bg-gray-150 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}"
                                >
                                    <span>Beli Langsung</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Customer Reviews -->
                <div class="border-t border-gray-100 dark:border-gray-700 p-6 sm:p-8 bg-gray-50/30 dark:bg-gray-900/40">
                    <h3 class="text-base sm:text-lg font-black text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                        💬 Ulasan Pembeli <span class="bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 text-xs px-2.5 py-0.5 rounded-full font-bold">{{ $product->reviews->count() }}</span>
                    </h3>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Left Column: Statistics & Write Review Form -->
                        <div class="space-y-6">
                            <!-- Ratings Summary -->
                            <div class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm flex items-center gap-4">
                                <div class="text-center shrink-0">
                                    <div class="text-4xl font-black text-gray-900 dark:text-white leading-none mb-1">{{ $product->average_rating }}</div>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider">dari 5 bintang</span>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center text-amber-400 mb-1.5">
                                        @for($i = 1; $i <= 5; $i++)
                                            <span class="text-lg">{{ $i <= round($product->average_rating) ? '★' : '☆' }}</span>
                                        @endfor
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 font-medium">95% pembeli sangat puas dengan kenyamanan produk ini.</p>
                                </div>
                            </div>


                        </div>
                        
                        <!-- Right Columns: Reviews List -->
                        <div class="lg:col-span-2 space-y-4 max-h-[380px] overflow-y-auto pr-2 scrollbar-thin">
                            @forelse($product->reviews as $rev)
                                <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-3 hover:shadow-md transition duration-200">
                                    <div class="flex items-center gap-3">
                                        <!-- User Avatar Bubble -->
                                        <div class="w-9 h-9 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-extrabold text-xs uppercase border border-indigo-100 dark:border-indigo-900/50">
                                            {{ substr($rev->user->name, 0, 2) }}
                                        </div>
                                        <!-- User Name and Rating -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $rev->user->name }}</span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $rev->created_at->format('d M Y') }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <!-- Stars rating -->
                                                <div class="flex text-amber-400 text-xs">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        <span>{{ $i <= $rev->rating ? '★' : '☆' }}</span>
                                                    @endfor
                                                </div>
                                                <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/40 px-1.5 py-0.5 rounded-md flex items-center gap-0.5 shrink-0">
                                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                                    Pembeli Terverifikasi
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pl-12">
                                        @if($rev->comment)
                                            <p class="text-xs text-gray-650 dark:text-gray-300 leading-relaxed">{{ $rev->comment }}</p>
                                        @else
                                            <p class="text-xs italic text-gray-400 dark:text-gray-500 font-medium">Pembeli tidak memberikan komentar.</p>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12 bg-white dark:bg-gray-900/25 rounded-3xl border border-dashed border-gray-200 dark:border-gray-700">
                                    <div class="text-3xl mb-2">💬</div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Belum ada ulasan untuk produk ini. Jadilah yang pertama memberikan ulasan!</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

