<?php

use App\Models\Campaign;
use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\rules;

layout('layouts.app');

state([
    'search' => '',
    'isModalOpen' => false,
    'editingCampaignId' => null,

    // Form fields
    'title' => '',
    'subtitle' => '',
    'description' => '',
    'badge_text' => '',
    'promo_tag' => '',
    'button_text' => 'Lihat Koleksi',
    'button_link' => '#catalog',
    'bg_gradient' => 'indigo',
    'start_date' => '',
    'end_date' => '',
    'is_active' => true,
]);

rules([
    'title' => ['required', 'string', 'max:255'],
    'subtitle' => ['nullable', 'string', 'max:255'],
    'description' => ['nullable', 'string'],
    'badge_text' => ['nullable', 'string', 'max:255'],
    'promo_tag' => ['nullable', 'string', 'max:255'],
    'button_text' => ['required', 'string', 'max:255'],
    'button_link' => ['required', 'string', 'max:255'],
    'bg_gradient' => ['required', 'string', 'in:indigo,emerald,rose,amber,purple'],
    'start_date' => ['nullable', 'date'],
    'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
    'is_active' => ['boolean'],
]);

$openCreateModal = function () {
    $this->resetErrorBag();
    $this->reset(['editingCampaignId', 'title', 'subtitle', 'description', 'badge_text', 'promo_tag', 'button_text', 'button_link', 'bg_gradient', 'start_date', 'end_date', 'is_active']);
    $this->button_text = 'Lihat Koleksi';
    $this->button_link = '#catalog';
    $this->bg_gradient = 'indigo';
    $this->is_active = true;
    $this->isModalOpen = true;
};

$openEditModal = function ($id) {
    $this->resetErrorBag();
    $campaign = Campaign::findOrFail($id);

    $this->editingCampaignId = $campaign->id;
    $this->title = $campaign->title;
    $this->subtitle = $campaign->subtitle ?? '';
    $this->description = $campaign->description ?? '';
    $this->badge_text = $campaign->badge_text ?? '';
    $this->promo_tag = $campaign->promo_tag ?? '';
    $this->button_text = $campaign->button_text;
    $this->button_link = $campaign->button_link;
    $this->bg_gradient = $campaign->bg_gradient;
    $this->start_date = $campaign->start_date ? $campaign->start_date->format('Y-m-d\TH:i') : '';
    $this->end_date = $campaign->end_date ? $campaign->end_date->format('Y-m-d\TH:i') : '';
    $this->is_active = $campaign->is_active;

    $this->isModalOpen = true;
};

$saveCampaign = function () {
    $this->validate();

    $data = [
        'title' => $this->title,
        'subtitle' => $this->subtitle !== '' ? $this->subtitle : null,
        'description' => $this->description !== '' ? $this->description : null,
        'badge_text' => $this->badge_text !== '' ? $this->badge_text : null,
        'promo_tag' => $this->promo_tag !== '' ? $this->promo_tag : null,
        'button_text' => $this->button_text,
        'button_link' => $this->button_link,
        'bg_gradient' => $this->bg_gradient,
        'start_date' => $this->start_date !== '' ? $this->start_date : null,
        'end_date' => $this->end_date !== '' ? $this->end_date : null,
        'is_active' => $this->is_active,
    ];

    if ($this->editingCampaignId) {
        $campaign = Campaign::findOrFail($this->editingCampaignId);
        $campaign->update($data);
        session()->flash('message', 'Campaign berhasil diperbarui!');
    } else {
        Campaign::create($data);
        session()->flash('message', 'Campaign berhasil dibuat!');
    }

    $this->isModalOpen = false;
};

$deleteCampaign = function ($id) {
    $campaign = Campaign::findOrFail($id);
    $campaign->delete();
    session()->flash('message', 'Campaign berhasil dihapus!');
};

$toggleActive = function ($id) {
    $campaign = Campaign::findOrFail($id);
    $campaign->update(['is_active' => !$campaign->is_active]);
};

$getCampaignsProperty = function () {
    $query = Campaign::query();
    if (!empty($this->search)) {
        $query->where('title', 'like', '%' . $this->search . '%')
              ->orWhere('promo_tag', 'like', '%' . $this->search . '%');
    }
    return $query->orderBy('created_at', 'desc')->get();
};
?>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-gray-100 tracking-tight">Manajemen Campaign Banners</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola event promo musiman (Idul Fitri, Natal, dll.) dan visual banner slideshow yang tampil di beranda belanja.</p>
            </div>
            
            <button 
                wire:click="openCreateModal" 
                class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow transition duration-150 cursor-pointer"
            >
                Tambah Campaign
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
                    placeholder="Cari campaign..." 
                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                />
            </div>
        </div>

        <!-- Campaigns Table -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                    <thead class="bg-gray-50/50 dark:bg-gray-700/30">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Campaign</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Badge & Tag</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tampilan Gradasi</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Masa Aktif</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->campaigns as $campaign)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/10">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-extrabold text-sm text-gray-900 dark:text-gray-100">{{ $campaign->title }}</span>
                                        @if($campaign->subtitle)
                                            <span class="text-xs text-gray-500 dark:text-gray-450 mt-0.5">{{ $campaign->subtitle }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        @if($campaign->badge_text)
                                            <span class="inline-flex items-center px-2 py-0.5 text-xxs font-black rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                {{ $campaign->badge_text }}
                                            </span>
                                        @endif
                                        @if($campaign->promo_tag)
                                            <span class="inline-flex items-center px-2 py-0.5 text-xxs font-black rounded bg-rose-100 dark:bg-rose-950/40 text-rose-800 dark:text-rose-455">
                                                🏷️ {{ $campaign->promo_tag }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                                        {{ $campaign->bg_gradient === 'indigo' ? 'bg-indigo-50 dark:bg-indigo-950/30 text-indigo-800 dark:text-indigo-300' : '' }}
                                        {{ $campaign->bg_gradient === 'emerald' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300' : '' }}
                                        {{ $campaign->bg_gradient === 'rose' ? 'bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300' : '' }}
                                        {{ $campaign->bg_gradient === 'amber' ? 'bg-amber-50 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300' : '' }}
                                        {{ $campaign->bg_gradient === 'purple' ? 'bg-purple-50 dark:bg-purple-950/30 text-purple-800 dark:text-purple-300' : '' }}
                                    ">
                                        {{ ucfirst($campaign->bg_gradient) }} Gradient
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex flex-col text-xs">
                                        @if($campaign->start_date)
                                            <span>Mulai: {{ $campaign->start_date->format('d M Y H:i') }}</span>
                                        @else
                                            <span>Mulai: Instan</span>
                                        @endif
                                        @if($campaign->end_date)
                                            <span class="mt-0.5 {{ $campaign->end_date->isPast() ? 'text-rose-600 font-semibold' : '' }}">
                                                Selesai: {{ $campaign->end_date->format('d M Y H:i') }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 italic mt-0.5">Selamanya</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $now = Carbon\Carbon::now();
                                        $isExpired = $campaign->end_date && $campaign->end_date->isPast();
                                        $isNotStarted = $campaign->start_date && $campaign->start_date->isFuture();
                                        $statusClass = 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-350';
                                        $statusText = 'Aktif';
                                        if (!$campaign->is_active) {
                                            $statusClass = 'bg-gray-100 text-gray-800 dark:bg-gray-700/50 dark:text-gray-400';
                                            $statusText = 'Nonaktif';
                                        } elseif ($isExpired) {
                                            $statusClass = 'bg-rose-50 text-rose-800 dark:bg-rose-950/20 dark:text-rose-400';
                                            $statusText = 'Kadaluarsa';
                                        } elseif ($isNotStarted) {
                                            $statusClass = 'bg-amber-50 text-amber-800 dark:bg-amber-950/20 dark:text-amber-400';
                                            $statusText = 'Terjadwal';
                                        }
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        <button wire:click="toggleActive({{ $campaign->id }})" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $campaign->is_active ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                            <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $campaign->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                        </button>
                                        <span class="px-2 py-0.5 rounded-full text-xxs font-extrabold {{ $statusClass }}">
                                            {{ $statusText }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold gap-2">
                                    <button wire:click="openEditModal({{ $campaign->id }})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-950 dark:hover:text-indigo-300 cursor-pointer mr-3">
                                        Edit
                                    </button>
                                    <button wire:click="deleteCampaign({{ $campaign->id }})" wire:confirm="Apakah Anda yakin ingin menghapus campaign ini?" class="text-rose-600 dark:text-rose-400 hover:text-rose-950 dark:hover:text-rose-300 cursor-pointer">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Campaign tidak ditemukan. Klik "Tambah Campaign" untuk membuat baru.
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
                    {{ $editingCampaignId ? 'Edit Campaign Banner' : 'Buat Campaign Banner' }}
                </h3>

                <form wire:submit.prevent="saveCampaign" class="space-y-4 py-4 overflow-y-auto flex-1 pr-1">
                    <!-- Title -->
                    <div>
                        <x-input-label for="campaign_title" :value="__('Judul Campaign')" />
                        <x-text-input wire:model="title" id="campaign_title" placeholder="contoh: Idul Fitri Mega Promo!" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>

                    <!-- Subtitle -->
                    <div>
                        <x-input-label for="campaign_subtitle" :value="__('Sub-judul')" />
                        <x-text-input wire:model="subtitle" id="campaign_subtitle" placeholder="contoh: Diskon Hingga 70%" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('subtitle')" class="mt-1" />
                    </div>

                    <!-- Description -->
                    <div>
                        <x-input-label for="campaign_description" :value="__('Deskripsi')" />
                        <textarea wire:model="description" id="campaign_description" placeholder="Deskripsi ringkas mengenai promo campaign ini..." rows="2" class="block mt-1 w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 text-sm"></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Badge Text -->
                        <div>
                            <x-input-label for="campaign_badge" :value="__('Teks Badge')" />
                            <x-text-input wire:model="badge_text" id="campaign_badge" placeholder="contoh: FESTIVAL HARI RAYA" class="block mt-1 w-full" type="text" />
                            <x-input-error :messages="$errors->get('badge_text')" class="mt-1" />
                        </div>

                        <!-- Promo Tag -->
                        <div>
                            <x-input-label for="campaign_tag" :value="__('Pilih Promo Tag (Opsional)')" />
                            <select wire:model="promo_tag" id="campaign_tag" class="block mt-1 w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">Tanpa Tag</option>
                                <option value="Flash Sale">Flash Sale</option>
                                <option value="Cashback">Cashback</option>
                                <option value="Diskon Besar">Diskon Besar</option>
                                <option value="New Arrival">New Arrival</option>
                                <option value="Idul Fitri">Idul Fitri</option>
                                <option value="Ramadhan">Ramadhan</option>
                                <option value="Natal">Natal</option>
                                <option value="Imlek">Imlek</option>
                                <option value="Tahun Baru">Tahun Baru</option>
                            </select>
                            <x-input-error :messages="$errors->get('promo_tag')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Button Text -->
                        <div>
                            <x-input-label for="btn_text" :value="__('Teks Tombol')" />
                            <x-text-input wire:model="button_text" id="btn_text" class="block mt-1 w-full" type="text" required />
                            <x-input-error :messages="$errors->get('button_text')" class="mt-1" />
                        </div>

                        <!-- Button Link -->
                        <div>
                            <x-input-label for="btn_link" :value="__('Link Tombol')" />
                            <x-text-input wire:model="button_link" id="btn_link" class="block mt-1 w-full" type="text" required />
                            <x-input-error :messages="$errors->get('button_link')" class="mt-1" />
                        </div>
                    </div>

                    <!-- Background Gradient Selection -->
                    <div>
                        <x-input-label for="bg_grad" :value="__('Warna Gradasi Background')" />
                        <select wire:model="bg_gradient" id="bg_grad" class="block mt-1 w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="indigo">Indigo (Ungu-Pink)</option>
                            <option value="emerald">Emerald (Hijau-Biru)</option>
                            <option value="rose">Rose (Merah-Orange)</option>
                            <option value="amber">Amber (Kuning-Oranye)</option>
                            <option value="purple">Purple (Ungu-Merah)</option>
                        </select>
                        <x-input-error :messages="$errors->get('bg_gradient')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Start Date -->
                        <div>
                            <x-input-label for="start_dt" :value="__('Tanggal Mulai')" />
                            <input wire:model="start_date" id="start_dt" type="datetime-local" class="block mt-1 w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>

                        <!-- End Date -->
                        <div>
                            <x-input-label for="end_dt" :value="__('Tanggal Berakhir')" />
                            <input wire:model="end_date" id="end_dt" type="datetime-local" class="block mt-1 w-full border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-950 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                        </div>
                    </div>

                    <!-- Active state checkbox -->
                    <div class="flex items-center gap-2 mt-2">
                        <input wire:model="is_active" id="active_camp" type="checkbox" class="rounded border-gray-300 text-indigo-650 shadow-sm focus:ring-indigo-500" />
                        <x-input-label for="active_camp" :value="__('Aktifkan Banner')" />
                    </div>
                </form>

                <div class="flex justify-end gap-3 border-t border-gray-50 dark:border-gray-700 pt-4 mt-2">
                    <button wire:click="$set('isModalOpen', false)" class="px-4 py-2 text-sm font-semibold rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                        Batal
                    </button>
                    <button wire:click="saveCampaign" class="px-4 py-2 text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow transition">
                        Simpan
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
