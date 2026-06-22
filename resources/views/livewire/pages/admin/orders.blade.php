<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;
    public ?int $selectedOrderId = null;
    public string $trackingNumber = '';
    public string $orderStatus = '';

    // Search and Filters
    public string $search = '';
    public string $statusFilter = '';
    public string $paymentStatusFilter = '';
    public string $courierFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCourierFilter(): void
    {
        $this->resetPage();
    }

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

        $order = Order::with(['items.product', 'items.productVariant', 'payment'])->findOrFail($this->selectedOrderId);
        
        $oldStatus = $order->status;
        $newStatus = $this->orderStatus;

        // Auto-Status Promotion: processing + tracking number -> shipping
        if ($newStatus === 'processing' && $this->trackingNumber !== '' && $order->tracking_number === null) {
            $newStatus = 'shipping';
            $this->orderStatus = 'shipping';
        }

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
                if ($item->product_variant_id && $item->productVariant) {
                    $item->productVariant->increment('stock', $item->quantity);
                } elseif ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
            // Restore voucher usage
            foreach ($order->vouchers as $voucher) {
                $voucher->decrement('used_count');
            }
        }

        $this->dispatch('notify', type: 'success', message: 'Pesanan berhasil diperbarui!');
        $this->closeDetails();
    }

    public function cleanWhatsAppPhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '0')) {
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            $cleaned = '62' . $cleaned;
        }
        return $cleaned;
    }

    public function getOrdersProperty()
    {
        $query = Order::with(['user', 'payment', 'items']);

        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                  ->orWhere('shipping_recipient_name', 'like', '%' . $this->search . '%')
                  ->orWhere('tracking_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function($qu) {
                      $qu->where('email', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->paymentStatusFilter)) {
            $query->whereHas('payment', function($qp) {
                $qp->where('status', $this->paymentStatusFilter);
            });
        }

        if (!empty($this->courierFilter)) {
            $query->where('shipping_courier', $this->courierFilter);
        }

        return $query->orderBy('created_at', 'desc')->paginate(10);
    }

    public function getEligibleForAutoCompletionCountProperty(): int
    {
        return Order::where('status', 'shipping')
            ->where(function ($query) {
                $query->where('shipped_at', '<=', now()->subDays(14))
                    ->orWhere(function ($q) {
                        $q->whereNull('shipped_at')
                          ->where('updated_at', '<=', now()->subDays(14));
                    });
            })
            ->count();
    }

    public function completeEligibleOrders(): void
    {
        $eligibleOrders = Order::where('status', 'shipping')
            ->where(function ($query) {
                $query->where('shipped_at', '<=', now()->subDays(14))
                    ->orWhere(function ($q) {
                        $q->whereNull('shipped_at')
                          ->where('updated_at', '<=', now()->subDays(14));
                    });
            })
            ->get();

        $count = $eligibleOrders->count();

        if ($count === 0) {
            $this->dispatch('notify', type: 'error', message: 'Tidak ada pesanan yang memenuhi syarat untuk diselesaikan otomatis.');
            return;
        }

        foreach ($eligibleOrders as $order) {
            $order->update(['status' => 'completed']);
        }

        $this->dispatch('notify', type: 'success', message: "Berhasil menyelesaikan {$count} pesanan secara massal!");
    }
};
?>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">Manajemen Pesanan</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Tinjau pesanan, kelola pelacakan pengiriman, dan perbarui siklus status.</p>
            </div>
            
            <div class="flex items-center gap-3">
                @php
                    $count = $this->eligibleForAutoCompletionCount;
                @endphp
                <button 
                    wire:click="completeEligibleOrders" 
                    @if($count === 0) disabled @endif
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold shadow-md transition-all duration-300 select-none
                        @if($count > 0)
                            bg-gradient-to-r from-emerald-600 to-teal-500 text-white hover:shadow-emerald-200/50 hover:shadow-lg dark:hover:shadow-emerald-950/20 active:scale-95 cursor-pointer
                        @else
                            bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-600 cursor-not-allowed border border-gray-200 dark:border-gray-700
                        @endif"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <span>Selesaikan Otomatis (+14 Hari)</span>
                    @if($count > 0)
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-black bg-white text-emerald-700 rounded-full animate-bounce">
                            {{ $count }}
                        </span>
                    @endif
                </button>
            </div>
        </div>

        <!-- Search and Filters Section -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm mb-8 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search input -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Cari Pesanan</label>
                    <div class="relative">
                        <input 
                            wire:model.live.debounce.300ms="search" 
                            type="text" 
                            placeholder="No. Faktur, Nama, Resi, Email..."
                            class="w-full pl-9 pr-4 py-2.5 border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                        >
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Status Pesanan</label>
                    <select wire:model.live="statusFilter" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">Semua Status</option>
                        <option value="pending">Menunggu Pembayaran</option>
                        <option value="processing">Diproses</option>
                        <option value="shipping">Dikirim</option>
                        <option value="completed">Selesai</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </div>

                <!-- Payment Status Filter -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Status Pembayaran</label>
                    <select wire:model.live="paymentStatusFilter" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">Semua Pembayaran</option>
                        <option value="pending">Menunggu</option>
                        <option value="settlement">Lunas</option>
                        <option value="expire">Kadaluarsa</option>
                        <option value="cancel">Batal</option>
                    </select>
                </div>

                <!-- Courier Filter -->
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Kurir Pengiriman</label>
                    <select wire:model.live="courierFilter" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                        <option value="">Semua Kurir</option>
                        <option value="jne">JNE</option>
                        <option value="pos">POS Indonesia</option>
                        <option value="tiki">TIKI</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="lg:grid lg:grid-cols-3 lg:gap-8">
            <!-- Orders List -->
            <div class="{{ $selectedOrderId ? 'lg:col-span-2' : 'lg:col-span-3' }} transition-all duration-300">
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/30">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Faktur</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pelanggan</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Kurir</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pembayaran</th>
                                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                @if($this->orders->isEmpty())
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                            Belum ada transaksi.
                                        </td>
                                    </tr>
                                @else
                                    @foreach($this->orders as $o)
                                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10 transition cursor-pointer" wire:click="selectOrder({{ $o->id }})">
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
                                                    {{ $o->status === 'shipping' ? 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300' : '' }}
                                                    {{ $o->status === 'cancelled' ? 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : '' }}
                                                ">
                                                    {{ match ($o->status) { 'pending' => 'Menunggu', 'processing' => 'Diproses', 'shipping' => 'Dikirim', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan', default => $o->status } }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider
                                                    {{ $o->payment?->status === 'settlement' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : '' }}
                                                    {{ $o->payment?->status === 'pending' ? 'bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-300' : '' }}
                                                    {{ in_array($o->payment?->status, ['expire', 'cancel']) ? 'bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-300' : '' }}
                                                ">
                                                    {{ match ($o->payment?->status ?? 'pending') { 'pending' => 'MENUNGGU', 'settlement' => 'LUNAS', 'expire' => 'KADALUARSA', 'cancel' => 'BATAL', default => strtoupper($o->payment?->status ?? 'PENDING') } }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold">
                                                <button class="text-indigo-600 hover:text-indigo-900 transition dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    Proses
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Links -->
                    <div class="px-6 py-4 border-t border-gray-150 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/10">
                        {{ $this->orders->links() }}
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
                            <div class="flex items-center justify-between border-b border-gray-50 dark:border-gray-700 pb-4">
                                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Detail Pemenuhan</h2>
                                <button wire:click="closeDetails" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Invoice and Info -->
                            <div class="space-y-2">
                                <span class="text-xs text-gray-400 block">Nomor Pesanan</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-extrabold text-gray-900 dark:text-gray-100 select-all">{{ $detailOrder->order_number }}</span>
                                    <button 
                                        @click="navigator.clipboard.writeText('{{ $detailOrder->order_number }}'); $dispatch('notify', { type: 'success', message: 'Nomor pesanan disalin!' })"
                                        class="p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition"
                                        title="Salin No. Pesanan"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500">Pelanggan: {{ $detailOrder->shipping_recipient_name }} ({{ $detailOrder->shipping_phone_number }})</p>
                                <p class="text-xs text-gray-500">{{ $detailOrder->shipping_address_line }}, {{ $detailOrder->shipping_city }}, {{ $detailOrder->shipping_province }}</p>
                                
                                <!-- Quick Actions (WhatsApp & Call) -->
                                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-50 dark:border-gray-700/50">
                                    <a 
                                        href="https://wa.me/{{ $this->cleanWhatsAppPhone($detailOrder->shipping_phone_number) }}" 
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/40 dark:text-emerald-300 rounded-xl text-xs font-semibold transition"
                                    >
                                        <svg class="h-3.5 w-3.5 fill-current" viewBox="0 0 24 24">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.665.989 3.3 1.472 5.358 1.473 5.466 0 9.914-4.437 9.917-9.896.002-2.646-1.03-5.132-2.906-7.01C17.142 1.839 14.66 1.8 12.016 1.8c-5.468 0-9.916 4.439-9.919 9.899-.001 2.124.562 4.103 1.63 5.864l-.973 3.553 3.655-.96l-.161-.092zm8.813-5.267c-.29-.145-1.72-.85-1.987-.947-.267-.097-.461-.145-.655.145-.194.29-.752.947-.922 1.14-.169.194-.339.218-.63.073-.29-.145-1.223-.45-2.33-1.437-.862-.77-1.443-1.72-1.613-2.012-.17-.29-.018-.448.128-.592.131-.13.29-.339.436-.509.145-.17.194-.29.291-.485.097-.194.048-.364-.024-.509-.073-.145-.655-1.577-.898-2.16-.236-.569-.475-.491-.655-.5h-.56c-.194 0-.509.073-.776.364-.267.29-1.02 1.02-1.02 2.487 0 1.468 1.067 2.88 1.213 3.074.145.194 2.1 3.21 5.09 4.506.71.307 1.266.491 1.7.63.714.227 1.36.195 1.872.118.571-.085 1.72-.704 1.962-1.383.243-.679.243-1.262.17-1.383-.073-.12-.267-.194-.558-.339z"/>
                                        </svg>
                                        WhatsApp
                                    </a>
                                    @if($detailOrder->tracking_number)
                                        <button 
                                            @click="navigator.clipboard.writeText('{{ $detailOrder->tracking_number }}'); $dispatch('notify', { type: 'success', message: 'Nomor resi disalin!' })"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 rounded-xl text-xs font-semibold transition"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                            Salin Resi
                                        </button>
                                    @endif
                                </div>

                                <!-- Print Documents (Invoice & Label) -->
                                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-50 dark:border-gray-700/50">
                                    <a 
                                        href="{{ route('order.print', $detailOrder) }}" 
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/40 dark:text-indigo-300 rounded-xl text-xs font-semibold transition"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        Cetak Invoice
                                    </a>
                                    <a 
                                        href="{{ route('admin.order.shipping-label', $detailOrder) }}" 
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/40 dark:text-indigo-300 rounded-xl text-xs font-semibold transition"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        Cetak Label
                                    </a>
                                </div>
                            </div>

                            <!-- Items -->
                            <div class="bg-gray-50 dark:bg-gray-700/30 p-4 rounded-2xl border border-gray-100 dark:border-gray-700/50 space-y-3">
                                <h3 class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Produk</h3>
                                @foreach($detailOrder->items as $i)
                                    <div class="flex justify-between text-xs text-gray-900 dark:text-gray-100">
                                        <span class="font-medium">{{ $i->name }} (x{{ $i->quantity }})</span>
                                        <span class="font-bold">Rp {{ number_format($i->price * $i->quantity, 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                                <div class="border-t border-gray-200 dark:border-gray-600 pt-2 flex justify-between text-xs font-bold text-gray-900 dark:text-gray-100">
                                    <span>Total Nilai</span>
                                    <span>Rp {{ number_format($detailOrder->total_amount, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <!-- Payment Details -->
                            @if($detailOrder->payment)
                                @php
                                    $payment = $detailOrder->payment;
                                    $payload = $payment->payment_payload;
                                    $bankInfo = '';
                                    if ($payload) {
                                        if ($payment->payment_type === 'bank_transfer' && isset($payload['va_numbers'][0]['bank'])) {
                                            $bankInfo = ' (' . strtoupper($payload['va_numbers'][0]['bank']) . ')';
                                        } elseif ($payment->payment_type === 'cstore' && isset($payload['store'])) {
                                            $bankInfo = ' (' . strtoupper($payload['store']) . ')';
                                        }
                                    }
                                @endphp
                                <div class="bg-gray-50 dark:bg-gray-700/30 p-4 rounded-2xl border border-gray-100 dark:border-gray-700/50 space-y-3">
                                    <h3 class="text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Detail Pembayaran</h3>
                                    <div class="space-y-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        <div class="flex justify-between">
                                            <span>Metode:</span>
                                            <span class="font-semibold text-gray-900 dark:text-gray-100 uppercase">{{ str_replace('_', ' ', $payment->payment_type ?? 'Midtrans') }}{{ $bankInfo }}</span>
                                        </div>
                                        @if($payment->transaction_id)
                                            <div class="flex justify-between">
                                                <span>ID Transaksi:</span>
                                                <span class="font-mono text-gray-700 dark:text-gray-300 select-all">{{ substr($payment->transaction_id, 0, 18) }}...</span>
                                            </div>
                                        @endif
                                        <div class="flex justify-between">
                                            <span>Status:</span>
                                            <span class="font-bold uppercase
                                                {{ $payment->status === 'settlement' ? 'text-emerald-600 dark:text-emerald-400' : '' }}
                                                {{ $payment->status === 'pending' ? 'text-amber-600 dark:text-amber-400' : '' }}
                                                {{ in_array($payment->status, ['expire', 'cancel']) ? 'text-rose-600 dark:text-rose-400' : '' }}
                                            ">
                                                {{ match ($payment->status) { 'pending' => 'MENUNGGU', 'settlement' => 'LUNAS', 'expire' => 'KADALUARSA', 'cancel' => 'BATAL', default => strtoupper($payment->status) } }}
                                            </span>
                                        </div>
                                        @if($payment->updated_at)
                                            <div class="flex justify-between">
                                                <span>Waktu:</span>
                                                <span>{{ $payment->updated_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Form updates -->
                            <div class="space-y-4">
                                <!-- Order Status -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Status Pemenuhan</label>
                                    <select wire:model="orderStatus" class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="pending">Menunggu Pembayaran</option>
                                        <option value="processing">Diproses (Dipersiapkan)</option>
                                        <option value="shipping">Dikirim (Dalam Perjalanan)</option>
                                        <option value="completed">Selesai</option>
                                        <option value="cancelled">Dibatalkan</option>
                                    </select>
                                </div>

                                <!-- Tracking Number -->
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Nomor Resi / Pelacakan</label>
                                    <input 
                                        wire:model="trackingNumber" 
                                        type="text" 
                                        placeholder="contoh: JNE123456789"
                                        class="w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    >
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button wire:click="closeDetails" class="w-1/2 px-4 py-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold text-sm rounded-xl transition">
                                    Batal
                                </button>
                                <button wire:click="updateOrder" class="w-1/2 px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-xl transition shadow">
                                    Simpan Perubahan
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
