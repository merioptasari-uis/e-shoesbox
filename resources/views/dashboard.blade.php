<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-150 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

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

                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between group hover:shadow-md transition">
                        <div>
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Total Produk</span>
                            <span class="text-3xl font-black text-gray-900 dark:text-white mt-1 block">{{ \App\Models\Product::count() }}</span>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-2xl group-hover:scale-110 transition">
                            👟
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between group hover:shadow-md transition">
                        <div>
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Voucher Aktif</span>
                            <span class="text-3xl font-black text-gray-900 dark:text-white mt-1 block">{{ \App\Models\Voucher::where('is_active', true)->count() }}</span>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-pink-50 dark:bg-pink-950/50 flex items-center justify-center text-pink-600 dark:text-pink-400 text-2xl group-hover:scale-110 transition">
                            🎫
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center justify-between group hover:shadow-md transition">
                        <div>
                            <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider block">Total Pesanan</span>
                            <span class="text-3xl font-black text-gray-900 dark:text-white mt-1 block">{{ \App\Models\Order::count() }}</span>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-2xl group-hover:scale-110 transition">
                            📦
                        </div>
                    </div>
                </div>

                <!-- Admin Action Panels -->
                <div>
                    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white mb-6">Menu Manajemen Toko</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Products Management Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between hover:shadow-lg transition">
                            <div>
                                <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center text-2xl mb-4">
                                    🛍️
                                </div>
                                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Kelola Produk & Diskon</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">
                                    Atur katalog sepatu, perbarui jumlah stok, dan tentukan harga diskon coret/langsung dari seller tanpa voucher.
                                </p>
                            </div>
                            <div class="mt-6">
                                <a href="{{ route('admin.products') }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-sm hover:scale-[1.02]">
                                    Kelola Produk ➔
                                </a>
                            </div>
                        </div>

                        <!-- Vouchers Management Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between hover:shadow-lg transition">
                            <div>
                                <div class="w-12 h-12 rounded-2xl bg-pink-50 dark:bg-pink-950/30 flex items-center justify-center text-2xl mb-4">
                                    🎫
                                </div>
                                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Kelola Voucher Promo</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">
                                    Buat dan atur kupon voucher (diskon persentase, potongan harga langsung, gratis ongkir), kuota limit, serta masa kedaluwarsa.
                                </p>
                            </div>
                            <div class="mt-6">
                                <a href="{{ route('admin.vouchers') }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-pink-600 hover:bg-pink-700 text-white text-xs font-bold transition shadow-sm hover:scale-[1.02]">
                                    Kelola Voucher ➔
                                </a>
                            </div>
                        </div>

                        <!-- Orders Management Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-3xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 flex flex-col justify-between hover:shadow-lg transition">
                            <div>
                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 flex items-center justify-center text-2xl mb-4">
                                    📦
                                </div>
                                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Kelola Pesanan Pelanggan</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 leading-relaxed">
                                    Pantau dan proses pesanan sepatu masuk, update nomor resi pengiriman, serta validasi status pembayaran.
                                </p>
                            </div>
                            <div class="mt-6">
                                <a href="{{ route('admin.orders') }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm hover:scale-[1.02]">
                                    Kelola Pesanan ➔
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

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
                            <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-white text-indigo-700 text-xs font-bold hover:bg-indigo-50 transition animate-bounce">
                                Belanja Sekarang ➔
                            </a>
                            <a href="{{ url('/cart') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-700/50 hover:bg-indigo-700/70 border border-indigo-400/30 text-white text-xs font-bold transition">
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
                            <a href="{{ url('/') }}" class="mt-4 inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow">
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
                                                        'paid' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-400 border-emerald-100 dark:border-emerald-900/50',
                                                        'shipped' => 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-400 border-indigo-100 dark:border-indigo-900/50',
                                                        'completed' => 'bg-teal-50 text-teal-800 dark:bg-teal-950/30 dark:text-teal-400 border-teal-100 dark:border-teal-900/50',
                                                        'cancelled' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/30 dark:text-rose-400 border-rose-100 dark:border-rose-900/50',
                                                    ];
                                                    $statusLabel = [
                                                        'pending' => 'Menunggu Pembayaran',
                                                        'paid' => 'Sudah Dibayar',
                                                        'shipped' => 'Dalam Pengiriman',
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
                                                <a href="{{ route('order.details', $order->id) }}" class="text-xs font-extrabold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition">
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
</x-app-layout>
