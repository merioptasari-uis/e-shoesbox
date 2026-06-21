<?php

use App\Livewire\Actions\Logout;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\computed;
use function Livewire\Volt\on;

$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/', navigate: true);
};

$cartCount = computed(function () {
    return Auth::check()
        ? CartItem::where('user_id', Auth::id())->sum('quantity')
        : 0;
});

on(['cart-updated' => function () {
    // refreshes the navigation bar
}]);

?>

<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="url('/')" :active="request()->is('/')" wire:navigate>
                        {{ __('Shop') }}
                    </x-nav-link>
                    @if (auth()->check())
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </x-nav-link>
                        @if (auth()->user()->isAdmin())
                            <x-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')" wire:navigate>
                                {{ __('Products') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.orders')" :active="request()->routeIs('admin.orders')" wire:navigate>
                                {{ __('Orders') }}
                            </x-nav-link>
                            <x-nav-link :href="route('admin.vouchers')" :active="request()->routeIs('admin.vouchers')" wire:navigate>
                                {{ __('Vouchers') }}
                            </x-nav-link>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Settings / Guest Links -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-4">
                @if (auth()->check())
                    <!-- Cart Toggle Button -->
                    <button 
                        @click="cartOpen = !cartOpen"
                        class="relative p-2 text-gray-505 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-305 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150"
                        title="View Cart"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        @if($this->cartCount > 0)
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-extrabold leading-none text-white bg-indigo-600 rounded-full">
                                {{ $this->cartCount }}
                            </span>
                        @endif
                    </button>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile')" wire:navigate>
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <button wire:click="logout" class="w-full text-start">
                                <x-dropdown-link>
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </button>
                        </x-slot>
                    </x-dropdown>
                @else
                    <div class="space-x-4">
                        <a href="{{ route('login') }}" class="text-sm text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100" wire:navigate>Login</a>
                        <a href="{{ route('register') }}" class="text-sm text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100" wire:navigate>Register</a>
                    </div>
                @endif
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="url('/')" :active="request()->is('/')" wire:navigate>
                {{ __('Shop') }}
            </x-responsive-nav-link>
            @if (auth()->check())
                <x-responsive-nav-link :href="url('/cart')" :active="request()->is('cart')" wire:navigate>
                    {{ __('Cart') }} ({{ $this->cartCount }})
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                @if (auth()->user()->isAdmin())
                    <x-responsive-nav-link :href="route('admin.products')" :active="request()->routeIs('admin.products')" wire:navigate>
                        {{ __('Products') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.orders')" :active="request()->routeIs('admin.orders')" wire:navigate>
                        {{ __('Orders') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.vouchers')" :active="request()->routeIs('admin.vouchers')" wire:navigate>
                        {{ __('Vouchers') }}
                    </x-responsive-nav-link>
                @endif
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            @if (auth()->check())
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800 dark:text-gray-200" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                    <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile')" wire:navigate>
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <button wire:click="logout" class="w-full text-start">
                        <x-responsive-nav-link>
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </button>
                </div>
            @else
                <div class="mt-3 space-y-1 px-4">
                    <x-responsive-nav-link :href="route('login')" wire:navigate>
                        {{ __('Login') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('register')" wire:navigate>
                        {{ __('Register') }}
                    </x-responsive-nav-link>
                </div>
            @endif
        </div>
    </div>
</nav>
