<?php

use App\Models\Voucher;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\rules;

layout('layouts.app');

state([
    'search' => '',
    'isModalOpen' => false,
    'editingVoucherId' => null,
    
    // Form fields
    'code' => '',
    'type' => 'percentage',
    'value' => '',
    'max_discount' => '',
    'min_spend' => '0',
    'limit_total' => '',
    'limit_per_user' => '1',
    'expires_at' => '',
    'is_active' => true,
]);

rules([
    'code' => ['required', 'string', 'max:255'],
    'type' => ['required', 'in:percentage,fixed,shipping'],
    'value' => ['required', 'numeric', 'min:0'],
    'max_discount' => ['nullable', 'numeric', 'min:0'],
    'min_spend' => ['required', 'numeric', 'min:0'],
    'limit_total' => ['nullable', 'integer', 'min:0'],
    'limit_per_user' => ['required', 'integer', 'min:1'],
    'expires_at' => ['nullable', 'date'],
    'is_active' => ['boolean'],
]);

$openCreateModal = function () {
    $this->resetErrorBag();
    $this->reset(['editingVoucherId', 'code', 'type', 'value', 'max_discount', 'min_spend', 'limit_total', 'limit_per_user', 'expires_at', 'is_active']);
    $this->is_active = true;
    $this->limit_per_user = 1;
    $this->min_spend = 0;
    $this->type = 'percentage';
    $this->isModalOpen = true;
};

$openEditModal = function ($id) {
    $this->resetErrorBag();
    $voucher = Voucher::findOrFail($id);
    
    $this->editingVoucherId = $voucher->id;
    $this->code = $voucher->code;
    $this->type = $voucher->type;
    $this->value = $voucher->value;
    $this->max_discount = $voucher->max_discount;
    $this->min_spend = $voucher->min_spend;
    $this->limit_total = $voucher->limit_total;
    $this->limit_per_user = $voucher->limit_per_user;
    $this->expires_at = $voucher->expires_at ? $voucher->expires_at->format('Y-m-d\TH:i') : '';
    $this->is_active = $voucher->is_active;
    
    $this->isModalOpen = true;
};

$saveVoucher = function () {
    $uniqueRule = 'unique:vouchers,code';
    if ($this->editingVoucherId) {
        $uniqueRule .= ',' . $this->editingVoucherId;
    }
    
    $this->validate([
        'code' => ['required', 'string', 'max:255', $uniqueRule],
    ]);

    $validated = $this->validate();
    $validated['code'] = strtoupper(trim($this->code));
    $validated['max_discount'] = $this->max_discount !== '' && $this->max_discount !== null ? $this->max_discount : null;
    $validated['limit_total'] = $this->limit_total !== '' && $this->limit_total !== null ? $this->limit_total : null;
    $validated['expires_at'] = $this->expires_at !== '' && $this->expires_at !== null ? $this->expires_at : null;

    if ($this->editingVoucherId) {
        $voucher = Voucher::findOrFail($this->editingVoucherId);
        $voucher->update($validated);
        session()->flash('message', 'Voucher berhasil diperbarui!');
    } else {
        Voucher::create($validated);
        session()->flash('message', 'Voucher berhasil dibuat!');
    }
    
    $this->isModalOpen = false;
};

$deleteVoucher = function ($id) {
    $voucher = Voucher::findOrFail($id);
    $voucher->delete();
    session()->flash('message', 'Voucher berhasil dihapus!');
};

$toggleActive = function ($id) {
    $voucher = Voucher::findOrFail($id);
    $voucher->update(['is_active' => !$voucher->is_active]);
};

$getVouchersProperty = function () {
    $query = Voucher::query();
    if (!empty($this->search)) {
        $query->where('code', 'like', '%' . $this->search . '%');
    }
    return $query->orderBy('created_at', 'desc')->get();
};
?>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">Manajemen Voucher</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Buat, edit, aktifkan/nonaktifkan, dan lihat kampanye voucher serta kode diskon.</p>
            </div>
            
            <button 
                wire:click="openCreateModal" 
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow transition duration-150 cursor-pointer"
            >
                Tambah Voucher
            </button>
        </div>

        <!-- Success Toast -->
        @if (session()->has('message'))
            <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-800 dark:text-emerald-300 rounded-2xl border border-emerald-100 dark:border-emerald-900/30 text-sm font-semibold">
                {{ session('message') }}
            </div>
        @endif

        <!-- Filter bar -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl p-4 shadow-sm border border-gray-100 dark:border-gray-700 mb-6 flex items-center">
            <div class="relative flex-1 max-w-xs">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-450">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <input 
                    wire:model.live.debounce.300ms="search" 
                    type="text" 
                    placeholder="Cari kode voucher..." 
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                />
            </div>
        </div>

        <!-- Vouchers Table -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-700/30">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kode Voucher</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nilai</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Minimal Belanja</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Batasan (Digunakan / Total)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kedaluwarsa</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->vouchers as $voucher)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-extrabold text-sm text-gray-900 dark:text-gray-100 font-mono tracking-wider">{{ $voucher->code }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold rounded-full
                                        {{ $voucher->type === 'shipping' ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-800 dark:text-blue-300' : '' }}
                                        {{ $voucher->type === 'percentage' ? 'bg-purple-50 dark:bg-purple-950/30 text-purple-800 dark:text-purple-300' : '' }}
                                        {{ $voucher->type === 'fixed' ? 'bg-indigo-50 dark:bg-indigo-950/30 text-indigo-800 dark:text-indigo-300' : '' }}
                                    ">
                                        {{ match ($voucher->type) { 'percentage' => 'Persentase', 'fixed' => 'Potongan Tetap', 'shipping' => 'Gratis Ongkir', default => $voucher->type } }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-950 dark:text-gray-100">
                                    @if($voucher->type === 'percentage')
                                        {{ number_format($voucher->value, 0) }}% 
                                        @if($voucher->max_discount)
                                            <span class="text-xs text-gray-400 font-normal">(Maks Rp {{ number_format($voucher->max_discount, 0, ',', '.') }})</span>
                                        @endif
                                    @else
                                        Rp {{ number_format($voucher->value, 0, ',', '.') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    Rp {{ number_format($voucher->min_spend, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-bold text-gray-900 dark:text-gray-100">{{ $voucher->used_count }}</span> / 
                                    <span>{{ $voucher->limit_total !== null ? $voucher->limit_total : '∞' }}</span>
                                    <p class="text-xxs text-gray-400">Maks {{ $voucher->limit_per_user }}x per pengguna</p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    @if($voucher->expires_at)
                                        <span class="{{ $voucher->expires_at->isPast() ? 'text-rose-600 font-semibold' : '' }}">
                                            {{ $voucher->expires_at->format('d M Y H:i') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 italic">Tanpa kedaluwarsa</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button wire:click="toggleActive({{ $voucher->id }})" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $voucher->is_active ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $voucher->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold gap-2">
                                    <button wire:click="openEditModal({{ $voucher->id }})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-950 dark:hover:text-indigo-300 cursor-pointer mr-3">
                                        Edit
                                    </button>
                                    <button wire:click="deleteVoucher({{ $voucher->id }})" wire:confirm="Apakah Anda yakin ingin menghapus voucher ini?" class="text-rose-600 dark:text-rose-400 hover:text-rose-950 dark:hover:text-rose-300 cursor-pointer">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Voucher tidak ditemukan. Klik "Tambah Voucher" untuk membuat kampanye baru.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal overlay -->
    @if($isModalOpen)
        <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80 transition-opacity z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-3xl max-w-lg w-full p-6 shadow-xl border border-gray-100 dark:border-gray-700 relative flex flex-col max-h-[90vh]">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 border-b border-gray-50 dark:border-gray-700 pb-4">
                    {{ $editingVoucherId ? 'Edit Voucher' : 'Buat Voucher' }}
                </h3>

                <form wire:submit.prevent="saveVoucher" class="space-y-4 py-4 overflow-y-auto flex-1 pr-1">
                    <!-- Voucher Code -->
                    <div>
                        <x-input-label for="voucher_code" :value="__('Kode Voucher')" />
                        <x-text-input wire:model="code" id="voucher_code" placeholder="contoh: DISKON20" class="block mt-1 w-full uppercase" type="text" required />
                        <x-input-error :messages="$errors->get('code')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Type -->
                        <div>
                            <x-input-label for="voucher_type" :value="__('Tipe Voucher')" />
                            <select wire:model.live="type" id="voucher_type" class="block mt-1 w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="percentage">Diskon Persentase</option>
                                <option value="fixed">Potongan Harga Tetap</option>
                                <option value="shipping">Potongan Gratis Ongkir</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-1" />
                        </div>

                        <!-- Value -->
                        <div>
                            <x-input-label for="voucher_value" :value="$type === 'percentage' ? __('Persentase Diskon (%)') : __('Nilai Diskon (Rp)')" />
                            <x-text-input wire:model="value" id="voucher_value" class="block mt-1 w-full" type="number" required />
                            <x-input-error :messages="$errors->get('value')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Max Discount (percentage only) -->
                        @if($type === 'percentage')
                            <div>
                                <x-input-label for="voucher_max_discount" :value="__('Diskon Maksimal (Rp) - Opsional')" />
                                <x-text-input wire:model="max_discount" id="voucher_max_discount" class="block mt-1 w-full" type="number" />
                                <x-input-error :messages="$errors->get('max_discount')" class="mt-1" />
                            </div>
                        @endif

                        <!-- Min Spend -->
                        <div class="{{ $type !== 'percentage' ? 'col-span-2' : '' }}">
                            <x-input-label for="voucher_min_spend" :value="__('Minimal Belanja yang Dibutuhkan (Rp)')" />
                            <x-text-input wire:model="min_spend" id="voucher_min_spend" class="block mt-1 w-full" type="number" required />
                            <x-input-error :messages="$errors->get('min_spend')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Limit Total -->
                        <div>
                            <x-input-label for="voucher_limit_total" :value="__('Total Stok (Opsional)')" />
                            <x-text-input wire:model="limit_total" id="voucher_limit_total" class="block mt-1 w-full" type="number" placeholder="Tidak Terbatas" />
                            <x-input-error :messages="$errors->get('limit_total')" class="mt-1" />
                        </div>

                        <!-- Limit Per User -->
                        <div>
                            <x-input-label for="voucher_limit_per_user" :value="__('Batasan Per Pengguna')" />
                            <x-text-input wire:model="limit_per_user" id="voucher_limit_per_user" class="block mt-1 w-full" type="number" required />
                            <x-input-error :messages="$errors->get('limit_per_user')" class="mt-1" />
                        </div>
                    </div>

                    <!-- Expiry Date -->
                    <div>
                        <x-input-label for="voucher_expires_at" :value="__('Tanggal & Waktu Kedaluwarsa (Opsional)')" />
                        <x-text-input wire:model="expires_at" id="voucher_expires_at" class="block mt-1 w-full" type="datetime-local" />
                        <x-input-error :messages="$errors->get('expires_at')" class="mt-1" />
                    </div>

                    <!-- Is Active Toggle -->
                    <div class="flex items-center gap-3 pt-2">
                        <input wire:model="is_active" id="voucher_is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 bg-gray-50 dark:bg-gray-700">
                        <x-input-label for="voucher_is_active" :value="__('Kampanye Aktif')" class="!mb-0 cursor-pointer" />
                        <x-input-error :messages="$errors->get('is_active')" class="mt-1" />
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-50 dark:border-gray-700">
                        <button 
                            type="button" 
                            wire:click="$set('isModalOpen', false)" 
                            class="px-4 py-2 text-sm font-semibold rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button 
                            type="submit" 
                            class="px-4 py-2 text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition cursor-pointer shadow"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
