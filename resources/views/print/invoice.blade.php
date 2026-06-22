<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $order->order_number }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            body {
                background-color: white !important;
                color: black !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans p-6 md:p-12">
    <!-- Print Button for Preview Page -->
    <div class="no-print flex justify-between items-center mb-8 bg-gray-50 p-4 rounded-2xl border border-gray-100 max-w-4xl mx-auto">
        <div>
            <h1 class="text-sm font-bold text-gray-800">Preview Invoice</h1>
            <p class="text-xs text-gray-500">Gunakan tombol cetak untuk mencetak atau menyimpan sebagai PDF.</p>
        </div>
        <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-xl transition shadow">
            Cetak Invoice
        </button>
    </div>

    <!-- Invoice Sheet -->
    <div class="max-w-4xl mx-auto border border-gray-200 p-8 rounded-3xl space-y-8 bg-white shadow-sm">
        <!-- Logo and Invoice Header -->
        <div class="flex justify-between items-start">
            <div>
                <span class="text-2xl font-black text-indigo-600 tracking-tight">e-shoesbox</span>
                <p class="text-xs text-gray-500 mt-1">Toko Sepatu Premium Pilihan Anda</p>
            </div>
            <div class="text-right">
                <h2 class="text-lg font-black text-gray-900 uppercase">Faktur Penjualan</h2>
                <p class="text-sm font-mono text-gray-600 mt-1">{{ $order->order_number }}</p>
            </div>
        </div>

        <hr class="border-gray-200">

        <!-- Info details -->
        <div class="grid grid-cols-2 gap-8 text-sm">
            <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Penerima Pengiriman</h3>
                <p class="font-extrabold text-gray-900">{{ $order->shipping_recipient_name }}</p>
                <p class="text-gray-600 mt-1">{{ $order->shipping_phone_number }}</p>
                <p class="text-gray-600 mt-1 leading-relaxed">
                    {{ $order->shipping_address_line }}<br>
                    {{ $order->shipping_city }}, {{ $order->shipping_province }} {{ $order->shipping_postal_code }}
                </p>
            </div>
            <div class="text-right">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Detail Pesanan</h3>
                <div class="space-y-1">
                    <p class="text-gray-600">Tanggal: <span class="font-semibold text-gray-900">{{ $order->created_at->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</span></p>
                    <p class="text-gray-600">Kurir: <span class="font-semibold text-gray-900 uppercase">{{ $order->shipping_courier }} ({{ $order->shipping_service }})</span></p>
                    <p class="text-gray-600">Status Pembayaran: 
                        <span class="font-bold uppercase text-emerald-600">
                            {{ match ($order->payment?->status ?? 'pending') { 'pending' => 'MENUNGGU', 'settlement' => 'LUNAS', 'expire' => 'KADALUARSA', 'cancel' => 'BATAL', default => strtoupper($order->payment?->status ?? 'PENDING') } }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Order Items Table -->
        <div class="overflow-hidden border border-gray-200 rounded-2xl">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Produk</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Harga Satuan</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Jumlah</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @foreach($order->items as $item)
                        <tr>
                            <td class="px-6 py-4">
                                <p class="font-bold text-gray-900">{{ $item->name }}</p>
                            </td>
                            <td class="px-6 py-4 text-right text-gray-600">
                                Rp {{ number_format($item->price, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center text-gray-900 font-medium">
                                {{ $item->quantity }}
                            </td>
                            <td class="px-6 py-4 text-right font-extrabold text-gray-900">
                                Rp {{ number_format($item->price * $item->quantity, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Financial Summary -->
        <div class="flex justify-end">
            <div class="w-full md:w-1/2 space-y-2 text-sm text-gray-600">
                <div class="flex justify-between pb-1 border-b border-gray-100">
                    <span>Subtotal Produk</span>
                    <span class="font-bold text-gray-900">Rp {{ number_format($order->subtotal_amount, 0, ',', '.') }}</span>
                </div>
                @if($order->discount_amount > 0)
                    <div class="flex justify-between text-rose-600 pb-1 border-b border-gray-100">
                        <span>Potongan Voucher Belanja</span>
                        <span>-Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between pb-1 border-b border-gray-100">
                    <span>Ongkos Kirim</span>
                    <span class="font-bold text-gray-900">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                </div>
                @if($order->shipping_discount_amount > 0)
                    <div class="flex justify-between text-rose-600 pb-1 border-b border-gray-100">
                        <span>Potongan Gratis Ongkir</span>
                        <span>-Rp {{ number_format($order->shipping_discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-base font-extrabold text-gray-900 pt-2">
                    <span>Total Pembayaran</span>
                    <span class="text-lg font-black text-indigo-600">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <hr class="border-gray-200">

        <!-- Footer terms -->
        <div class="text-center text-xs text-gray-400">
            <p>Terima kasih telah berbelanja di e-shoesbox!</p>
            <p class="mt-1">Simpan faktur penjualan ini sebagai bukti transaksi yang sah.</p>
        </div>
    </div>

    <!-- Auto print trigger -->
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
