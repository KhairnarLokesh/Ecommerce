# Kalika Enterprises – Production Deployment Guide

This guide provides step-by-step deployment instructions for hosting the **Kalika Enterprises** web application on typical Linux shared PHP hosting (e.g. cPanel, Hostinger, GoDaddy, Bluehost, Namecheap) or VPS environments running PHP 8.x and MySQL 8.0 / MariaDB 10.4+.

---

## 1. Hosting Prerequisites

- **PHP**: Version 8.1 or 8.2 (8.0+ supported)
- **PHP Extensions**: `pdo_mysql`, `mbstring`, `openssl`, `json`, `gd`, `fileinfo`
- **Database**: MySQL 8.0+ or MariaDB 10.4+ (InnoDB engine support, `utf8mb4`)
- **Web Server**: Apache 2.4+ with `mod_rewrite`, `mod_headers`, and `mod_authz_core` enabled
- **SSL Certificate**: Let's Encrypt / cPanel AutoSSL (free)

---

## 2. Step-by-Step Deployment Procedure

### Step 1: Create the MySQL Database & User
1. Log in to your hosting control panel (cPanel / DirectAdmin).
2. Navigate to **MySQL Databases** (or **MySQL Database Wizard**).
3. Create a new database: e.g. `cpaneluser_kalika`.
4. Create a new database user: e.g. `cpaneluser_kuser` with a strong 16+ character password.
5. Assign the user to the database and grant **ALL PRIVILEGES**.
6. Note the database name, username, and password for Step 4.

### Step 2: Import Database Schema & Seed Data
1. Open **phpMyAdmin** from cPanel.
2. Select your newly created database (`cpaneluser_kalika`).
3. Click the **Import** tab:
   - First import [`database/schema.sql`](file:///e:/Freelance%20Project/database/schema.sql) (creates the 12 InnoDB tables).
   - Next import [`database/seed.sql`](file:///e:/Freelance%20Project/database/seed.sql) (populates confirmed brands, products, size variants, and settings).
4. Verify that 12 tables are visible in the left sidebar and that `brands` contains 7 rows.

### Step 3: Upload Project Files & Document Root Configuration

#### Option A: Standard Hosting with Configurable Document Root (Recommended)
1. Upload all project files to a folder above public webroot (e.g. `/home/username/kalika_app/`).
2. In cPanel **Domains** &rarr; **Document Root**, configure the primary domain `kalikaenterprises.com` to point to:
   ```
   /home/username/kalika_app/public
   ```
   *Benefit*: Internal directories (`config/`, `includes/`, `database/`, `docs/`, `logs/`) are located completely outside the public web root.

#### Option B: Shared Hosting with Fixed `public_html` Root
If your hosting plan locks the document root to `/home/username/public_html`:
1. Upload all project files directly into `/home/username/public_html/`.
2. The included root [`.htaccess`](file:///e:/Freelance%20Project/.htaccess) and subfolder `.htaccess` files will automatically block all direct browser access to `config/`, `database/`, `docs/`, `includes/`, and `logs/` with **HTTP 403 Forbidden**.
3. Create or verify root `.htaccess` rewrite rule to redirect root traffic to `/public/`:
   ```apache
   RewriteEngine On
   RewriteRule ^$ public/ [L]
   RewriteCond %{REQUEST_URI} !^/public/
   RewriteCond %{REQUEST_URI} !^/admin/
   RewriteRule ^(.*)$ public/$1 [L]
   ```

### Step 4: Configure Environment Credentials (`config.php`)
1. In the `config/` directory, copy `config.sample.php` to create `config.php`:
   ```bash
   cp config/config.sample.php config/config.php
   ```
2. Edit `config/config.php` with your live hosting values:
   ```php
   // Set to production mode
   define('APP_ENV', 'production');

   // Live Database Credentials
   define('DB_HOST', 'localhost');
   define('DB_PORT', '3306');
   define('DB_NAME', 'cpaneluser_kalika');
   define('DB_USER', 'cpaneluser_kuser');
   define('DB_PASS', 'YourStrongLivePasswordHere!');
   define('DB_CHARSET', 'utf8mb4');
   ```
3. Verify that `APP_ENV` is set to `'production'`. This automatically:
   - Disables `display_errors` and `display_startup_errors` (prevents path and database structure leaks).
   - Enables `log_errors` and routes PHP error messages to `/logs/php_errors.log`.

### Step 5: Set File and Directory Permissions
Ensure permissions are set correctly to allow uploads while preventing script execution:
- Directories: `0755` (`drwxr-xr-x`)
- PHP Code Files: `0644` (`-rw-r--r--`)
- Upload Directory: Ensure `/public/uploads/` and `/public/uploads/products/` are writable (`0755` or `0775` depending on server user group).
- Verify that [`public/uploads/.htaccess`](file:///e:/Freelance%20Project/public/uploads/.htaccess) and [`public/uploads/products/.htaccess`](file:///e:/Freelance%20Project/public/uploads/products/.htaccess) are present on the server.

### Step 6: Enable Free SSL / Force HTTPS
1. In cPanel, navigate to **SSL/TLS Status** and run **AutoSSL** (or install Let's Encrypt).
2. Verify that your root `.htaccess` enforces HTTPS redirection:
   ```apache
   RewriteCond %{HTTPS} off [NC]
   RewriteCond %{HTTP_HOST} !^localhost [NC]
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

### Step 7: Create Production Administrator Account & Cleanup
1. Access the administrator provisioning script in your browser:
   ```
   https://yourdomain.com/database/create_admin.php
   ```
2. Enter your desired production administrator username (e.g. `admin` or `deepak`) and a strong password (minimum 12 characters including uppercase, lowercase, numbers, and symbols).
3. Submit to insert the Bcrypt-hashed administrator credentials into the `admins` table.
4. **CRITICAL SECURITY STEP**: Immediately delete `create_admin.php` from the server:
   ```bash
   rm database/create_admin.php
   ```
   *Failure to delete this file exposes an administrative account creation vector.*

### Step 8: Configure Automated Daily Backups
1. In cPanel, navigate to **Cron Jobs**.
2. Add a new cron job scheduled to run daily at 02:00 AM:
   ```bash
   0 2 * * * /usr/local/bin/php /home/username/public_html/database/backup.php > /dev/null 2>&1
   ```
3. This creates a daily SQL backup in `/database/backups/` and automatically keeps the last 14 days of backups while rotating older files.

### Step 9: Configure Domain DNS Records
In your domain registrar DNS panel (e.g. GoDaddy, Hostinger, Cloudflare):
- **A Record**: `@` &rarr; Your server IP address (e.g. `192.0.2.1`)
- **CNAME Record**: `www` &rarr; `yourdomain.com`

---

## 3. Post-Deployment Verification Checklist

Once files and database are live, run through this verification checklist:

1. [ ] **Storefront Home Page**: Open `https://yourdomain.com/` – confirms shared header, active brands, service area highlights, and footer.
2. [ ] **Automated Smoke Test**: Open `https://yourdomain.com/tests/smoke.php` – confirms all 43 automated checks pass on the production server. *(Delete `tests/smoke.php` or restrict access once verified).*
3. [ ] **Catalogue Guard**: Browse `/public/products.php` – confirm Campa and Euro Chips appear; verify Coca-Cola and Glucose are hidden.
4. [ ] **Customer Registration**: Register a test account with 10-digit mobile `9876543210`.
5. [ ] **Cart & Minimum Threshold**:
   - Add items totaling under ₹2,000 (checkout must be disabled).
   - Add items totaling &ge; ₹2,000 (checkout unlocked, delivery marked FREE).
6. [ ] **Place Wholesale Order**: Complete checkout; verify unique order number `KE-YYYYMMDD-XXXX` and offline payment notice.
7. [ ] **Admin Portal Login**: Sign in at `/admin/login.php` with administrator credentials.
8. [ ] **Order Status Advancement**: Change order status from `Placed` &rarr; `Dispatched` &rarr; `Delivered`; verify changes reflect on customer order page.
9. [ ] **Tax Invoice**: Open and print `/public/invoice.php?order=[id]` – verify A4 print preview.
10. [ ] **Security Verification**: Attempt to access `https://yourdomain.com/config/config.php` – must return **403 Forbidden**.
