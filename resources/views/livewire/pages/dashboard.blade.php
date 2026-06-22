<?php

use Livewire\Volt\Component;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public function getStatsProperty(): array
    {
        $totalRevenue = (float) Order::whereIn('status', ['processing', 'shipping', 'completed'])
            ->sum('total_amount');

        return [
            'total_products' => Product::count(),
            'active_vouchers' => Voucher::where('is_active', true)->count(),
            'total_orders' => Order::count(),
            'total_revenue' => $totalRevenue,
        ];
    }

    public function getSalesTrendProperty(): array
    {
        $revenue7Days = [];
        $labels7Days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels7Days[] = $date->format('d M');
            $revenue7Days[] = (float) Order::whereDate('created_at', $date->toDateString())
                ->whereIn('status', ['processing', 'shipping', 'completed'])
                ->sum('total_amount');
        }

        return [
            'labels' => $labels7Days,
            'data' => $revenue7Days,
        ];
    }

    public function getCategoryDistributionProperty(): array
    {
        $categories = OrderItem::select('categories.name', DB::raw('SUM(order_items.quantity) as total_qty'))
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['processing', 'shipping', 'completed'])
            ->groupBy('categories.name')
            ->orderByDesc('total_qty')
            ->get();

        return [
            'labels' => $categories->pluck('name')->toArray(),
            'data' => $categories->pluck('total_qty')->map(fn($v) => (int) $v)->toArray(),
        ];
    }

    public function getTopProductsProperty()
    {
        return OrderItem::select('order_items.name', DB::raw('SUM(order_items.quantity) as total_qty'), DB::raw('SUM(order_items.quantity * order_items.price) as total_sales'))
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['processing', 'shipping', 'completed'])
            ->groupBy('order_items.name', 'order_items.product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();
    }
};
?>

<div class="py-10 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        
        @if(auth()->user()->isAdmin())
            <!-- Admin Dashboard Section -->
            <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-3xl p-8 text-white shadow-xl relative overflow-hidden">
                <div class="absolute inset-0 bg-grid-white/[0.05] bg-[size:20px_20px]"></div>
                <div class="relative z-10 space-y-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-white/20 uppercase tracking-widest">Administrator</span>
                    <h1 class="text-3xl font-black tracking-tight">Selamat Datang Kembali, {{ auth()->user()->name }}!</h1>
                    <p class="text-indigo-100 max-w-xl text-sm leading-relaxed">
                        Kelola produk, atur voucher promo diskon, dan pantau pesanan pelanggan dari satu pusat kontrol.
                    </p>
                </div>
            </div>

            <!-- Statistics Grid (4 columns for Admin) -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Total Pendapatan -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between group hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Total Pendapatan</span>
                        <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">
                            Rp {{ number_format($this->stats['total_revenue'], 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-2xl group-hover:scale-110 transition">
                        💰
                    </div>
                </div>

                <!-- Total Pesanan -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between group hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Total Pesanan</span>
                        <span class="text-2xl font-black text-gray-900 dark:text-white mt-1 block">{{ $this->stats['total_orders'] }}</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400 text-2xl group-hover:scale-110 transition">
                        📦
                    </div>
                </div>

                <!-- Total Produk -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between group hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Total Produk</span>
                        <span class="text-2xl font-black text-gray-900 dark:text-white mt-1 block">{{ $this->stats['total_products'] }}</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-2xl group-hover:scale-110 transition">
                        👟
                    </div>
                </div>

                <!-- Voucher Aktif -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between group hover:shadow-md transition">
                    <div>
                        <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Voucher Aktif</span>
                        <span class="text-2xl font-black text-gray-900 dark:text-white mt-1 block">{{ $this->stats['active_vouchers'] }}</span>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-pink-50 dark:bg-pink-950/50 flex items-center justify-center text-pink-600 dark:text-pink-400 text-2xl group-hover:scale-110 transition">
                        🎫
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Line Chart: Sales Trend (2/3 width) -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex justify-between items-center mb-6 bg-transparent">
                        <div>
                            <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Tren Pendapatan Harian</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Statistik pendapatan penjualan bersih dalam 7 hari terakhir</p>
                        </div>
                        <span class="px-2.5 py-1 text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 rounded-lg">7 Hari</span>
                    </div>
                    <div class="relative h-72 w-full bg-transparent">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Donut Chart: Category Distribution (1/3 width) -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col">
                    <div class="mb-6 bg-transparent">
                        <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Porsi Kategori Produk</h3>
                        <p class="text-xs text-gray-550 dark:text-gray-400">Distribusi volume penjualan berdasarkan kategori sepatu</p>
                    </div>
                    <div class="relative flex-1 h-64 w-full bg-transparent flex items-center justify-center">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products Ranking & Action Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Top 5 Products Sold -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white mb-6">Top 5 Produk Terlaris</h3>
                    
                    @if($this->topProducts->isEmpty())
                        <div class="text-center py-12">
                            <span class="text-3xl block mb-2">📊</span>
                            <p class="text-xs text-gray-500">Belum ada transaksi terverifikasi untuk menentukan peringkat produk.</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($this->topProducts as $index => $product)
                                @php
                                    $maxQty = $this->topProducts->first()->total_qty ?: 1;
                                    $pct = ($product->total_qty / $maxQty) * 100;
                                @endphp
                                <div class="space-y-1 bg-transparent">
                                    <div class="flex justify-between items-center text-xs font-semibold bg-transparent">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-extrabold
                                                {{ $index === 0 ? 'bg-amber-100 text-amber-800' : ($index === 1 ? 'bg-slate-100 text-slate-700' : ($index === 2 ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-600')) }}
                                            ">
                                                {{ $index + 1 }}
                                            </span>
                                            <span class="text-gray-900 dark:text-gray-100 font-bold truncate max-w-xs sm:max-w-sm">{{ $product->name }}</span>
                                        </div>
                                        <div class="flex items-center gap-4 bg-transparent text-gray-400 dark:text-gray-500">
                                            <span>{{ $product->total_qty }} pasang</span>
                                            <span class="font-extrabold text-gray-900 dark:text-gray-100">Rp {{ number_format($product->total_sales, 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                    <!-- Progress Bar -->
                                    <div class="w-full h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-indigo-500 to-indigo-650 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Admin Action Panels -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between">
                    <div>
                        <h3 class="text-base font-extrabold text-gray-900 dark:text-white mb-4">Pusat Manajemen Cepat</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-6">
                            Gunakan menu cepat di bawah ini untuk mengakses dashboard administrasi utama secara langsung.
                        </p>
                        <div class="space-y-3 bg-transparent">
                            <a href="{{ route('admin.products') }}" class="w-full flex items-center justify-between p-3.5 rounded-2xl bg-indigo-50/50 hover:bg-indigo-50 dark:bg-indigo-950/20 dark:hover:bg-indigo-950/30 text-indigo-700 dark:text-indigo-400 text-xs font-bold transition hover:scale-[1.01]">
                                <span class="flex items-center gap-2 bg-transparent">👟 Kelola Produk & Stok</span>
                                <span class="bg-transparent">➔</span>
                            </a>
                            <a href="{{ route('admin.orders') }}" class="w-full flex items-center justify-between p-3.5 rounded-2xl bg-emerald-50/50 hover:bg-emerald-50 dark:bg-emerald-950/20 dark:hover:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 text-xs font-bold transition hover:scale-[1.01]">
                                <span class="flex items-center gap-2 bg-transparent">📦 Kelola Pesanan</span>
                                <span class="bg-transparent">➔</span>
                            </a>
                            <a href="{{ route('admin.vouchers') }}" class="w-full flex items-center justify-between p-3.5 rounded-2xl bg-pink-50/50 hover:bg-pink-50 dark:bg-pink-950/20 dark:hover:bg-pink-950/30 text-pink-700 dark:text-pink-400 text-xs font-bold transition hover:scale-[1.01]">
                                <span class="flex items-center gap-2 bg-transparent">🎫 Kelola Voucher Promo</span>
                                <span class="bg-transparent">➔</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Load Chart.js CDN and Initialize charts -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                function initCharts() {
                    const ctxRevenue = document.getElementById('revenueChart');
                    const ctxCategory = document.getElementById('categoryChart');
                    if (!ctxRevenue || !ctxCategory) return;

                    if (window.revenueChartInstance) window.revenueChartInstance.destroy();
                    if (window.categoryChartInstance) window.categoryChartInstance.destroy();

                    window.revenueChartInstance = new Chart(ctxRevenue, {
                        type: 'line',
                        data: {
                            labels: @json($this->salesTrend['labels']),
                            datasets: [{
                                label: 'Pendapatan (Rp)',
                                data: @json($this->salesTrend['data']),
                                borderColor: '#4f46e5',
                                backgroundColor: 'rgba(79, 70, 229, 0.05)',
                                borderWidth: 3.5,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#4f46e5',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointHoverRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return ' Pendapatan: Rp ' + context.parsed.y.toLocaleString('id-ID');
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(156, 163, 175, 0.1)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            if (value >= 1000000) return 'Rp ' + (value / 1000000) + 'M';
                                            if (value >= 1000) return 'Rp ' + (value / 1000) + 'rb';
                                            return 'Rp ' + value;
                                        }
                                    }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });

                    window.categoryChartInstance = new Chart(ctxCategory, {
                        type: 'doughnut',
                        data: {
                            labels: @json($this->categoryDistribution['labels']),
                            datasets: [{
                                data: @json($this->categoryDistribution['data']),
                                backgroundColor: [
                                    '#4f46e5',
                                    '#ec4899',
                                    '#14b8a6',
                                    '#f59e0b',
                                    '#8b5cf6'
                                ],
                                borderWidth: 0,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 15,
                                        font: { size: 11 }
                                    }
                                }
                            },
                            cutout: '70%'
                        }
                    });
                }

                // Trigger on load & wire:navigate
                if (document.readyState === 'complete') {
                    initCharts();
                } else {
                    document.addEventListener('DOMContentLoaded', initCharts);
                }
                document.addEventListener('livewire:navigated', initCharts);
            </script>

        @else
            <!-- Customer Dashboard Section -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-3xl p-8 text-white shadow-xl relative overflow-hidden">
                <div class="absolute inset-0 bg-grid-white/[0.05] bg-[size:20px_20px]"></div>
                <div class="relative z-10 space-y-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-white/20 uppercase tracking-widest">Pelanggan</span>
                    <h1 class="text-3xl font-black tracking-tight">Halo, {{ auth()->user()->name }}!</h1>
                    <p class="text-indigo-100 max-w-xl text-sm leading-relaxed">
                        Selamat datang di e-shoesbox. Cari sepatu favorit Anda dengan harga terbaik dan promo menarik.
                    </p>
                    <div class="flex gap-4 pt-2">
                        <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-white text-indigo-700 text-xs font-bold hover:bg-indigo-50 transition animate-bounce" wire:navigate>
                            Belanja Sekarang ➔
                        </a>
                        <a href="{{ url('/cart') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-700/50 hover:bg-indigo-700/70 border border-indigo-400/30 text-white text-xs font-bold transition" wire:navigate>
                            Lihat Keranjang
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Orders for Customer -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Riwayat Pesanan Anda</h2>
                    <span class="text-xs text-gray-400 uppercase tracking-wider">Total: {{ \App\Models\Order::where('user_id', auth()->id())->count() }} Pesanan</span>
                </div>

                @php
                    $orders = \App\Models\Order::where('user_id', auth()->id())->latest()->get();
                @endphp

                @if($orders->isEmpty())
                    <div class="text-center py-12">
                        <div class="text-4xl mb-3">🛍️</div>
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300">Belum ada pesanan</h3>
                        <p class="text-xs text-gray-400 mt-1 max-w-xs mx-auto">Anda belum pernah melakukan pemesanan sepatu. Koleksi produk menarik kami sedang menunggu Anda!</p>
                        <a href="{{ url('/') }}" class="mt-4 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow" wire:navigate>
                            Jelajahi Toko
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-700 text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                    <th class="pb-3">No. Pesanan</th>
                                    <th class="pb-3">Tanggal</th>
                                    <th class="pb-3">Total Bayar</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700 text-sm">
                                @foreach($orders as $order)
                                    <tr class="text-gray-700 dark:text-gray-300">
                                        <td class="py-3 font-semibold text-gray-900 dark:text-white">
                                            {{ $order->order_number ?? 'INV/' . $order->created_at->format('Ymd') . '/' . $order->id }}
                                        </td>
                                        <td class="py-3">
                                            {{ $order->created_at->format('d M Y, H:i') }}
                                        </td>
                                        <td class="py-3 font-bold">
                                            Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                        </td>
                                        <td class="py-3">
                                            @php
                                                $statusClasses = [
                                                    'pending' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-400 border-amber-100 dark:border-amber-900/50',
                                                    'processing' => 'bg-blue-50 text-blue-800 dark:bg-blue-950/30 dark:text-blue-400 border-blue-100 dark:border-blue-900/50',
                                                    'shipping' => 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-400 border-indigo-100 dark:border-indigo-900/50',
                                                    'completed' => 'bg-teal-50 text-teal-800 dark:bg-teal-950/30 dark:text-teal-400 border-teal-100 dark:border-teal-900/50',
                                                    'cancelled' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400 border-rose-100 dark:border-rose-900/50',
                                                ];
                                                $statusLabel = [
                                                    'pending' => 'Menunggu Pembayaran',
                                                    'processing' => 'Diproses',
                                                    'shipping' => 'Dalam Pengiriman',
                                                    'completed' => 'Selesai',
                                                    'cancelled' => 'Dibatalkan',
                                                ];
                                                $class = $statusClasses[$order->status] ?? 'bg-gray-50 text-gray-800 border-gray-150';
                                                $label = $statusLabel[$order->status] ?? $order->status;
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $class }}">
                                                {{ $label }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right">
                                            <a href="{{ route('order.details', $order->id) }}" class="text-xs font-extrabold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition" wire:navigate>
                                                Detail Pesanan ➔
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

    </div>
</div>
