<?php

use Livewire\Volt\Component;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

new class extends Component
{
    #[On('cart-updated')]
    public function refresh(): void
    {
        // Livewire will automatically reload the properties.
    }

    public function getItemsProperty()
    {
        if (!Auth::check()) {
            return collect();
        }

        return CartItem::with('product')
            ->where('user_id', Auth::id())
            ->get();
    }

    public function getTotalProperty(): float
    {
        return $this->items->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });
    }

    public function increment(int $itemId): void
    {
        $item = CartItem::findOrFail($itemId);
        
        if ($item->quantity + 1 > $item->product->stock) {
            $this->dispatch('notify', type: 'error', message: 'Tidak dapat menambah lebih banyak. Stok tidak mencukupi!');
            return;
        }

        $item->increment('quantity');
        $this->dispatch('cart-updated');
    }

    public function decrement(int $itemId): void
    {
        $item = CartItem::findOrFail($itemId);

        if ($item->quantity <= 1) {
            $item->delete();
        } else {
            $item->decrement('quantity');
        }

        $this->dispatch('cart-updated');
    }

    public function remove(int $itemId): void
    {
        $item = CartItem::findOrFail($itemId);
        $item->delete();
        $this->dispatch('cart-updated');
    }
};
?>

<div 
    x-show="cartOpen" 
    class="fixed inset-0 z-50 overflow-hidden" 
    aria-labelledby="slide-over-title" 
    role="dialog" 
    aria-modal="true"
    style="display: none;"
>
    <div class="absolute inset-0 overflow-hidden">
        <!-- Overlay -->
        <div 
            x-show="cartOpen"
            x-transition:enter="ease-in-out duration-500"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in-out duration-500"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-gray-500/75 dark:bg-gray-900/80 backdrop-blur-sm transition-opacity" 
            @click="cartOpen = false"
        ></div>

        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <!-- Slide-over panel -->
            <div 
                x-show="cartOpen"
                x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                class="pointer-events-auto w-screen max-w-md"
            >
                <div class="flex h-full flex-col bg-white dark:bg-gray-800 shadow-2xl border-l border-gray-100 dark:border-gray-700">
                    <!-- Drawer Header -->
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-gray-700">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100" id="slide-over-title">
                            Keranjang Belanja
                        </h2>
                        <button 
                            type="button" 
                            class="rounded-xl p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition" 
                            @click="cartOpen = false"
                        >
                            <span class="sr-only">Tutup panel</span>
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Item List -->
                    <div class="flex-1 overflow-y-auto px-6 py-4">
                        @if($this->items->isEmpty())
                            <div class="h-full flex flex-col items-center justify-center text-center">
                                <div class="w-20 h-20 rounded-full bg-gray-50 dark:bg-gray-700 flex items-center justify-center mb-4 border border-dashed border-gray-200 dark:border-gray-600">
                                    <svg class="h-10 w-10 text-gray-450 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                </div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Keranjang Anda kosong</h3>
                                <p class="text-sm text-gray-500 mt-1">Mulai tambahkan sepatu premium ke keranjang Anda!</p>
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach($this->items as $item)
                                    <div class="flex items-center gap-4 bg-gray-50 dark:bg-gray-700/50 p-4 rounded-2xl border border-gray-100 dark:border-gray-700">
                                        <!-- Product Thumbnail -->
                                        <div class="w-20 h-20 shrink-0 bg-gradient-to-br from-indigo-50 to-pink-50 dark:from-gray-700 dark:to-gray-800 rounded-xl overflow-hidden relative border border-gray-100 dark:border-gray-700">
                                            @if($item->product->image_path)
                                                <img src="{{ asset('storage/' . $item->product->image_path) }}" alt="{{ $item->product->name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="absolute inset-0 flex items-center justify-center text-indigo-500">
                                                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Details -->
                                        <div class="flex-1 min-w-0">
                                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">
                                                {{ $item->product->name }}
                                            </h3>
                                            <p class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold uppercase tracking-wider">
                                                {{ $item->product->category->name }}
                                            </p>
                                            <span class="text-sm font-extrabold text-gray-900 dark:text-gray-100 block mt-1">
                                                Rp {{ number_format($item->product->price, 0, ',', '.') }}
                                            </span>

                                            <!-- Qty Controls -->
                                            <div class="flex items-center gap-2 mt-2">
                                                <button 
                                                    wire:click="decrement({{ $item->id }})" 
                                                    class="p-1 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition"
                                                >
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                                </button>
                                                <span class="text-xs font-bold text-gray-800 dark:text-gray-200 px-1">
                                                    {{ $item->quantity }}
                                                </span>
                                                <button 
                                                    wire:click="increment({{ $item->id }})" 
                                                    class="p-1 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition"
                                                >
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Remove Button -->
                                        <button 
                                            wire:click="remove({{ $item->id }})" 
                                            class="p-2 rounded-xl text-gray-400 hover:text-rose-600 dark:hover:text-rose-450 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition shrink-0"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Footer / Summary -->
                    @if(!$this->items->isEmpty())
                        <div class="border-t border-gray-100 dark:border-gray-700 px-6 py-6 bg-gray-50 dark:bg-gray-700/30">
                            <div class="flex justify-between text-base font-bold text-gray-900 dark:text-gray-100 mb-4">
                                <span>Subtotal</span>
                                <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                Biaya pengiriman dan diskon akan dihitung saat checkout.
                            </p>
                            <a 
                                href="{{ url('/cart') }}" 
                                @click="cartOpen = false"
                                class="w-full flex items-center justify-center px-6 py-3.5 border border-transparent text-sm font-semibold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 dark:shadow-none"
                                wire:navigate
                            >
                                Lanjut ke Pembayaran
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
