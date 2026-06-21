<?php

use Livewire\Volt\Component;
use App\Models\CartItem;
use App\Models\Province;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\RajaOngkirService;
use App\Services\MidtransService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public string $recipientName = '';
    public string $phoneNumber = '';
    public string $addressLine = '';
    public ?int $provinceId = null;
    public ?int $cityId = null;
    public ?string $courier = null;
    public ?string $selectedService = null;
    
    public array $shippingServices = [];
    public float $shippingCost = 0;

    protected $listeners = ['cart-updated' => '$refresh'];

    public function mount(): void
    {
        $this->recipientName = Auth::user()->name;
        $this->phoneNumber = Auth::user()->phone ?? '';
    }

    public function getItemsProperty()
    {
        return CartItem::with('product')
            ->where('user_id', Auth::id())
            ->get();
    }

    public function getSubtotalProperty(): float
    {
        return $this->items->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });
    }

    public function getTotalWeightProperty(): int
    {
        return $this->items->sum(function ($item) {
            return $item->product->weight * $item->quantity;
        });
    }

    public function getTotalProperty(): float
    {
        return $this->subtotal + $this->shippingCost;
    }

    public function updatedProvinceId(): void
    {
        $this->cityId = null;
        $this->courier = null;
        $this->selectedService = null;
        $this->shippingServices = [];
        $this->shippingCost = 0;
    }

    public function updatedCityId(): void
    {
        $this->courier = null;
        $this->selectedService = null;
        $this->shippingServices = [];
        $this->shippingCost = 0;
    }

    public function updatedCourier(): void
    {
        $this->selectedService = null;
        $this->shippingCost = 0;
        $this->fetchShippingRates();
    }

    public function fetchShippingRates(): void
    {
        if (!$this->cityId || !$this->courier || $this->items->isEmpty()) {
            return;
        }

        $rajaOngkir = new RajaOngkirService();
        $this->shippingServices = $rajaOngkir->calculateCost(
            $this->cityId,
            $this->totalWeight,
            $this->courier
        );
    }

    public function selectService(string $serviceCode, float $cost): void
    {
        $this->selectedService = $serviceCode;
        $this->shippingCost = $cost;
    }

    public function increment(int $itemId): void
    {
        $item = CartItem::findOrFail($itemId);
        if ($item->quantity + 1 > $item->product->stock) {
            $this->dispatch('notify', type: 'error', message: 'Cannot add more. Insufficient stock!');
            return;
        }
        $item->increment('quantity');
        $this->dispatch('cart-updated');
        $this->fetchShippingRates();
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
        $this->fetchShippingRates();
    }

    public function remove(int $itemId): void
    {
        $item = CartItem::findOrFail($itemId);
        $item->delete();
        $this->dispatch('cart-updated');
        $this->fetchShippingRates();
    }

    public function placeOrder(MidtransService $midtransService): void
    {
        $this->validate([
            'recipientName' => 'required|string|max:255',
            'phoneNumber' => 'required|string|max:20',
            'addressLine' => 'required|string',
            'provinceId' => 'required|integer',
            'cityId' => 'required|integer',
            'courier' => 'required|string',
            'selectedService' => 'required|string',
        ]);

        $cartItems = $this->items;
        if ($cartItems->isEmpty()) {
            $this->dispatch('notify', type: 'error', message: 'Your cart is empty!');
            return;
        }

        // Verify stock for all items
        foreach ($cartItems as $item) {
            if ($item->product->stock < $item->quantity) {
                $this->dispatch('notify', type: 'error', message: "Insufficient stock for {$item->product->name}!");
                return;
            }
        }

        try {
            $order = DB::transaction(function () use ($cartItems, $midtransService) {
                $province = Province::findOrFail($this->provinceId);
                $city = City::findOrFail($this->cityId);

                // Create base order record
                $order = Order::create([
                    'user_id' => Auth::id(),
                    'order_number' => 'TEMP-' . uniqid(),
                    'subtotal_amount' => $this->subtotal,
                    'shipping_cost' => $this->shippingCost,
                    'total_amount' => $this->total,
                    'shipping_courier' => $this->courier,
                    'shipping_service' => $this->selectedService,
                    'status' => 'pending',
                    'shipping_recipient_name' => $this->recipientName,
                    'shipping_phone_number' => $this->phoneNumber,
                    'shipping_address_line' => $this->addressLine,
                    'shipping_province' => $province->name,
                    'shipping_city' => $city->name,
                    'shipping_postal_code' => $city->postal_code,
                ]);

                // Create sequential order invoice
                $order->update([
                    'order_number' => 'INV/' . date('Ymd') . '/' . $order->id,
                ]);

                // Create order items & decrement stock
                foreach ($cartItems as $item) {
                    $item->product->decrement('stock', $item->quantity);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'name' => $item->product->name,
                        'price' => $item->product->price,
                        'quantity' => $item->quantity,
                    ]);
                }

                // Delete cart items
                CartItem::where('user_id', Auth::id())->delete();

                // Fetch Midtrans Snap Token
                $snapToken = $midtransService->getSnapToken(
                    $order->order_number,
                    $order->total_amount,
                    [
                        'name' => $this->recipientName,
                        'email' => Auth::user()->email,
                        'phone' => $this->phoneNumber,
                    ]
                );

                Payment::create([
                    'order_id' => $order->id,
                    'gross_amount' => $order->total_amount,
                    'status' => 'pending',
                    'snap_token' => $snapToken,
                ]);

                return $order;
            });

            $payment = $order->payment;
            $this->dispatch('cart-updated');
            
            // Dispatch event to show payment modal
            $this->dispatch('pay-order', [
                'snapToken' => $payment->snap_token,
                'orderId' => $order->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Order placement failed: ' . $e->getMessage());
            $this->dispatch('notify', type: 'error', message: 'Failed to place order. Please try again.');
        }
    }

    public function with(): array
    {
        return [
            'provinces' => Province::orderBy('name')->get(),
            'cities' => $this->provinceId ? City::where('province_id', $this->provinceId)->orderBy('name')->get() : collect(),
        ];
    }
};
?>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 mb-8 tracking-tight">Checkout</h1>

        @if($this->items->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-12 text-center shadow-sm border border-gray-100 dark:border-gray-700 max-w-lg mx-auto">
                <div class="w-20 h-20 rounded-full bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center mb-6 mx-auto">
                    <svg class="h-10 w-10 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Your checkout cart is empty</h3>
                <p class="text-sm text-gray-500 mb-6">Select from our collection of premium footwear before finalizing checkout.</p>
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-sm font-semibold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-md shadow-indigo-150 dark:shadow-none" wire:navigate>
                    Browse Shoes
                </a>
            </div>
        @else
            <div class="lg:grid lg:grid-cols-3 lg:gap-8">
                <!-- Cart Items and Shipping Form -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Items Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-6">1. Review Items</h2>
                        <div class="space-y-4">
                            @foreach($this->items as $item)
                                <div class="flex items-center gap-4 py-4 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-750' : '' }}">
                                    <!-- Thumbnail -->
                                    <div class="w-20 h-20 shrink-0 bg-gradient-to-br from-indigo-50 to-pink-50 dark:from-gray-700 dark:to-gray-750 rounded-2xl overflow-hidden relative border border-gray-100 dark:border-gray-750">
                                        @if($item->product->image_path)
                                            <img src="{{ asset('storage/' . $item->product->image_path) }}" alt="{{ $item->product->name }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="absolute inset-0 flex items-center justify-center text-indigo-500">
                                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Description -->
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate">
                                            {{ $item->product->name }}
                                        </h3>
                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold uppercase tracking-wider">
                                            {{ $item->product->category->name }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-2">
                                            <button wire:click="decrement({{ $item->id }})" class="p-1 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-500 hover:text-gray-700 transition">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                            </button>
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-200 px-1">{{ $item->quantity }}</span>
                                            <button wire:click="increment({{ $item->id }})" class="p-1 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-500 hover:text-gray-700 transition">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Price / Remove -->
                                    <div class="text-right">
                                        <span class="text-sm font-extrabold text-gray-900 dark:text-gray-100 block">
                                            Rp {{ number_format($item->product->price * $item->quantity, 0, ',', '.') }}
                                        </span>
                                        <button wire:click="remove({{ $item->id }})" class="text-xs text-gray-400 hover:text-rose-600 transition mt-2">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Shipping Address Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 space-y-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 border-b border-gray-50 dark:border-gray-750 pb-4">2. Shipping Details</h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Recipient Name -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-450 uppercase tracking-wider mb-2">Recipient Name</label>
                                <input wire:model="recipientName" type="text" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('recipientName') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- Phone Number -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Phone Number</label>
                                <input wire:model="phoneNumber" type="text" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @error('phoneNumber') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Address Line -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Full Address</label>
                            <textarea wire:model="addressLine" rows="3" placeholder="Street Name, Building/Unit, Landmark" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"></textarea>
                            @error('addressLine') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Province Selector -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Province</label>
                                <select wire:model.live="provinceId" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">Select Province</option>
                                    @foreach($provinces as $prov)
                                        <option value="{{ $prov->id }}">{{ $prov->name }}</option>
                                    @endforeach
                                </select>
                                @error('provinceId') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <!-- City Selector -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">City</label>
                                <select wire:model.live="cityId" {{ !$provinceId ? 'disabled' : '' }} class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100 dark:disabled:bg-gray-800 disabled:cursor-not-allowed">
                                    <option value="">Select City</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }} ({{ $city->type }})</option>
                                    @endforeach
                                </select>
                                @error('cityId') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Courier Selection Card Grid -->
                        <div x-data="{ selected: @entangle('courier') }">
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-3">Select Courier</label>
                            <div class="grid grid-cols-3 gap-4">
                                <!-- JNE -->
                                <div 
                                    @click="if({{ $cityId ? 'true' : 'false' }}) { selected = 'jne'; $wire.set('courier', 'jne') }"
                                    :class="selected === 'jne' ? 'border-indigo-600 ring-2 ring-indigo-600/20 bg-indigo-50/50 dark:bg-indigo-950/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                                    class="border p-4 rounded-2xl flex flex-col items-center justify-center text-center cursor-pointer transition {{ !$cityId ? 'opacity-50 cursor-not-allowed' : '' }}"
                                >
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">JNE</span>
                                    <span class="text-xxs text-gray-500 mt-1">Jalur Nugraha Ekakurir</span>
                                </div>
                                <!-- POS -->
                                <div 
                                    @click="if({{ $cityId ? 'true' : 'false' }}) { selected = 'pos'; $wire.set('courier', 'pos') }"
                                    :class="selected === 'pos' ? 'border-indigo-600 ring-2 ring-indigo-600/20 bg-indigo-50/50 dark:bg-indigo-950/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                                    class="border p-4 rounded-2xl flex flex-col items-center justify-center text-center cursor-pointer transition {{ !$cityId ? 'opacity-50 cursor-not-allowed' : '' }}"
                                >
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">POS</span>
                                    <span class="text-xxs text-gray-500 mt-1">Pos Indonesia</span>
                                </div>
                                <!-- TIKI -->
                                <div 
                                    @click="if({{ $cityId ? 'true' : 'false' }}) { selected = 'tiki'; $wire.set('courier', 'tiki') }"
                                    :class="selected === 'tiki' ? 'border-indigo-600 ring-2 ring-indigo-600/20 bg-indigo-50/50 dark:bg-indigo-950/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                                    class="border p-4 rounded-2xl flex flex-col items-center justify-center text-center cursor-pointer transition {{ !$cityId ? 'opacity-50 cursor-not-allowed' : '' }}"
                                >
                                    <span class="text-sm font-bold text-gray-900 dark:text-gray-100">TIKI</span>
                                    <span class="text-xxs text-gray-500 mt-1">Titipan Kilat</span>
                                </div>
                            </div>
                            @error('courier') <span class="text-rose-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Shipping Service Choices -->
                        @if(!empty($shippingServices))
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-3">Available Services</label>
                                <div class="space-y-2">
                                    @foreach($shippingServices as $srv)
                                        <div 
                                            wire:click="selectService('{{ $srv['service'] }}', {{ $srv['cost'] }})"
                                            class="flex items-center justify-between p-4 rounded-2xl border cursor-pointer transition {{ $selectedService === $srv['service'] ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/20 ring-2 ring-indigo-600/20' : 'border-gray-100 dark:border-gray-750 hover:bg-gray-50 dark:hover:bg-gray-750/30' }}"
                                        >
                                            <div>
                                                <span class="text-sm font-extrabold text-gray-900 dark:text-gray-100 uppercase">{{ $srv['service'] }}</span>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $srv['description'] }} - {{ $srv['etd'] }} days</p>
                                            </div>
                                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                Rp {{ number_format($srv['cost'], 0, ',', '.') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                                @error('selectedService') <span class="text-rose-500 text-xs mt-2 block">{{ $message }}</span> @enderror
                            </div>
                        @elseif($courier && empty($shippingServices))
                            <div class="p-4 bg-amber-50 dark:bg-amber-950/20 text-amber-800 dark:text-amber-300 rounded-2xl text-xs font-medium border border-amber-100 dark:border-amber-900/30">
                                Loading shipping services... Make sure the destination city is selected.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Sticky Summary Receipt -->
                <div class="lg:col-span-1 mt-8 lg:mt-0">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 sticky top-6 space-y-6">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 border-b border-gray-50 dark:border-gray-750 pb-4">Order Summary</h2>

                        <div class="space-y-3">
                            <div class="flex justify-between text-sm text-gray-500">
                                <span>Subtotal</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-500">
                                <span>Total Weight</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($this->totalWeight) }} grams</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-500">
                                <span>Shipping Cost</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $shippingCost > 0 ? 'Rp ' . number_format($shippingCost, 0, ',', '.') : 'Choose Service' }}
                                </span>
                            </div>
                            <div class="border-t border-gray-100 dark:border-gray-750 pt-4 flex justify-between text-base font-extrabold text-gray-900 dark:text-gray-100">
                                <span>Total Amount</span>
                                <span>Rp {{ number_format($this->total, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        <button 
                            wire:click="placeOrder" 
                            class="w-full flex items-center justify-center px-6 py-4 border border-transparent text-sm font-semibold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-lg shadow-indigo-150 dark:shadow-none"
                        >
                            Proceed to Payment
                        </button>

                        <div class="flex items-center gap-2 justify-center text-xs text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Secure Checkout powered by Midtrans
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Midtrans Snap Embedded JavaScript & Event Listener -->
    <script 
        src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" 
        data-client-key="{{ config('services.midtrans.client_key') }}"
    ></script>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('pay-order', (eventData) => {
                const data = eventData[0];
                const snapToken = data.snapToken;
                const orderId = data.orderId;

                if (!snapToken || snapToken.startsWith('mock-snap-token')) {
                    alert('Mock payment token generated. Redirecting to Order Status Polling page...');
                    window.location.href = '/order/' + orderId;
                    return;
                }

                snap.pay(snapToken, {
                    onSuccess: function(result) {
                        window.location.href = '/order/' + orderId;
                    },
                    onPending: function(result) {
                        window.location.href = '/order/' + orderId;
                    },
                    onError: function(result) {
                        alert("Payment failed!");
                    },
                    onClose: function() {
                        alert('You closed the payment popup without finishing payment.');
                    }
                });
            });
        });
    </script>

    <!-- Toast alerts -->
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
</div>
