# e-shoesbox 👟📦

[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![Livewire Version](https://img.shields.io/badge/Livewire-4.x-blue.svg)](https://laravel-livewire.com)
[![TailwindCSS Version](https://img.shields.io/badge/TailwindCSS-4.x-38bdf8.svg)](https://tailwindcss.com)

A modern, high-fidelity, and feature-rich online shoe marketplace application built on top of Laravel 13, Livewire 4 (Volt), TailwindCSS 4, and MySQL. The system is designed to provide a premium shopping experience featuring interactive product catalogs, real-time variant selections, automated vouchers, integrated RajaOngkir shipping calculation, and a seamless Midtrans payment flow.

---

## 👥 Tim Pengembang (Development Team)

Proyek ini dikembangkan sebagai tugas kuliah oleh kelompok:
- **Meri Optasari** (Ketua Kelompok)
- **Reza Saponna** (Anggota Kelompok)

---

## ✨ Features

- **Storefront / Customer Side**:
  - **Dynamic Product Catalog**: Premium marketplace-style listing with instant live search, category filters, and price range filters.
  - **Interactive Details Modal**: Multi-image thumbnail gallery, size & color variants selector, and real average ratings.
  - **Product Reviews**: Customer-written review entries with verified buyer badges.
  - **Dynamic Flash Sales**: Real-time sales countdowns, automated scheduling, and visual sold progress bars.
  - **Shopping Cart & Checkout**: Interactive mini-cart drawer, destination/province/city RajaOngkir address selection, and automated courier shipping cost calculation.
  - **Promotion & Vouchers**: Stacked checkout discount codes supporting free shipping (e.g., `FREEONGKIR`) and percentage/fixed discounts (e.g., `COBAINBARU`).
  - **Order Timeline Tracker**: Stepper timeline showing payment verification, processing, shipping, and package arrival.
  - **Mock Payment Simulation**: Fully interactive local payment sandbox simulator to test checkout flows without hitting real APIs.

- **Administration / Admin Control Panel**:
  - **Analytics Reports Dashboard**: Beautiful sales charts (powered by Chart.js) tracking 7-day revenue, category distribution, and top-selling products.
  - **Product & Variant CRUD**: Dynamic variant control mapping color/size combinations and image assets.
  - **Order Management Panel**: Advanced filtering, Indonesian phone-number WhatsApp integration, and one-click shipping tracking code activation.
  - **Invoice & Shipping Label**: Print-friendly invoice generation for buyers and warehouse shipping labels for administrators.
  - **Campaigns & Slideshows**: Homepage hero banner manager with custom expiration and background styles.
  - **Vouchers CRUD**: Admin management panel to create and configure active coupon codes.

---

## 🛠️ Tech Stack & Requirements

- **PHP**: `^8.3`
- **Database**: MySQL `^8.0` or MariaDB
- **Composer**: `^2.6`
- **Node.js & NPM**: Node `^20.x` & NPM `^10.x`
- **Laravel Framework**: `^13.x`
- **Livewire**: `^4.x` (featuring Volt & Blaze)
- **CSS Framework**: TailwindCSS `^4.x`
- **Bundler**: Vite

### 🗄️ Arsitektur Database (Database Schema)

Aplikasi ini menggunakan skema database relasional untuk menyimpan data toko secara terstruktur:
- **users**: Data otentikasi untuk Pelanggan (Customer) dan Administrator (Admin).
- **products**: Data produk sepatu, deskripsi, harga dasar, berat produk, promo tag, dan penjadwalan Flash Sale.
- **product_variants**: Menyimpan detail varian spesifik berdasarkan kombinasi Ukuran (Size), Warna (Hex Color), dan tingkat stok masing-masing.
- **campaigns**: Data banner promosi interaktif di halaman beranda dengan kustomisasi gradien background CSS, teks badge, dan emoji.
- **vouchers**: Data kupon promo aktif pendukung potongan ongkos kirim (Bebas Ongkir) dan potongan harga belanja.
- **orders & order_items**: Rincian transaksi pembelian, alamat penerima, pilihan kurir, nomor resi pengiriman, serta referensi kode voucher.
- **payments**: Pencatatan data pembayaran terintegrasi Midtrans (Virtual Account, GoPay, Credit Card) beserta status pembayaran.
- **reviews**: Rating bintang dan ulasan umpan balik dari pembeli terverifikasi (Verified Buyer).

---

## 🚀 Local Deployment Guide (Laragon)

This guide walks you through setting up and running the application locally on Windows using the **Laragon** development suite.

### Step 1: Install Laragon & Prerequisites
Make sure you have the following installed on your machine:
1. **Laragon**: Download and install [Laragon Wamp](https://laragon.org/download/) (Full edition recommended, which includes Apache, PHP 8+, MySQL, and git).
2. **PHP 8.3+**: Ensure your Laragon PHP version is updated to PHP 8.3.
3. **Composer**: Ensure Composer is configured globally.
4. **Node.js**: Install Node.js & NPM globally.

### Step 2: Clone or Copy the Repository
Place this project inside Laragon's web root directory (default path: `C:\laragon\www\`):
```bash
# Path should be: C:\laragon\www\e-shoesbox
```
Once copied, start Laragon and click **"Start All"**. Laragon will automatically detect the folder and configure a local virtual host: `http://e-shoesbox.test` (or `http://e-shoesbox.local`).

### Step 3: Setup Environment Configuration
Copy the `.env.example` file in the root folder and rename it to `.env`:
```bash
# In your terminal inside the project root:
copy .env.example .env
```
Open the `.env` file and configure your database and service integrations.

> [!WARNING]
> **Security Guard**: Never commit your real `.env` file containing secrets, private keys, or API tokens to your version control repository. Only use safe dummy configurations for production or public environments.

#### Database Settings:
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=e_shoesbox
DB_USERNAME=root
DB_PASSWORD=
```

#### API Integrations Setup (Sample / Dummy Values):
```ini
# Midtrans Credentials (Sandbox defaults)
MIDTRANS_MERCHANT_ID=your_midtrans_merchant_id_sample
MIDTRANS_CLIENT_KEY=your_midtrans_client_key_sample
MIDTRANS_SERVER_KEY=your_midtrans_server_key_sample
MIDTRANS_IS_PRODUCTION=false

# RajaOngkir Shipping Cost API Settings (Starter API default)
RAJAONGKIR_API_KEY=your_rajaongkir_api_key_sample
RAJAONGKIR_PACKAGE_TYPE=starter
```

### Step 4: Create the Database
1. Open Laragon, click **"Database"** (HeidiSQL will open).
2. Connect to your local MySQL instance (host: `127.0.0.1`, user: `root`, no password by default).
3. Right-click on the session name -> **Create new** -> **Database**.
4. Name the database: `e_shoesbox` (or the name you specified in `.env` under `DB_DATABASE`).

### Step 5: Install Dependencies & Setup App Key
Open your terminal inside the project root (`C:\laragon\www\e-shoesbox`) and run:
```bash
# Install PHP Composer packages
composer install

# Generate application key
php artisan key:generate
```

### Step 6: Create Storage Symlink & Seed Initial Data
Run the migrations and database seeders. The database seeders will populate the tables with initial provinces, cities, products, variants, and sandbox discount coupons.
```bash
# Link public storage directory
php artisan storage:link

# Run fresh migrations and seed initial data
php artisan migrate:fresh --seed
```
*Note: If `RAJAONGKIR_API_KEY` is not set or valid, the seeder automatically falls back to a deterministic offline provincial dataset (`database/data/rajaongkir_locations.json`) without failing or making network requests.*

### Step 7: Build Frontend Assets
Run the asset builder script to build TailwindCSS 4 and compile assets:
```bash
# Install NPM dependencies
npm install

# Compile assets for production
npm run build
```
Alternatively, for hot-reloading during frontend customization:
```bash
# Start Vite development server
npm run dev
```

---

## 🌐 Virtual Hosts & URL Rewriting (.htaccess)

### Option A: Using Laragon Virtual Hosts (Recommended)
Laragon automatically provisions virtual hosts for folders under `www/`. By default:
- Project folder name: `e-shoesbox`
- Auto-generated domain: `http://e-shoesbox.test`
Laragon maps the DocumentRoot of `e-shoesbox.test` to the `/public` subdirectory of your Laravel folder. This is the cleanest and most secure approach.

### Option B: Local Root Subdirectory Access (`http://localhost/e-shoesbox`)
If you access the project directly via `http://localhost/e-shoesbox` without configuring virtual hosts, you need a way to forward web requests to Laravel's `/public` folder automatically.

We have included a `.htaccess` file in the **project root directory** that automatically redirects all incoming Apache requests to the `/public` folder without requiring `/public/` in the URL structure:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Forward all requests to the public/ folder
    RewriteRule ^$ public/ [L]
    RewriteRule (.*) public/$1 [L]
</IfModule>
```
*Make sure Apache's `mod_rewrite` module is enabled in your Laragon configuration (Laragon -> Apache -> Apache modules -> check `rewrite_module`).*

## 👥 Default Testing Accounts (Akun Demo)

You can log in using the following pre-seeded credentials for local testing:

- **Store Administrator**:
  - **Email**: `admin@e-shoesbox.com`
  - **Password**: `password`
  - **Role**: Admin (Akses panel admin di `/dashboard` atau menu laporan)

- **Demo Customer**:
  - **Email**: `customer@e-shoesbox.com`
  - **Password**: `password`
  - **Role**: Customer (Akses belanja dan lacak pesanan)

---

## 🎫 Default Seeded Vouchers (Testing Promos)

You can use the following default codes during cart checkout:
- `COBAINBARU`: Grants a percentage/flat discount.
- `FREEONGKIR`: Free shipping promotion (valid with a minimum spend of Rp 150.000).
- `DISKONHEBOH`: Promotional coupon for flash sale testing.

---

## 🔒 Security Best Practices

1. **API Credentials**: Ensure `MIDTRANS_SERVER_KEY` and `RAJAONGKIR_API_KEY` are kept strictly in your local `.env` file. Do not share or push `.env` to GitHub.
2. **Directory Permissions**: Laravel requires write access to the `storage/` and `bootstrap/cache/` directories. If you encounter permission errors on Apache, ensure these folders have read/write privileges.
3. **Environment**: For local development, keep `APP_ENV=local` and `APP_DEBUG=true`. For production deployments, change these to `production` and `false` respectively.

---

## 🧪 Pengujian Sistem & Kualitas Kode (Testing & QA)

Untuk menjamin keandalan sistem dan keselarasan kode sesuai standar industri (sangat berguna untuk laporan tugas kuliah), Anda dapat menjalankan perintah verifikasi berikut:

1. **Linter & Code Formatting (Laravel Pint)**:
   ```bash
   composer run lint
   ```
   Secara otomatis merapikan penulisan kode PHP agar sesuai dengan standar PSR-12.

2. **Pengujian Fungsional (Pest PHP)**:
   ```bash
   composer run test
   ```
   Menjalankan 75 suite test otomatis yang memverifikasi fungsionalitas keranjang belanja, auto-apply voucher diskon, restock stok varian saat order batal/expired, dan integrasi response webhook pembayaran.
