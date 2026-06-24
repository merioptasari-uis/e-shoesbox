# e-shoesbox 👟📦

[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-red.svg)](https://laravel.com)
[![Livewire Version](https://img.shields.io/badge/Livewire-4.x-blue.svg)](https://laravel-livewire.com)
[![TailwindCSS Version](https://img.shields.io/badge/TailwindCSS-4.x-38bdf8.svg)](https://tailwindcss.com)

A modern, high-fidelity, and feature-rich online shoe marketplace application built on top of Laravel 13, Livewire 4 (Volt), TailwindCSS 4, and MySQL. The system is designed to provide a premium shopping experience featuring interactive product catalogs, real-time variant selections, automated vouchers, integrated RajaOngkir shipping calculation, and a seamless Midtrans payment flow.

---

## 👥 Development Team

This project was developed as a university course project by:
- **Meri Optasari** (Group Leader)
- **Reza Saponna** (Group Member)

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

### 🗄️ Database Schema

The application utilizes a relational database schema to store store data in a structured manner:
- **users**: Authentication data for Customers and Administrators (Admin).
- **products**: Shoe product details, descriptions, base prices, product weights, promo tags, and Flash Sale schedules.
- **product_variants**: Stores specific variant details based on combinations of Size, Hex Color, and their respective stock levels.
- **campaigns**: Interactive homepage promotional banners with custom CSS background gradients, badge texts, and emojis.
- **vouchers**: Active coupon promotion data supporting free shipping and order discounts.
- **orders & order_items**: Transaction details, recipient addresses, courier choices, shipping tracking numbers, and voucher code references.
- **payments**: Integrated Midtrans payment records (Virtual Account, GoPay, Credit Card) along with payment status.
- **reviews**: Star ratings and feedback reviews from verified buyers.

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

## 👥 Default Testing Accounts

You can log in using the following pre-seeded credentials for local testing:

- **Store Administrator**:
  - **Email**: `admin@e-shoesbox.com`
  - **Password**: `password`
  - **Role**: Admin (Accesses the admin dashboard at `/dashboard` or reports menu)

- **Demo Customer**:
  - **Email**: `customer@e-shoesbox.com`
  - **Password**: `password`
  - **Role**: Customer (Accesses storefront shopping and order tracking)

---

## 🎫 Default Seeded Vouchers (Testing Promos)

You can use the following default codes during cart checkout:
- `COBAINBARU`: Grants a percentage/flat discount.
- `FREEONGKIR`: Free shipping promotion (valid with a minimum spend of Rp 150,000).
- `DISKONHEBOH`: Promotional coupon for flash sale testing.

---

## 🔒 Security Best Practices

1. **API Credentials**: Ensure `MIDTRANS_SERVER_KEY` and `RAJAONGKIR_API_KEY` are kept strictly in your local `.env` file. Do not share or push `.env` to GitHub.
2. **Directory Permissions**: Laravel requires write access to the `storage/` and `bootstrap/cache/` directories. If you encounter permission errors on Apache, ensure these folders have read/write privileges.
3. **Environment**: For local development, keep `APP_ENV=local` and `APP_DEBUG=true`. For production deployments, change these to `production` and `false` respectively.

---

## 🧪 Testing & QA

To ensure system reliability and code quality alignment with industry standards (highly useful for university assignment reports), you can run the following verification commands:

1. **Linter & Code Formatting (Laravel Pint)**:
   ```bash
   composer run lint
   ```
   Automatically formats PHP code to comply with PSR-12 standards.

2. **Functional Testing (Pest PHP)**:
   ```bash
   composer run test
   ```
   Runs 75 automated test suites verifying cart functionality, voucher auto-application, stock variant restoration on order cancellation/expiry, and webhook integration.

---

## 🔧 Troubleshooting Guide (Windows & Other Platforms)

If you encounter issues during installation or when running the application locally, here are some common solutions:

### 1. `composer install` Fails on Windows
On some Windows operating systems, the `composer install` command may fail due to local PHP extension mismatches or platform requirements.
* **Solution**: Run the following update command to align the lock file with the dependencies available on your Windows system:
  ```bash
  composer update
  ```
  Alternatively, you can ignore local system/extension constraints during dependency installation:
  ```bash
  composer install --ignore-platform-reqs
  ```

### 2. Payment Gateway Always Routes to "Midtrans Simulator" (Not the Real Snap Popup)
The application is designed to automatically fall back to the local payment simulation modal (Mock Modal) if the connection to the Midtrans Sandbox API fails or is misconfigured.

On Windows, the most common cause is the **absence of a CA SSL certificate** in your local PHP installation, which causes HTTPS handshake failures (cURL error 60).
* **cURL SSL Error Solution (Windows)**:
  1. Download the latest CA certificate (`cacert.pem`) from the official curl site: [curl.se/docs/caextract.html](https://curl.se/docs/caextract.html).
  2. Save the `cacert.pem` file in your PHP directory (for example, in Laragon: `C:\laragon\bin\php\php-8.x.x-...\extras\ssl\cacert.pem` or another local PHP folder).
  3. Open your `php.ini` configuration file (via Laragon -> PHP -> php.ini), search for the options `curl.cainfo` and `openssl.cafile`, and point them to the certificate location:
     ```ini
     curl.cainfo = "C:/laragon/bin/php/php-8.x.x-.../extras/ssl/cacert.pem"
     openssl.cafile = "C:/laragon/bin/php/php-8.x.x-.../extras/ssl/cacert.pem"
     ```
     *(Use forward slashes `/` or escaped double backslashes `\\` for path configurations in Windows)*.
  4. Save `php.ini` and restart all services in Laragon (click **Stop** then **Start All**).
* **Laravel Config Cache Solution**:
  If you have recently changed the Midtrans API keys in the `.env` file but they are not being read by Laravel, run the configuration clear command:
  ```bash
  php artisan optimize:clear
  ```

### 3. Database Connection Issues / RajaOngkir Module Offline
* **MySQL Database**: Ensure that the MySQL service status in Laragon is active (Start All). Verify that the database name in the `.env` file (`DB_DATABASE`) matches the database you created in HeidiSQL (default: `e_shoesbox`).
* **RajaOngkir**: If you do not enter a `RAJAONGKIR_API_KEY` in the `.env` file, the seeder and shipping cost calculator will automatically switch to using offline regional data (`database/data/rajaongkir_locations.json`) without interrupting the installation or checkout process.
