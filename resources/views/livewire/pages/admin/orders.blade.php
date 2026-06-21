<?php

use Livewire\Volt\Component;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public ?int $selectedOrderId = null;
    public string $trackingNumber = '';
    public string $orderStatus = '';

    public function mount(): void
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
    }

    public function selectOrder(int $orderId): void
    {
        $this->selectedOrderId = $orderId;
        $order = Order::findOrFail($orderId);
        $this->trackingNumber = $order->tracking_number ?? '';
        $this->orderStatus = $order->status;
    }

    public function closeDetails(): void
    {
        $this->selectedOrderId = null;
        $this->trackingNumber = '';
        $this->orderStatus = '';
    }

    public function updateOrder(): void
    {
        if (!$this->selectedOrderId) {
            return;
        }

        $order = Order::findOrFail($this->selectedOrderId);
        
        $oldStatus = $order->status;
        $newStatus = $this->orderStatus;

        $order->update([
            'tracking_number' => $this->trackingNumber !== '' ? $this->trackingNumber : null,
            'status' => $newStatus,
        ]);

        // Auto-cancel payment and restore stock if cancelled
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $payment = $order->payment;
            if ($payment && $payment->status !== 'settlement') {
                $payment->update(['status' => 'cancel']);
            }
            // Restore stock
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
        }

        $this->dispatch('notify', type: 'success', message: 'Order updated successfully!');
        $this->closeDetails();
    }

    public function getOrdersProperty()
    {
        return Order::with(['user', 'payment', 'items'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
};
?>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">Order Management</h1>
                <p class="text-sm text-gray-550 dark:text-gray-400 mt-1">Review orders, manage fulfillment tracking, and update status cycles.</p>
            </div>
        </div>

        <div class="lg:grid lg:grid-cols-3 lg:gap-8">
            <!-- Orders List -->
            <div class="{{ $selectedOrderId ? 'lg:col-span-2' : 'lg:col-span-3' }} transition-all duration-300">
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-750/30">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Invoice</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Courier</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Payment</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @if($this->orders->isEmpty())
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            No transactions placed yet.
                                        </td>
                                    </tr>
                                @else
                                    @foreach($this->orders as $o)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-750/10 transition cursor-pointer" wire:click="selectOrder({{ $o->id }})">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-gray-100">
                                                {{ $o->order_number }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $o->shipping_recipient_name }}</div>
                                                <div class="text-xs text-gray-500">{{ $o->user?->email }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 uppercase">
                                                {{ $o->shipping_courier }} - {{ $o->shipping_service }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-extrabold text-gray-900 dark:text-gray-100">
                                                Rp {{ number_format($o->total_amount, 0, ',', '.') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider
                                                    {{ $o->status === 'completed' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : '' }}
                                                    {{ $o->status === 'pending' ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-300' : '' }}
                                                    {{ $o->status === 'processing' ? 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-300' : '' }}
                                                    {{ $o->status === 'shipping' ? 'bg-indigo-50 text-indigo-805 dark:bg-indigo-950 dark:text-indigo-305' : '' }}
                                                    {{ $o->status === 'cancelled' ? 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : '' }}
                                                ">
                                                    {{ $o->status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider
                                                    {{ $o->payment?->status === 'settlement' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : '' }}
                                                    {{ $o->payment?->status === 'pending' ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-300' : '' }}
                                                    {{ in_array($o->payment?->status, ['expire', 'cancel']) ? 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : '' }}
                                                ">
                                                    {{ $o->payment?->status ?? 'pending' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold">
                                                <button class="text-indigo-600 hover:text-indigo-900 transition dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    Fulfill
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Fulfill Details Sidebar/Card -->
            @if($selectedOrderId)
                @php
                    $detailOrder = App\Models\Order::with(['user', 'payment', 'items'])->find($selectedOrderId);
                @endphp
                @if($detailOrder)
                    <div class="lg:col-span-1 mt-8 lg:mt-0">
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 sticky top-6 space-y-6">
                            <div class="flex items-center justify-between border-b border-gray-50 dark:border-gray-750 pb-4">
                                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Fulfillment details</h2>
                                <button wire:click="closeDetails" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Invoice and Info -->
                            <div class="space-y-1">
                                <span class="text-xs text-gray-400 block">Order Number</span>
                                <span class="text-base font-extrabold text-gray-900 dark:text-gray-100">{{ $detailOrder->order_number }}</span>
                                <p class="text-xs text-gray-500">Customer: {{ $detailOrder->shipping_recipient_name }} ({{ $detailOrder->shipping_phone_number }})</p>
                                <p class="text-xs text-gray-550">{{ $detailOrder->shipping_address_line }}, {{ $detailOrder->shipping_city }}, {{ $detailOrder->shipping_province }}</p>
                            </div>

                            <!-- Items -->
                            <div class="bg-gray-50 dark:bg-gray-750/30 p-4 rounded-2xl border border-gray-100 dark:border-gray-750/50 space-y-3">
                                <h3 class="text-xs font-bold text-gray-600 dark:text-gray-450 uppercase tracking-wider">Items</h3>
                                @foreach($detailOrder->items as $i)
                                    <div class="flex justify-between text-xs text-gray-900 dark:text-gray-100">
                                        <span class="font-medium">{{ $i->name }} (x{{ $i->quantity }})</span>
                                        <span class="font-bold">Rp {{ number_format($i->price * $i->quantity, 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                                <div class="border-t border-gray-200 dark:border-gray-600 pt-2 flex justify-between text-xs font-bold text-gray-900 dark:text-gray-100">
                                    <span>Total Value</span>
                                    <span>Rp {{ number_format($detailOrder->total_amount, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <!-- Form updates -->
                            <div class="space-y-4">
                                <!-- Order Status -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-450 uppercase tracking-wider mb-2">Fulfillment Status</label>
                                    <select wire:model="orderStatus" class="w-full border-gray-300 dark:border-gray-650 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="pending">Pending Payment</option>
                                        <option value="processing">Processing (Preparing)</option>
                                        <option value="shipping">Shipping (Dispatched)</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>

                                <!-- Tracking Number -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-450 uppercase tracking-wider mb-2">Tracking Number / Receipt</label>
                                    <input 
                                        wire:model="trackingNumber" 
                                        type="text" 
                                        placeholder="e.g. JNE123456789"
                                        class="w-full border-gray-300 dark:border-gray-650 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    >
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button wire:click="closeDetails" class="w-1/2 px-4 py-3 bg-gray-100 hover:bg-gray-250 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold text-sm rounded-xl transition">
                                    Cancel
                                </button>
                                <button wire:click="updateOrder" class="w-1/2 px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-xl transition shadow">
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

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
