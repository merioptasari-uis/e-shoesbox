<?php

use Livewire\Volt\Component;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        // Guard access: only the owner or an admin can view this page
        if ($order->user_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $this->order = $order;
        $this->checkPaymentStatus();
    }

    public function checkPaymentStatus(): void
    {
        $payment = $this->order->payment;
        if (!$payment || $payment->status === 'settlement' || $payment->status === 'expire') {
            return;
        }

        $serverKey = (string) config('services.midtrans.server_key', '');
        if (empty($serverKey) || str_contains($serverKey, 'key_here') || str_starts_with($serverKey, 'AIzaSy')) {
            return;
        }

        try {
            $isProd = (bool) config('services.midtrans.is_production', false);
            $url = $isProd
                ? "https://api.midtrans.com/v2/{$this->order->order_number}/status"
                : "https://api.sandbox.midtrans.com/v2/{$this->order->order_number}/status";

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withBasicAuth($serverKey, '')
            ->get($url);

            if ($response->successful()) {
                $status = $response->json('transaction_status');
                $paymentType = $response->json('payment_type');
                $transactionId = $response->json('transaction_id');

                $mappedStatus = match ($status) {
                    'settlement', 'capture' => 'settlement',
                    'pending' => 'pending',
                    'deny', 'cancel' => 'cancel',
                    'expire' => 'expire',
                    default => $payment->status,
                };

                $payment->update([
                    'status' => $mappedStatus,
                    'payment_type' => $paymentType,
                    'transaction_id' => $transactionId,
                    'payment_payload' => $response->json(),
                ]);

                if ($mappedStatus === 'settlement' && $this->order->status === 'pending') {
                    $this->order->update(['status' => 'processing']);
                } elseif (in_array($mappedStatus, ['cancel', 'expire']) && $this->order->status === 'pending') {
                    $this->order->update(['status' => 'cancelled']);
                    // Restore product stock
                    foreach ($this->order->items as $item) {
                        if ($item->product) {
                            $item->product->increment('stock', $item->quantity);
                        }
                    }
                }

                $this->order->load('payment');
            }
        } catch (\Exception $e) {
            Log::error('Midtrans status poll exception: ' . $e->getMessage());
        }
    }

    public function simulateSettlement(): void
    {
        $payment = $this->order->payment;
        if ($payment && $payment->status !== 'settlement') {
            $payment->update([
                'status' => 'settlement',
                'payment_type' => 'bank_transfer',
                'transaction_id' => 'simulated-tx-' . uniqid(),
            ]);

            if ($this->order->status === 'pending') {
                $this->order->update(['status' => 'processing']);
            }

            $this->order->load('payment');
            $this->dispatch('notify', type: 'success', message: 'Simulated payment settlement successfully!');
        }
    }
};
?>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen" wire:poll.5s="checkPaymentStatus">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Order Header -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="text-xs font-semibold text-indigo-650 dark:text-indigo-400 uppercase tracking-widest block mb-1">Invoice Details</span>
                <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                    {{ $order->order_number }}
                </h1>
                <p class="text-xs text-gray-500 mt-1">Placed on {{ $order->created_at->format('d M Y H:i') }}</p>
            </div>

            <div>
                <!-- Status Badge -->
                <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider
                    {{ $order->status === 'completed' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-900/30' : '' }}
                    {{ $order->status === 'pending' ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-300 border border-amber-100 dark:border-amber-900/30' : '' }}
                    {{ $order->status === 'processing' ? 'bg-blue-50 text-blue-805 dark:bg-blue-950 dark:text-blue-300 border border-blue-100 dark:border-blue-900/30' : '' }}
                    {{ $order->status === 'shipping' ? 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900/30' : '' }}
                    {{ $order->status === 'cancelled' ? 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-100 dark:border-rose-900/30' : '' }}
                ">
                    {{ $order->status }}
                </span>
            </div>
        </div>

        <!-- Simulation Banner for Local Developers -->
        @if($order->status === 'pending')
            <div class="bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/30 rounded-3xl p-6 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider block mb-1">Developer Sandbox Tools</span>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Simulate Payment Completion</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Use this button to simulate a successful settlement from Midtrans.</p>
                </div>
                <button wire:click="simulateSettlement" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow">
                    Settle Payment
                </button>
            </div>
        @endif

        <div class="space-y-6">
            <!-- Items ordered -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">Items Ordered</h2>
                <div class="space-y-4">
                    @foreach($order->items as $item)
                        <div class="flex items-center gap-4 py-2 {{ !$loop->last ? 'border-b border-gray-50 dark:border-gray-750' : '' }}">
                            <div class="flex-1">
                                <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $item->name }}</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Qty: {{ $item->quantity }} x Rp {{ number_format($item->price, 0, ',', '.') }}</p>
                            </div>
                            <span class="text-sm font-extrabold text-gray-900 dark:text-gray-100">
                                Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Shipping address details -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">Delivery Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Recipient Name & Contact</span>
                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ $order->shipping_recipient_name }}</p>
                        <p class="text-gray-500 mt-0.5">{{ $order->shipping_phone_number }}</p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Shipping Destination</span>
                        <p class="font-semibold text-gray-950 dark:text-gray-100">{{ $order->shipping_address_line }}</p>
                        <p class="text-gray-500 mt-0.5">{{ $order->shipping_city }}, {{ $order->shipping_province }} {{ $order->shipping_postal_code }}</p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Courier Method</span>
                        <p class="font-extrabold text-gray-900 dark:text-gray-105 uppercase">{{ $order->shipping_courier }} - {{ $order->shipping_service }}</p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Tracking Number</span>
                        @if($order->tracking_number)
                            <p class="font-extrabold text-indigo-650 dark:text-indigo-400 uppercase">{{ $order->tracking_number }}</p>
                        @else
                            <p class="text-gray-400 italic">Not shipped yet</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">Payment Summary</h2>
                
                <div class="space-y-3 mb-6 border-b border-gray-100 dark:border-gray-750 pb-4">
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>Items Subtotal</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-105">Rp {{ number_format($order->subtotal_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>Shipping Cost</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-105">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-base font-extrabold text-gray-900 dark:text-gray-100 pt-2">
                        <span>Total Paid</span>
                        <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm bg-gray-50 dark:bg-gray-750/30 p-4 rounded-2xl border border-gray-100 dark:border-gray-750">
                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Payment Method</span>
                        <p class="font-bold text-gray-900 dark:text-gray-100 uppercase">
                            {{ $order->payment?->payment_type ? str_replace('_', ' ', $order->payment->payment_type) : 'Midtrans gateway' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Payment Status</span>
                        <span class="font-extrabold uppercase
                            {{ $order->payment?->status === 'settlement' ? 'text-emerald-600 dark:text-emerald-400' : '' }}
                            {{ $order->payment?->status === 'pending' ? 'text-amber-500 dark:text-amber-400' : '' }}
                            {{ in_array($order->payment?->status, ['expire', 'cancel']) ? 'text-rose-600 dark:text-rose-400' : '' }}
                        ">
                            {{ $order->payment?->status ?? 'pending' }}
                        </span>
                    </div>
                </div>

                <!-- Pay Button (if payment is still pending) -->
                @if($order->status === 'pending' && $order->payment?->status === 'pending' && $order->payment?->snap_token)
                    <div class="mt-6">
                        <button 
                            id="pay-button"
                            class="w-full flex items-center justify-center px-6 py-4 border border-transparent text-sm font-semibold rounded-2xl text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-lg shadow-indigo-150 dark:shadow-none"
                        >
                            Complete Payment
                        </button>
                    </div>

                    <!-- Midtrans Snap popup hook -->
                    <script 
                        src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" 
                        data-client-key="{{ config('services.midtrans.client_key') }}"
                    ></script>

                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const payButton = document.getElementById('pay-button');
                            if (payButton) {
                                payButton.addEventListener('click', () => {
                                    const snapToken = '{{ $order->payment->snap_token }}';
                                    
                                    if (snapToken.startsWith('mock-snap-token')) {
                                        alert('Simulating payment sandbox popup.');
                                        $wire.simulateSettlement();
                                        return;
                                    }

                                    snap.pay(snapToken, {
                                        onSuccess: function(result) {
                                            $wire.checkPaymentStatus();
                                        },
                                        onPending: function(result) {
                                            $wire.checkPaymentStatus();
                                        },
                                        onError: function(result) {
                                            alert("Payment failed!");
                                        },
                                        onClose: function() {
                                            alert('Popup closed. You can retry paying by clicking Complete Payment.');
                                        }
                                    });
                                });
                            }
                        });
                    </script>
                @endif
            </div>
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
