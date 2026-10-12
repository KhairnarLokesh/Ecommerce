# Kalika Enterprises – Comprehensive Security Audit & Hardening Report

This report documents the security posture, vulnerability audit findings, hardening measures, and residual risks for the **Kalika Enterprises** wholesale food and beverage distribution web application as required by **Phase 8**.

---

## 1. Executive Summary & Verification Matrix

| # | Security Control | Verification Standard | Implementation Status | Notes |
|---|------------------|-----------------------|-----------------------|-------|
| 1 | **SQL Injection Defense** | PDO prepared statements for 100% of dynamic queries | **PASS (100%)** | Zero unescaped string interpolations in SQL |
| 2 | **Cross-Site Scripting (XSS)** | Context-aware HTML escaping via `e()` (`htmlspecialchars`) | **PASS** | Applied to all dynamic customer & admin views |
| 3 | **Cross-Site Request Forgery (CSRF)** | Synchronizer Token Pattern with timing-safe comparison | **PASS** | Every POST form requires verified token |
| 4 | **Session Management** | `HttpOnly`, `SameSite=Lax`, dynamic `Secure`, idle timeout | **PASS** | Session regeneration on login; isolated admin namespace |
| 5 | **Authorization & IDOR Defense** | Server-side role & identity verification on orders/invoices | **PASS** | Strict 403 Forbidden on mismatched customer IDs |
| 6 | **Unrestricted File Uploads** | Multi-layer check: extension, MIME, `getimagesize`, `.htaccess` | **PASS** | Script execution blocked in `/public/uploads/` |
| 7 | **Brute-Force Rate Limiting** | 5 failed attempts / 15-minute sliding window | **PASS** | Customer login, Admin login, and OTP generation |
| 8 | **HTTP Security Headers** | `nosniff`, `SAMEORIGIN`, `strict-origin`, basic CSP | **PASS** | Enforced at PHP runtime and Apache `.htaccess` |
| 9 | **Production Error Handling** | `display_errors = 0`, logs stored outside webroot | **PASS** | Isolated `/logs/` directory protected from web access |
| 10| **Sensitive File Access** | Direct access blocked to config, schema, docs, logs | **PASS** | Multiple `.htaccess` layers returning 403 Forbidden |
| 11| **Credential Storage** | Strong Bcrypt hashing (`PASSWORD_DEFAULT`), secrets ignored | **PASS** | No credentials or salts committed to Git |
| 12| **Input Validation** | Strict regex for mobile, numeric ranges, GST guard | **PASS** | Server-side validation before any database write |

---

## 2. In-Depth Audit Findings & Mitigations

### 2.1 SQL Injection Audit (Item 1)
- **Methodology**: Automated code search across all PHP files for `$pdo->query()` with dynamic variables and `$pdo->prepare()` with string concatenation.
- **Audit Findings**:
  - All database queries handling user input use PDO parameterized statements with bound placeholders (`?` or `:name`).
  - Complex dynamic queries (e.g. catalogue search in [`public/products.php`](file:///e:/Freelance%20Project/public/products.php) and cart lookup in [`includes/cart.php`](file:///e:/Freelance%20Project/includes/cart.php)) construct parameterized SQL strings with dynamic `IN (?, ?, ...)` clauses while passing all variant IDs in `$stmt->execute($ids)`.
  - Database connection in [`includes/db.php`](file:///e:/Freelance%20Project/includes/db.php) explicitly disables emulated prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`) and enables exception mode (`PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`).

### 2.2 Cross-Site Scripting (XSS) Defenses (Item 2)
- **Methodology**: Grepped for all output tags `<?=` across `public/` and `admin/` to verify escaping.
- **Audit Findings**:
  - All dynamic data rendered into HTML attributes or tags is passed through `e()` (`htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`).
  - Textarea fields with multiline text use `nl2br(e($text))` ensuring HTML entities are escaped *before* line breaks are added.
  - No raw user input is directly evaluated with JavaScript `eval()` or inserted into `innerHTML`.

### 2.3 Cross-Site Request Forgery (CSRF) (Item 3)
- **Methodology**: Verified token generation, token storage, and verification across all POST endpoints.
- **Audit Findings**:
  - Implemented in [`includes/csrf.php`](file:///e:/Freelance%20Project/includes/csrf.php) using cryptographically secure random bytes (`random_bytes(32)`).
  - Comparison uses `hash_equals()` to prevent timing attacks.
  - Every single POST form in the application includes `<?= csrf_field() ?>`.
  - AJAX cart updates include the CSRF token in the request header/body and receive a fresh token upon response.

### 2.4 Session Security & Fixation Protection (Item 4)
- **Methodology**: Inspected cookie configurations, session lifecycles, and namespace separation.
- **Audit Findings**:
  - Configured in [`includes/auth.php`](file:///e:/Freelance%20Project/includes/auth.php) via `session_set_cookie_params()`:
    - `httponly`: `true` (blocks client-side JavaScript access to `document.cookie`).
    - `samesite`: `'Lax'` (mitigates CSRF via third-party cross-site navigation).
    - `secure`: Dynamically enabled when SSL/HTTPS is detected.
    - `lifetime`: 7 days.
  - **Fixation Defense**: `session_regenerate_id(true)` is invoked immediately upon successful customer login, admin login, and registration.
  - **Idle Timeout**: Automatically destroys sessions inactive for more than 2 hours (7,200 seconds).
  - **Namespace Isolation**: Customer credentials exist under `$_SESSION['user_id']`, whereas administrative access requires `$_SESSION['admin_id']`. A customer session cannot access `/admin/*`.

### 2.5 Insecure Direct Object References (IDOR) (Item 5)
- **Methodology**: Verified authorization logic for single-record views (`order.php`, `invoice.php`, `profile.php`).
- **Audit Findings**:
  - [`public/profile.php`](file:///e:/Freelance%20Project/public/profile.php): Reads identity strictly from `$_SESSION['user_id']`. Does not accept an arbitrary `id` URL parameter.
  - [`public/order.php`](file:///e:/Freelance%20Project/public/order.php): Verifies `(int)$order['user_id'] === (int)$_SESSION['user_id'] || is_admin()`. If a customer attempts to query another user's order ID, the script immediately responds with **HTTP 403 Forbidden**.
  - [`public/invoice.php`](file:///e:/Freelance%20Project/public/invoice.php): Enforces the same identity check before rendering commercial invoice details.

### 2.6 File Upload Hardening (Item 6)
- **Methodology**: Audited product image uploads in [`admin/product-edit.php`](file:///e:/Freelance%20Project/admin/product-edit.php).
- **Audit Findings**:
  - **Extension Whitelist**: Only `.jpg`, `.jpeg`, `.png`, and `.webp` are accepted.
  - **Size Constraint**: Enforces a strict 2MB limit.
  - **MIME & Header Verification**: Invokes `getimagesize()` on the temporary upload file to verify real image headers and confirm image MIME types (`image/jpeg`, `image/png`, `image/webp`).
  - **Collision-Safe Random Names**: Renames uploaded files using `prod_` + `bin2hex(random_bytes(8))` + extension, neutralizing directory traversal or original malicious file names.
  - **Server Execution Block**: Both [`public/uploads/.htaccess`](file:///e:/Freelance%20Project/public/uploads/.htaccess) and [`public/uploads/products/.htaccess`](file:///e:/Freelance%20Project/public/uploads/products/.htaccess) disable script execution (`php_flag engine off`, remove script handlers, deny all `.php`, `.cgi`, `.sh`, `.exe`, etc.).

### 2.7 Brute-Force Rate Limiting (Item 7)
- **Methodology**: Verified rate-limiting database table [`login_attempts`](file:///e:/Freelance%20Project/database/schema.sql) and sliding window queries.
- **Audit Findings**:
  - [`public/login.php`](file:///e:/Freelance%20Project/public/login.php): Allows max 5 failed attempts per 15-minute sliding window per mobile number or IP address before locking access.
  - [`admin/login.php`](file:///e:/Freelance%20Project/admin/login.php): Applies the same 5-attempt/15-minute lockout to staff usernames and client IPs.
  - [`public/forgot.php`](file:///e:/Freelance%20Project/public/forgot.php): Limits OTP verification attempts to 5 per OTP token before invalidating the code.

### 2.8 HTTP Security Headers (Item 8)
- **Methodology**: Verified headers sent by PHP and Apache.
- **Audit Findings**:
  - Implemented in [`includes/auth.php`](file:///e:/Freelance%20Project/includes/auth.php) via `send_security_headers()` and in root [`.htaccess`](file:///e:/Freelance%20Project/.htaccess):
    - `X-Content-Type-Options: nosniff`
    - `X-Frame-Options: SAMEORIGIN`
    - `Referrer-Policy: strict-origin-when-cross-origin`
    - `X-XSS-Protection: 1; mode=block`
    - `Content-Security-Policy: default-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com;`

### 2.9 Production Error Handling & Logging (Item 9)
- **Methodology**: Inspected [`config/config.php`](file:///e:/Freelance%20Project/config/config.php).
- **Audit Findings**:
  - In `production` mode, `ini_set('display_errors', '0')` and `ini_set('display_startup_errors', '0')` are enforced to prevent database schema and path disclosure.
  - Errors are routed to [`logs/php_errors.log`](file:///e:/Freelance%20Project/logs/) using `ini_set('log_errors', '1')`.
  - The [`logs/`](file:///e:/Freelance%20Project/logs/) directory contains an independent `.htaccess` with `Require all denied`.

### 2.10 Non-Web-Accessible Sensitive Directories (Item 10)
- **Methodology**: Inspected directory protection against direct HTTP traversal.
- **Audit Findings**:
  - Root [`.htaccess`](file:///e:/Freelance%20Project/.htaccess) contains:
    ```apache
    RewriteRule ^(config|includes|database|docs|logs)/ - [F,L,NC]
    ```
  - Independent local `.htaccess` files placed inside:
    - [`config/.htaccess`](file:///e:/Freelance%20Project/config/.htaccess) (`Require all denied`)
    - [`database/.htaccess`](file:///e:/Freelance%20Project/database/.htaccess) (`Require all denied`)
    - [`docs/.htaccess`](file:///e:/Freelance%20Project/docs/.htaccess) (`Require all denied`)
    - [`includes/.htaccess`](file:///e:/Freelance%20Project/includes/.htaccess) (`Require all denied`)
    - [`logs/.htaccess`](file:///e:/Freelance%20Project/logs/.htaccess) (`Require all denied`)

### 2.11 Credential & Secret Hygiene (Item 11)
- **Methodology**: Verified hashing algorithms and repository exclusion.
- **Audit Findings**:
  - Customer passwords and staff passwords hashed exclusively with Bcrypt via `password_hash($pass, PASSWORD_DEFAULT)`.
  - [`.gitignore`](file:///e:/Freelance%20Project/.gitignore) explicitly ignores `config/config.php`, `.env`, and all `.log` files.
  - Sample configuration template [`config/config.sample.php`](file:///e:/Freelance%20Project/config/config.sample.php) contains dummy placeholder values.

### 2.12 Business Constraint & Publishing Guards (Item 12)
- **Methodology**: Verified input rules and catalogue publishing constraints.
- **Audit Findings**:
  - Mobile numbers validated via regex `/^[6-9]\d{9}$/`.
  - Minimum order value strictly enforced server-side in checkout transaction (`$subtotal >= 2000.00`).
  - **Strict GST Guard**: Products with `gst_percent IS NULL` cannot be activated in [`admin/product-edit.php`](file:///e:/Freelance%20Project/admin/product-edit.php) or [`admin/products.php`](file:///e:/Freelance%20Project/admin/products.php), and are excluded from public catalogue queries.

---

## 3. Residual Risks & Production Disclosures

1. **Free Demo OTP Screen Display (Client Specification)**:
   - *Risk*: In accordance with project instructions ("no paid SMS gateway"), [`public/forgot.php`](file:///e:/Freelance%20Project/public/forgot.php) generates a random 6-digit OTP and renders it directly in a styled "Demo OTP" box on screen.
   - *Production Remediation*: This mechanism is strictly for testing and client demonstration. Before public production launch with real customers, integrate an authorized SMS gateway (e.g. MSG91, Fast2SMS) with Indian DLT-registered templates so the OTP is transmitted out-of-band via SMS.
2. **Offline Payment On Delivery Model**:
   - *Operational Risk*: Because no online payment gateway is implemented (per client specification), payment collection relies on offline settlement upon van arrival. This presents commercial delivery rejection risk (cash on delivery default risk) rather than digital web application vulnerabilities.

---

## 4. Conclusion
The application meets and exceeds the security requirements outlined in Phase 8 for deployment to standard PHP 8.x + MySQL shared hosting environments.
