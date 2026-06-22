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
    public int $rating = 5;
    public string $comment = '';
    public ?int $activeReviewProductId = null;

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
            $orderId = str_replace('/', '-', $this->order->order_number);
            $url = $isProd
                ? "https://api.midtrans.com/v2/{$orderId}/status"
                : "https://api.sandbox.midtrans.com/v2/{$orderId}/status";

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
            $this->dispatch('notify', type: 'success', message: 'Simulasi penyelesaian pembayaran berhasil!');
        }
    }

    public function confirmReceived(): void
    {
        if ($this->order->user_id !== Auth::id()) {
            abort(403);
        }

        if ($this->order->status === 'shipping') {
            $this->order->update(['status' => 'completed']);
            $this->order->load('payment');
            $this->dispatch('notify', type: 'success', message: 'Pesanan telah diterima! Terima kasih telah berbelanja.');
        }
    }

    public function submitReview(int $productId): void
    {
        if ($this->order->user_id !== Auth::id()) {
            abort(403);
        }

        if ($this->order->status !== 'completed') {
            $this->dispatch('notify', type: 'error', message: 'Anda hanya dapat menulis ulasan setelah status pesanan selesai!');
            return;
        }

        // Check if user has already reviewed this product for this order
        $alreadyReviewed = \App\Models\Review::where('user_id', Auth::id())
            ->where('order_id', $this->order->id)
            ->where('product_id', $productId)
            ->exists();

        if ($alreadyReviewed) {
            $this->dispatch('notify', type: 'error', message: 'Anda sudah menulis ulasan untuk produk ini!');
            return;
        }

        $this->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        \App\Models\Review::create([
            'user_id' => Auth::id(),
            'product_id' => $productId,
            'order_id' => $this->order->id,
            'rating' => $this->rating,
            'comment' => $this->comment !== '' ? $this->comment : null,
        ]);

        $this->reset(['rating', 'comment', 'activeReviewProductId']);
        $this->order->load('items.product');
        $this->dispatch('notify', type: 'success', message: 'Ulasan Anda berhasil dikirim!');
    }
};
?>

@push('meta')
    <meta name="description" content="Lacak status detail pembayaran, pengemasan, pengiriman, dan konfirmasi barang pesanan Anda di e-shoesbox.">
    <meta name="keywords" content="lacak pesanan, status pengiriman, detail faktur e-shoesbox">
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="e-shoesbox - Detail Pesanan #{{ $order->order_number }}">
    <meta property="og:description" content="Lacak status detail pembayaran, pengemasan, pengiriman, dan konfirmasi barang pesanan Anda.">
    <meta property="og:image" content="{{ asset('favicon.svg') }}">
@endpush

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen" 
     wire:poll.5s="checkPaymentStatus"
     x-data="{ 
         showMockModal: new URLSearchParams(window.location.search).has('showMockPay') || false,
         selectedMockMethod: '',
         paymentSimulated: false,
         closeModal() {
             this.showMockModal = false;
             const url = new URL(window.location);
             url.searchParams.delete('showMockPay');
             window.history.replaceState({}, '', url);
         }
     }"
>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Order Header -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <span class="text-xs font-semibold text-violet-600 dark:text-violet-400 uppercase tracking-widest block mb-1">Detail Faktur</span>
                <h1 class="text-xl sm:text-2xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">
                    {{ $order->order_number }}
                </h1>
                <p class="text-xs text-gray-500 mt-1">Dibuat pada {{ $order->created_at->format('d M Y H:i') }}</p>
            </div>

            <div class="flex flex-col sm:items-end gap-2.5">
                <!-- Status Badge -->
                <span class="inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider
                    {{ $order->status === 'completed' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-900/30' : '' }}
                    {{ $order->status === 'pending' ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-300 border border-amber-100 dark:border-amber-900/30' : '' }}
                    {{ $order->status === 'processing' ? 'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-300 border border-blue-100 dark:border-blue-900/30' : '' }}
                    {{ $order->status === 'shipping' ? 'bg-violet-50 text-violet-800 dark:bg-violet-950 dark:text-violet-300 border border-violet-100 dark:border-violet-900/30' : '' }}
                    {{ $order->status === 'cancelled' ? 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-100 dark:border-rose-900/30' : '' }}
                ">
                    {{ match ($order->status) { 'pending' => 'Menunggu Pembayaran', 'processing' => 'Diproses', 'shipping' => 'Dalam Pengiriman', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', default => $order->status } }}
                </span>

                <!-- Print Invoice Button -->
                <a 
                    href="{{ route('order.print', $order) }}" 
                    target="_blank"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-xs font-bold transition shadow-sm w-fit"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Cetak Invoice
                </a>
            </div>
        </div>

        <!-- Order Progress Stepper -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status Alur Pesanan</h3>
                @if($order->status === 'cancelled')
                    <span class="text-xs font-extrabold text-rose-600 dark:text-rose-400 flex items-center gap-1">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Dibatalkan
                    </span>
                @endif
            </div>

            <!-- Stepper Container -->
            <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6 md:gap-2">
                <!-- Line background for Desktop (Horizontal) -->
                <div class="hidden md:block absolute left-0 right-0 top-1/2 -translate-y-1/2 h-0.5 bg-gray-150 dark:bg-gray-700 -z-10 rounded-full">
                    <!-- Progress bar line -->
                    <div class="h-full bg-violet-650 dark:bg-violet-500 rounded-full transition-all duration-500" 
                         style="width: {{ $order->status === 'completed' ? '100%' : ($order->status === 'shipping' ? '75%' : ($order->status === 'processing' ? '50%' : ($order->status === 'pending' && $order->payment?->status === 'settlement' ? '50%' : ($order->status === 'pending' ? '25%' : '0%')))) }}">
                    </div>
                </div>

                @php
                    $steps = [
                        ['id' => 'created', 'label' => 'Pesanan Dibuat', 'desc' => 'Menunggu verifikasi', 'icon' => 'created'],
                        ['id' => 'paid', 'label' => 'Pembayaran', 'desc' => 'Diverifikasi', 'icon' => 'paid'],
                        ['id' => 'processing', 'label' => 'Diproses', 'desc' => 'Sedang disiapkan', 'icon' => 'processing'],
                        ['id' => 'shipping', 'label' => 'Dikirim', 'desc' => 'Dalam perjalanan', 'icon' => 'shipping'],
                        ['id' => 'completed', 'label' => 'Selesai', 'desc' => 'Diterima pembeli', 'icon' => 'completed'],
                    ];

                    $currentStatus = $order->status;
                    $paymentStatus = $order->payment?->status;

                    // Map order states to step completeness
                    $stepStates = [];
                    // Created is always true
                    $stepStates['created'] = true;
                    // Paid: true if payment is settled, or order is processing/shipping/completed
                    $stepStates['paid'] = ($paymentStatus === 'settlement' || in_array($currentStatus, ['processing', 'shipping', 'completed']));
                    // Processing: true if order status is processing, shipping, or completed
                    $stepStates['processing'] = in_array($currentStatus, ['processing', 'shipping', 'completed']);
                    // Shipping: true if shipping or completed
                    $stepStates['shipping'] = in_array($currentStatus, ['shipping', 'completed']);
                    // Completed: true if completed
                    $stepStates['completed'] = ($currentStatus === 'completed');

                    // Check active states
                    $activeStep = 'created';
                    if ($stepStates['completed']) {
                        $activeStep = 'completed';
                    } elseif ($stepStates['shipping']) {
                        $activeStep = 'shipping';
                    } elseif ($stepStates['processing']) {
                        $activeStep = 'processing';
                    } elseif ($stepStates['paid']) {
                        $activeStep = 'processing'; // If paid, next active is processing
                    } else {
                        $activeStep = 'paid'; // If created but unpaid, active is paid (since they need to pay)
                    }
                @endphp

                @foreach($steps as $index => $step)
                    @php
                        $isCompleted = $stepStates[$step['id']];
                        $isActive = ($activeStep === $step['id'] && $currentStatus !== 'cancelled');
                        $isCancelled = ($currentStatus === 'cancelled' && !$isCompleted);
                    @endphp

                    <div class="flex md:flex-col items-center gap-4 md:gap-2 flex-1 w-full relative">
                        <!-- Step Circle Icon -->
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 border-2 transition-all duration-300 z-10
                            {{ $isCompleted 
                                ? 'bg-violet-50 dark:bg-violet-950/40 border-violet-600 text-violet-600 dark:text-violet-400 font-extrabold shadow-sm' 
                                : ($isActive 
                                    ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-500 text-amber-500 font-extrabold animate-pulse' 
                                    : ($isCancelled 
                                        ? 'bg-rose-50 dark:bg-rose-950/20 border-rose-500 text-rose-500' 
                                        : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500')) }}
                        ">
                            @if($step['id'] === 'created')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            @elseif($step['id'] === 'paid')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            @elseif($step['id'] === 'processing')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            @elseif($step['id'] === 'shipping')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            @elseif($step['id'] === 'completed')
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @endif
                        </div>

                        <!-- Labels -->
                        <div class="text-left md:text-center">
                            <p class="text-xs font-extrabold 
                                {{ $isCompleted ? 'text-violet-900 dark:text-violet-200 font-bold' : ($isActive ? 'text-amber-700 dark:text-amber-400 font-bold' : ($isCancelled ? 'text-rose-500' : 'text-gray-500 dark:text-gray-400')) }}
                            ">
                                {{ $step['label'] }}
                            </p>
                            <p class="text-[10px] text-gray-450 dark:text-gray-500 font-medium">
                                {{ $step['desc'] }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Simulation Banner for Local Developers -->
        @if($order->status === 'pending')
            <div class="bg-violet-50 dark:bg-violet-950/30 border border-violet-100 dark:border-violet-900/30 rounded-3xl p-6 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span class="text-xs font-bold text-violet-600 dark:text-violet-400 uppercase tracking-wider block mb-1">Alat Sandbox Developer</span>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Simulasikan Penyelesaian Pembayaran</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Gunakan simulator untuk mensimulasikan berbagai metode pembayaran sandbox.</p>
                </div>
                <button @click="showMockModal = true" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-semibold rounded-xl text-white bg-violet-650 hover:bg-violet-700 transition shadow">
                    Buka Simulator Pembayaran
                </button>
            </div>
        @endif

        <!-- Confirm Received Banner for Buyer -->
        @if($order->status === 'shipping')
            <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/30 rounded-3xl p-6 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block mb-1">Konfirmasi Pesanan Diterima</span>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Apakah produk Anda sudah sampai?</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Jika Anda telah menerima produk dengan baik, klik tombol di bawah untuk menyelesaikan pesanan.</p>
                </div>
                <button wire:click="confirmReceived" class="inline-flex items-center justify-center px-5 py-2.5 border border-transparent text-xs font-extrabold rounded-xl text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-md hover:scale-[1.02]">
                    Pesanan Diterima
                </button>
            </div>
        @endif

        <div class="space-y-6">
            <!-- Items ordered -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">Produk yang Dipesan</h2>
                <div class="space-y-4">
                    @foreach($order->items as $item)
                        <div class="py-3 {{ !$loop->last ? 'border-b border-gray-50 dark:border-gray-700' : '' }}">
                            <div class="flex items-center gap-4">
                                <div class="flex-1">
                                    <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $item->name }}</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">Jumlah: {{ $item->quantity }} x Rp {{ number_format($item->price, 0, ',', '.') }}</p>
                                </div>
                                <span class="text-sm font-extrabold text-gray-900 dark:text-gray-100">
                                    Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}
                                </span>
                            </div>

                            @if($order->status === 'completed' && $item->product_id)
                                @php
                                    $review = \App\Models\Review::where('user_id', Auth::id())
                                        ->where('order_id', $order->id)
                                        ->where('product_id', $item->product_id)
                                        ->first();
                                @endphp

                                @if($review)
                                    <!-- Display Review -->
                                    <div class="mt-3 bg-gray-50 dark:bg-gray-900/30 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 text-xs space-y-1">
                                        <div class="flex items-center text-amber-400 gap-1">
                                            <span class="font-bold text-gray-700 dark:text-gray-300 mr-1">Ulasan Anda:</span>
                                            @for($i = 1; $i <= 5; $i++)
                                                <span>{{ $i <= $review->rating ? '★' : '☆' }}</span>
                                            @endfor
                                        </div>
                                        @if($review->comment)
                                            <p class="text-gray-600 dark:text-gray-400">{{ $review->comment }}</p>
                                        @endif
                                    </div>
                                @else
                                    <!-- Toggle Button -->
                                    <div class="mt-2 text-right">
                                        <button 
                                            wire:click="$set('activeReviewProductId', {{ $activeReviewProductId === $item->product_id ? 'null' : $item->product_id }})"
                                            class="inline-flex items-center px-3 py-1.5 border border-violet-600 hover:bg-violet-50 dark:hover:bg-violet-950/30 text-[10px] font-bold rounded-lg text-violet-600 dark:text-violet-400 transition"
                                        >
                                            {{ $activeReviewProductId === $item->product_id ? 'Tutup Form' : 'Beri Ulasan' }}
                                        </button>
                                    </div>

                                    @if($activeReviewProductId === $item->product_id)
                                        <!-- Inline Review Form -->
                                        <form wire:submit.prevent="submitReview({{ $item->product_id }})" class="mt-3 bg-gray-50/50 dark:bg-gray-900/20 p-4 rounded-2xl border border-violet-100 dark:border-violet-900/30 space-y-3">
                                            <div>
                                                <label class="block text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Pilih Bintang:</label>
                                                <div class="flex items-center gap-1">
                                                    @for($r = 1; $r <= 5; $r++)
                                                        <button 
                                                            type="button" 
                                                            wire:click="$set('rating', {{ $r }})" 
                                                            class="text-xl transition hover:scale-110 focus:outline-none {{ $rating >= $r ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600' }}"
                                                        >
                                                            ★
                                                        </button>
                                                    @endfor
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Komentar:</label>
                                                <textarea 
                                                    wire:model="comment" 
                                                    rows="2" 
                                                    placeholder="Bagikan pengalaman Anda menggunakan sepatu ini..." 
                                                    class="w-full text-xs p-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-violet-500 focus:border-violet-500 focus:outline-none"
                                                ></textarea>
                                                @error('comment') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                                            </div>
                                            <button 
                                                type="submit" 
                                                class="w-full py-2 rounded-xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 transition"
                                            >
                                                Kirim Ulasan
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Shipping address details -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">Informasi Pengiriman</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Nama Penerima & Kontak</span>
                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ $order->shipping_recipient_name }}</p>
                        <p class="text-gray-500 mt-0.5">{{ $order->shipping_phone_number }}</p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Tujuan Pengiriman</span>
                        <p class="font-semibold text-gray-950 dark:text-gray-100">{{ $order->shipping_address_line }}</p>
                        <p class="text-gray-500 mt-0.5">{{ $order->shipping_city }}, {{ $order->shipping_province }} {{ $order->shipping_postal_code }}</p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Metode Kurir</span>
                        <p class="font-extrabold text-gray-900 dark:text-gray-100 uppercase">{{ $order->shipping_courier }} - {{ $order->shipping_service }}</p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Nomor Resi / Pelacakan</span>
                        @if($order->tracking_number)
                            <p class="font-extrabold text-violet-600 dark:text-violet-400 uppercase">{{ $order->tracking_number }}</p>
                        @else
                            <p class="text-gray-400 italic">Belum dikirim</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-gray-100 mb-4">Ringkasan Pembayaran</h2>
                
                <div class="space-y-3 mb-6 border-b border-gray-100 dark:border-gray-700 pb-4">
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>Subtotal Produk</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Rp {{ number_format($order->subtotal_amount, 0, ',', '.') }}</span>
                    </div>
                    @if($order->discount_amount > 0)
                        <div class="flex justify-between text-sm text-emerald-600 dark:text-emerald-400 font-semibold">
                            <span>Diskon Voucher</span>
                            <span>- Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm text-gray-500">
                        <span>Biaya Pengiriman</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                    </div>
                    @if($order->shipping_discount_amount > 0)
                        <div class="flex justify-between text-sm text-emerald-600 dark:text-emerald-400 font-semibold">
                            <span>Diskon Ongkir</span>
                            <span>- Rp {{ number_format($order->shipping_discount_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-base font-extrabold text-gray-900 dark:text-gray-100 pt-2">
                        <span>Total yang Dibayar</span>
                        <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm bg-gray-50 dark:bg-gray-700/30 p-4 rounded-2xl border border-gray-100 dark:border-gray-700">
                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Metode Pembayaran</span>
                        <p class="font-bold text-gray-900 dark:text-gray-100 uppercase">
                            {{ $order->payment?->payment_type ? str_replace('_', ' ', $order->payment->payment_type) : 'Gerbang Pembayaran Midtrans' }}
                        </p>
                    </div>

                    <div>
                        <span class="text-xs text-gray-400 block mb-1">Status Pembayaran</span>
                        <span class="font-extrabold uppercase
                            {{ $order->payment?->status === 'settlement' ? 'text-emerald-600 dark:text-emerald-400' : '' }}
                            {{ $order->payment?->status === 'pending' ? 'text-amber-500 dark:text-amber-400' : '' }}
                            {{ in_array($order->payment?->status, ['expire', 'cancel']) ? 'text-rose-600 dark:text-rose-400' : '' }}
                        ">
                            {{ match ($order->payment?->status ?? 'pending') { 'pending' => 'MENUNGGU PEMBAYARAN', 'settlement' => 'LUNAS', 'expire' => 'KADALUARSA', 'cancel' => 'BATAL', default => strtoupper($order->payment?->status ?? 'PENDING') } }}
                        </span>
                    </div>
                </div>

                <!-- Pay Button (if payment is still pending) -->
                @if($order->status === 'pending' && $order->payment?->status === 'pending' && $order->payment?->snap_token)
                    <div class="mt-6">
                        <button 
                            @click="
                                const snapToken = '{{ $order->payment->snap_token }}';
                                if (snapToken.startsWith('mock-snap-token')) {
                                    showMockModal = true;
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
                                        alert('Pembayaran gagal!');
                                    },
                                    onClose: function() {
                                        alert('Popup ditutup. Anda dapat mencoba membayar kembali dengan mengklik Bayar Sekarang.');
                                    }
                                });
                            "
                            class="w-full flex items-center justify-center px-6 py-4 border border-transparent text-sm font-semibold rounded-2xl text-white bg-violet-600 hover:bg-violet-700 transition shadow-lg shadow-violet-100 dark:shadow-none"
                        >
                            Bayar Sekarang
                        </button>
                    </div>

                    <!-- Midtrans Snap popup hook -->
                    <script 
                        src="{{ config('services.midtrans.is_production') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}" 
                        data-client-key="{{ config('services.midtrans.client_key') }}"
                    ></script>
                @endif
            </div>
        </div>
    </div>

    <!-- Mock Midtrans Snap Modal -->
    <div 
        x-show="showMockModal" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none;"
    >
        <div 
            class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-2xl border border-gray-150 dark:border-gray-700 w-full max-w-md"
            x-transition:enter="transition ease-out duration-300 transform scale-95"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-200 transform scale-100"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="closeModal()"
        >
            <!-- Header -->
            <div class="bg-violet-600 dark:bg-violet-950 p-5 text-white flex items-center justify-between border-b border-violet-700 dark:border-violet-900">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center font-black text-sm text-violet-100 font-sans">S</span>
                    <div>
                        <h4 class="text-sm font-extrabold tracking-tight">Midtrans Simulator</h4>
                        <p class="text-xxs text-violet-200 uppercase tracking-widest font-bold">Sandbox Mode</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-xs text-violet-200 block">Total Bayar</span>
                    <span class="text-base font-black">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-6">
                <!-- Main Screen -->
                <div x-show="selectedMockMethod === ''" class="space-y-4">
                    <div class="text-center py-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-100 dark:border-amber-900/30">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                            Simulasi Pembayaran
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Pilih metode pembayaran simulasi di bawah untuk melanjutkan.</p>
                    </div>

                    <!-- Payment Methods List -->
                    <div class="space-y-2">
                        <!-- Virtual Account -->
                        <button 
                            @click="selectedMockMethod = 'va'" 
                            class="w-full flex items-center justify-between p-4 rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20 hover:bg-violet-50/40 dark:hover:bg-violet-950/20 hover:border-violet-600 dark:hover:border-violet-400 transition text-left"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950 flex items-center justify-center text-violet-600 dark:text-violet-400 font-extrabold text-xs">VA</div>
                                <div>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white block">Transfer Virtual Account</span>
                                    <span class="text-xxs text-gray-500">BCA, Mandiri, BNI, BRI</span>
                                </div>
                            </div>
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>

                        <!-- GoPay -->
                        <button 
                            @click="selectedMockMethod = 'gopay'" 
                            class="w-full flex items-center justify-between p-4 rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20 hover:bg-violet-50/40 dark:hover:bg-violet-950/20 hover:border-violet-600 dark:hover:border-violet-400 transition text-left"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-extrabold text-xs">QR</div>
                                <div>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white block">GoPay / QRIS</span>
                                    <span class="text-xxs text-gray-500">Bayar instan pakai kode QR</span>
                                </div>
                            </div>
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>

                        <!-- Credit Card -->
                        <button 
                            @click="selectedMockMethod = 'cc'" 
                            class="w-full flex items-center justify-between p-4 rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/20 hover:bg-violet-50/40 dark:hover:bg-violet-950/20 hover:border-violet-600 dark:hover:border-violet-400 transition text-left"
                        >
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/40 flex items-center justify-center text-purple-600 dark:text-purple-400 font-extrabold text-xs">CC</div>
                                <div>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white block">Kartu Kredit / Debit</span>
                                    <span class="text-xxs text-gray-500">Visa, Mastercard, JCB</span>
                                </div>
                            </div>
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Virtual Account Screen -->
                <div x-show="selectedMockMethod === 'va'" class="space-y-4" style="display: none;">
                    <button @click="selectedMockMethod = ''" class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 transition">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg> Kembali
                    </button>
                    <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-700 text-center">
                        <p class="text-xs text-gray-450 dark:text-gray-400 uppercase tracking-widest font-bold">Nomor Virtual Account</p>
                        <p class="text-xl font-mono font-black text-violet-600 dark:text-violet-400 mt-1">988776655443321</p>
                        <p class="text-[10px] text-gray-500 mt-2">Gunakan tombol di bawah untuk mensimulasikan pembayaran transfer bank berhasil.</p>
                    </div>
                    <button 
                        @click="paymentSimulated = true; $wire.simulateSettlement(); closeModal()"
                        class="w-full py-3.5 rounded-2xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 transition shadow-lg shadow-violet-100 dark:shadow-none"
                    >
                        Simulasikan Pembayaran Sukses
                    </button>
                </div>

                <!-- GoPay Screen -->
                <div x-show="selectedMockMethod === 'gopay'" class="space-y-4" style="display: none;">
                    <button @click="selectedMockMethod = ''" class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 transition">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg> Kembali
                    </button>
                    <div class="flex flex-col items-center justify-center p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700 text-center">
                        <div class="w-32 h-32 bg-white p-2 rounded-xl border border-gray-200 flex flex-col items-center justify-center relative overflow-hidden mb-3">
                            <div class="w-full h-full flex flex-wrap gap-1 opacity-75">
                                @for($i = 0; $i < 64; $i++)
                                    <div class="w-3 h-3 bg-gray-900"></div>
                                @endfor
                            </div>
                            <div class="absolute inset-0 bg-white/95 border-4 border-violet-650 rounded-xl m-1 flex items-center justify-center font-extrabold text-[10px] text-violet-650 tracking-wider">
                                MOCK QRIS
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Pindai kode QR tiruan di atas untuk menyelesaikan pesanan.</p>
                    </div>
                    <button 
                        @click="paymentSimulated = true; $wire.simulateSettlement(); closeModal()"
                        class="w-full py-3.5 rounded-2xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 transition shadow-lg shadow-violet-100 dark:shadow-none"
                    >
                        Simulasikan Pembayaran Sukses
                    </button>
                </div>

                <!-- Credit Card Screen -->
                <div x-show="selectedMockMethod === 'cc'" class="space-y-4" style="display: none;">
                    <button @click="selectedMockMethod = ''" class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 transition">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg> Kembali
                    </button>
                    
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Nomor Kartu</label>
                            <input type="text" placeholder="4111 1111 1111 1111" disabled class="w-full bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-500 text-xs rounded-xl py-2.5 px-3">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Masa Berlaku</label>
                                <input type="text" placeholder="12/28" disabled class="w-full bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-500 text-xs rounded-xl py-2.5 px-3">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">CVV</label>
                                <input type="text" placeholder="123" disabled class="w-full bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-500 text-xs rounded-xl py-2.5 px-3">
                            </div>
                        </div>
                    </div>
                    
                    <button 
                        @click="paymentSimulated = true; $wire.simulateSettlement(); closeModal()"
                        class="w-full py-3.5 rounded-2xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 transition shadow-lg shadow-violet-100 dark:shadow-none"
                    >
                        Simulasikan Pembayaran Sukses
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <button 
                    @click="closeModal()" 
                    class="text-xs font-semibold text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 transition"
                >
                    Batalkan / Tutup
                </button>
                <span class="text-[10px] text-gray-400 dark:text-gray-500">Merchant: e-shoesbox</span>
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
