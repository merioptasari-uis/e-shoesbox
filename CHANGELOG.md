# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Created a new database migration adding `shipped_at` and `completed_at` columns to the `orders` table.
- Implemented model boot event listeners in `Order.php` to auto-populate `shipped_at` and `completed_at` timestamps upon status transitions.
- Added bulk auto-completion utility button "Selesaikan Otomatis (+14 Hari)" on the Admin Order Management panel, enabling bulk transitions of orders shipped >= 14 days ago to "completed".
- Added a Pest feature test verifying bulk auto-completion of shipping orders.
- Created a Pest feature test verifying checkout pessimistic locking and variant-aware stock restoration.
- Documented default pre-seeded admin and customer testing credentials in `README.md`.
- Created a root-level `.htaccess` file directing Apache traffic to the `/public` folder for custom local subdirectory deployments.
- Created a comprehensive `README.md` containing local Laragon deployment documentation, stack descriptions, virtual hosts mappings, integration guides, and security constraints.
- Created `VoucherSeeder` class to seed default sandbox vouchers (`COBAINBARU`, `FREEONGKIR` for Bebas Ongkir with minimum spend Rp 150.000, and `DISKONHEBOH`).
- Added dynamic Flash Sale scheduling columns `flash_sale_start` and `flash_sale_end` to the `products` table, model casts, and fillable attributes.
- Implemented datetime-local input fields in the admin products edit form for precise scheduling of flash sale start and end times.

### Fixed
- Fixed checkout race conditions under high concurrency by introducing pessimistic row locking (`lockForUpdate`) on product and variant records during order placement.
- Fixed incomplete stock restoration where order cancellation/expiry only recovered parent product stock, now correctly restoring specific variant stock levels in both the Midtrans webhook and Admin panel.
- Fixed the large vertical gap between the main product image and thumbnails in the shop product detail modal by replacing 'justify-between' with a standard 'gap-4' flex layout.

### Changed
- Refactored storefront Flash Sale section to display products dynamically using active scheduler dates (`flash_sale_start` <= now <= `flash_sale_end`).
- Updated storefront countdown timer to be fully dynamic, counting down to the nearest expiring active flash sale.
- Redesigned color swatches and sizing selectors in storefront details modal with high-fidelity circular indicators and diagonal strike-through out-of-stock styles.
- Reduced love/wishlist icon size to `h-4 w-4` to fit balancedly inside the product card circle button.
- Restricted storefront Flash Sale section to only show products explicitly tagged with promo_tag 'Flash Sale'.
- Refactored storefront Flash Sale countdown timer to dynamically calculate time remaining until midnight instead of a static hardcoded time.
- Added 'Flash Sale' label to the admin promo tag selection dropdown to enable explicit control.
- Improved storefront product details modal with visual color swatches and status badges with pulse/bounce animations.
- Refactored admin product edit modal to a wider 2-column grid layout (max-w-4xl) separating general information on the left and price/stock/weight/photos on the right to eliminate vertical scrolling.
- Refined admin navigation menu (desktop and mobile responsive view) to hide the duplicate customer dashboard link and display the reports analytics 'Laporan' link exclusively.
- Redesigned storefront product catalog cards on Belanja page with a premium glassmorphic feel, subtle hover translate/glow animations, dynamic color variant dots, aligned name container heights, and interactive variant action icons.

### Added
- Implemented print-friendly invoice export functionality for customer order-details page.
- Added print-friendly warehouse shipping label printing for administrators in the orders management sidebar.
- Added direct 'Laporan' shortcut link to admin navigation and mobile sidebar menus pointing to the analytics reports dashboard.
- Implemented comprehensive Admin Order Management panel with dynamic Search & Filters (by Invoice, Customer, Tracking Resi, Email, Order Status, Payment Status, and Courier).
- Added Auto-Status Promotion to automatically change order status to 'shipping' once a non-empty Tracking Number (Resi) is saved.
- Implemented a Detailed Payment Summary card displaying Midtrans Transaction ID, Payment Type, Bank/Issuer details, and callback timestamps.
- Added Quick Action utilities in the admin order sidebar, including Copy Invoice, Copy Tracking Number, and one-click direct WhatsApp chat link formatting Indonesian phone numbers.
- Implemented complete local RajaOngkir shipping locations JSON dataset (`database/data/rajaongkir_locations.json`) containing all 34 provinces and 501 cities of Indonesia.
- Updated `RajaOngkirSeeder` to securely load geographical data from the local JSON dataset as a fallback when `RAJAONGKIR_API_KEY` is not present, enabling zero-network deterministic seeding.
- Added "Buy Now" (Beli Langsung) direct checkout button in the product details modal, bypassing the persistent database cart.
- Implemented an interactive Mock Midtrans Sandbox Payment Simulation modal in the order details page, allowing developers and customers to test simulated payments (Virtual Account, GoPay, Credit Card) when sandbox API keys are invalid or missing.
- Added a "Pesanan Diterima" (Confirm Order Received) button on the order details view when status is "Dalam Pengiriman", allowing buyers to mark order status as "Selesai" (completed) and unlock product review capabilities.
- Implemented a responsive visual order progress timeline stepper at the top of the Customer Order Details page, dynamically tracking created, paid, processing, shipping, and completed milestones with custom indicators.
- Added a visual automatic voucher recommendations section in the checkout sidebar, allowing users to apply eligible shipping or product vouchers in one click, and providing dynamic spending reminders to encourage cart value increases.
- Refactored the dashboard route to a Livewire Volt component and built a visual analytics sales reports dashboard for the admin using Chart.js, featuring 7-day revenue trends, product category distribution donut charts, and top 5 best selling products progress charts.

### Fixed
- Fixed DI Yogyakarta province ID mapping from legacy incorrect ID 39 to standard RajaOngkir ID 5 in seeder fallback and database records.
- Refined UI/UX of "+ Keranjang" (Add to Cart) and "Beli Langsung" (Buy Now) buttons in the product details modal, resolving the oversized layout and shopping cart SVG icon overflow.
- Fixed variant selection warning notification popup appearing immediately when opening the product details modal from the catalog page instead of only when trying to add/buy.
- Included detailed product items, quantities, shipping costs, and voucher discounts in the Midtrans Snap request payload to display them on the payment page and dashboard.
- Added a visual loading spinner and pulse skeleton loader on the checkout page (`cart.blade.php`) while fetching RajaOngkir shipping rates to improve user feedback.
- Redesigned the customer reviews card list and write-review form in `index.blade.php` to use premium, consistent typography and standard Tailwind CSS colors, resolving the white text on white background readability issue in the input area.
- Enforced backend and frontend constraints restricting product reviews to verified customers who have a completed order for that specific product.
- Updated the product details review cards to display avatar bubbles and "Pembeli Terverifikasi" badges for authentic review representation.
- Fixed text contrast of review statistics ("95% pembeli") on the product details page by replacing invalid Tailwind color classes (`text-gray-550`, `dark:text-gray-450`) with standard gray variants.
- Fixed text contrast of standard text inputs, textarea description, select categories, and variant stock inputs in the admin edit product modal to ensure readability in both light and dark themes, and preventing them from blending with the modal background.
- Fixed the product variant and reviews feature test to simulate a completed order for the user before verifying review submission.

### Added
- Implemented dynamic database-backed Product Variant system (`product_variants` table) tracking individual stock, size, and color combinations.
- Built dynamic reviews system (`reviews` table) letting logged-in users post shoe comments and ratings directly in the details modal.
- Integrated real average rating, review count, and sales volume calculation derived from database reviews and completed/paid order history.
- Added live size/color selectors and instant stock validation in shop detail modal, cart drawer, cart checkout page, and admin products edit modal.
- Refactored admin products CRUD panel to allow listing, creating, editing, and deleting color/size/stock variants for any product.
- Implemented database-driven Campaign & Slideshow Event management system with support for background gradient styles, badge text, promo tag integration, and expiration constraints.
- Built Campaign CRUD administration panel under `/admin/campaigns` protected by `admin` middleware.
- Integrated dynamic active campaigns query on storefront homepage slideshow with automated date range check (`scopeActive`) and fallback slideshow slides when no campaigns are active.
- Overhauled the customer catalog page UI (`shop/index.blade.php`) to feel like a premium, feature-rich marketplace (Tokopedia/Shopee styling), implementing an auto-playing Alpine.js hero carousel banner, seasonal themed promotions, and visual coupon sidebar cards.
- Integrated min/max price range filters with instant reactive debounce inside the Livewire/Volt shop catalog search sidebar.
- Added copyable discount codes with instant clipboard write feedback (e.g., `COBAINBARU`).
- Implemented an Alpine.js-powered simulated Flash Sale countdown timer and visual sold progress bars showing items left to create urgency.
- Added mock customer reviews, sales counts, and average star ratings derived deterministically from the product ID to enhance authenticity.
- Added interactive color variant swatches, size selection buttons, and shipping estimates selectors inside the product details modal.
- Added catalog page pagination (12 items per page), custom sorting criteria (by promo/discounts), and themed holiday promo badges (Idul Fitri, Natal, Imlek, Tahun Baru) on the customer shoe catalog page.
- Added 25 additional realistic shoe products (totaling 28 products) across Running, Sneakers, and Casual categories inside database seeders to support volume testing on the shop page.
- Merged main product image and additional supplementary images inputs into a single unified gallery file input in the admin panel with support for setting images as main, swapping, and auto-promoting on deletion.
- Added live temporary image upload previews for main and additional images inside the admin product creation/edit form.
- Implemented multiple product supplementary image uploads in the Admin Products panel with live previews and delete actions.
- Configured SVG and ICO favicon links in app and guest layout templates.
- Implemented premium product details modal/panel with a multi-image thumbnail gallery selector on the customer shop catalog page.
- Fully localized all application UI views and user-facing notifications to Indonesian, including navigation, shopping cart, order details tracking, admin products, admin orders, admin vouchers, and authentication views.
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
- Fixed product detail sold count (`sales_count` attribute) to display actual paid, processing, and completed order items, removing mock fallback counts.
- Fixed Midtrans checkout Snap modal failing to open when navigated dynamically with `wire:navigate` by refactoring `cart.blade.php` to listen to `pay-order` via Alpine.js window event.
- Refactored `order-details.blade.php` to trigger the Midtrans Snap modal dynamically via an Alpine.js `@click` handler on the "Bayar Sekarang" button, eliminating DOMContentLoaded reload dependencies.
- Fixed PHPStan type warnings regarding `number_format` parameter types and relationship return type annotations in `Product`, `ProductVariant`, and `Review` models.
- Fixed product details modal failing to open after catalog updates by upgrading computed property getters to Livewire v3 `#[Computed]` attributes and adding unique `wire:key` attributes on loop elements.
- Fixed catalog layout and grid alignment on the main shop catalog page by correcting malformed HTML tags.
- Standardized all scattered non-standard Tailwind color class weights (e.g., gray-750, gray-850, indigo-650) to valid standard equivalents.
- Updated global application title name config to "e-shoesbox" across templates.
- Fixed admin products page syntax error by correctly importing and invoking `usesFileUploads` and `layout` using functional Volt syntax.
- Configured explicit layout mapping `layouts.app` on the admin orders class-based Volt component.
- Resolved "Call to undefined function layout()" error in admin view templates.
- Disabled email verification requirements across all routes and user registration.
- Removed `MustVerifyEmail` implementation from the `User` model.
- Removed unused verification controllers, view files, and email verification feature tests.
- Corrected invalid color weight typos (e.g., bg-gray-55, gray-750, gray-650, text-indigo-655, border-indigo-650) in all Blade views.
- Aligned form inputs and dropdown options background/borders to ensure proper styling across light and dark modes.
