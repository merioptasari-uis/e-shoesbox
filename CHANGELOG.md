# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Implemented stacked voucher system supporting free shipping and percentage/fixed discounts with expiry and usage checks.
- Created `provinces` and `cities` database tables and seeder utilizing RajaOngkir API.
- Implemented responsive Customer Shop shoe catalog page at `/` with search, category filtering, and price sorting.
- Developed persistent database-backed cart utilizing `cart_items` table with real-time stock limits.
- Built slide-out Mini-Cart drawer Livewire component accessible across pages.
- Created dedicated `/cart` page with recipient details, address inputs, shipping destination selectors, courier selectors, and RajaOngkir shipping rate estimates.
- Integrated Midtrans Snap Embedded JavaScript payment overlay for checkout.
- Built POST webhook receiver `/api/midtrans/notification` with CSRF protection bypassed.
- Created Order details status tracker view `/order/{order}` with active polling.
- Developed Admin Orders dashboard panel at `/admin/orders` to manage delivery tracking and fulfillments.
- Added direct seller discounts on products with custom database-level pricing and computed model attributes (`selling_price`, `has_discount`, `discount_percentage`).
- Created complete Admin Voucher CRUD management panel at `/admin/vouchers` for creating, editing, and deleting discount codes.
- Restructured `dashboard.blade.php` with a premium admin control panel (with metrics and quick action cards) and a customer order history tracker.
- Enhanced the product catalog cards with animated pulse badges, crossed-out original prices, and a green "Hemat" (savings) helper text.
- Aligned checkout pricing subtotal calculations and order placement item prices to use the product's active selling price.
- Synchronized brand logo component rendering to target custom public/logo.png brand asset across layout files.

### Fixed
- Fixed admin products page syntax error by correctly importing and invoking `usesFileUploads` and `layout` using functional Volt syntax.
- Configured explicit layout mapping `layouts.app` on the admin orders class-based Volt component.
- Resolved "Call to undefined function layout()" error in admin view templates.
- Disabled email verification requirements across all routes and user registration.
- Removed `MustVerifyEmail` implementation from the `User` model.
- Removed unused verification controllers, view files, and email verification feature tests.
- Corrected invalid color weight typos (e.g., bg-gray-55, gray-750, gray-650, text-indigo-655, border-indigo-650) in all Blade views.
- Aligned form inputs and dropdown options background/borders to ensure proper styling across light and dark modes.
