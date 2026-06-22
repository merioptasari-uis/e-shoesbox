<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label Pengiriman {{ $order->order_number }}</title>
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
<body class="bg-white text-gray-900 font-sans p-6">
    <!-- Print Button for Preview Page -->
    <div class="no-print flex justify-between items-center mb-8 bg-gray-50 p-4 rounded-2xl border border-gray-100 max-w-2xl mx-auto">
        <div>
            <h1 class="text-sm font-bold text-gray-800">Preview Label Pengiriman</h1>
            <p class="text-xs text-gray-500">Gunakan tombol cetak untuk mencetak label label pengemasan.</p>
        </div>
        <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-xl transition shadow">
            Cetak Label
        </button>
    </div>

    <!-- Shipping Label Box -->
    <div class="max-w-2xl mx-auto border-4 border-dashed border-gray-800 p-6 space-y-6 bg-white">
        <!-- Courier Info Header -->
        <div class="flex justify-between items-center border-b-2 border-gray-800 pb-4">
            <div>
                <span class="text-2xl font-black uppercase text-gray-900">{{ $order->shipping_courier }}</span>
                <span class="ml-2 px-3 py-1 bg-gray-900 text-white font-extrabold text-sm rounded-lg uppercase tracking-wider">
                    {{ $order->shipping_service ?? 'REG' }}
                </span>
            </div>
            <div class="text-right">
                <span class="text-xs text-gray-500 block">No. Faktur</span>
                <span class="font-mono font-bold text-gray-800">{{ $order->order_number }}</span>
            </div>
        </div>

        <!-- Sender and Receiver Details -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm divide-y md:divide-y-0 md:divide-x divide-gray-200">
            <!-- Receiver -->
            <div class="space-y-2 pb-6 md:pb-0">
                <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest">Penerima:</h3>
                <p class="text-lg font-black text-gray-900">{{ $order->shipping_recipient_name }}</p>
                <p class="font-extrabold text-gray-800">{{ $order->shipping_phone_number }}</p>
                <p class="text-gray-700 leading-relaxed font-medium">
                    {{ $order->shipping_address_line }}<br>
                    {{ $order->shipping_city }}, {{ $order->shipping_province }}<br>
                    <span class="font-bold">KODE POS: {{ $order->shipping_postal_code }}</span>
                </p>
            </div>

            <!-- Sender -->
            <div class="space-y-2 pt-6 md:pt-0 md:pl-6">
                <h3 class="text-xs font-black text-gray-500 uppercase tracking-widest">Pengirim:</h3>
                <p class="text-lg font-black text-gray-900">e-shoesbox (Toko Online)</p>
                <p class="font-extrabold text-gray-800">0812-3456-7890</p>
                <p class="text-gray-600 leading-relaxed">
                    Kavling Sentra Industri Sepatu, Blok C-3<br>
                    Cibaduyut, Kota Bandung, Jawa Barat
                </p>
            </div>
        </div>

        <!-- Package Notes / Instructions -->
        @if($order->notes)
            <div class="border-t border-gray-200 pt-4 text-xs">
                <span class="font-bold text-gray-700 uppercase block mb-1">Catatan Pembeli:</span>
                <p class="text-gray-600 italic bg-gray-50 p-2.5 rounded-lg border border-gray-100">{{ $order->notes }}</p>
            </div>
        @endif

        <!-- Packing Slip list of items -->
        <div class="border-t-2 border-gray-800 pt-4 space-y-2">
            <h3 class="text-xs font-black text-gray-800 uppercase tracking-widest">Daftar Isi Paket (Picking List):</h3>
            <ul class="divide-y divide-gray-100 text-xs font-semibold text-gray-700">
                @foreach($order->items as $item)
                    <li class="py-2 flex justify-between">
                        <span>{{ $item->name }}</span>
                        <span class="font-bold text-gray-900">QTY: {{ $item->quantity }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="border-t border-gray-200 pt-4 text-center text-[10px] text-gray-400">
            <p>Dicetak otomatis melalui e-shoesbox Admin Panel.</p>
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
