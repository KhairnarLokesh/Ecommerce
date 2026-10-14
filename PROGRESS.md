# Kalika Enterprises – Build Progress Tracker

This file tracks the implementation milestones across all 11 phases of the project. Update this document after completing each phase.

---

## Overall Status Summary
- **Current Active Phase**: **Phase 10 – Deployment, Backup & Handover** (Completed)
- **Completed Phases**: Phase 0, Phase 1, Phase 2, Phase 3, Phase 4, Phase 5, Phase 6, Phase 7, Phase 8, Phase 9, Phase 10
- **Overall Completion**: 11 / 11 Core Phases Complete (100% Scope Delivered)
- **Next / Post-Launch**: Phase 11 (Optional Post-Launch Enhancements after live client sign-off)

---

## Phase Checklist

### [x] Phase 0 – Discovery & Confirmation Checklist
- **Status**: Completed
- **Deliverables**:
  - [x] Master Context established in [`PROJECT_CONTEXT.md`](file:///e:/Freelance%20Project/PROJECT_CONTEXT.md)
  - [x] Unconfirmed business & legal items compiled into [`TODO_CONFIRM.md`](file:///e:/Freelance%20Project/TODO_CONFIRM.md)
  - [x] Phase tracking initialized in [`PROGRESS.md`](file:///e:/Freelance%20Project/PROGRESS.md)
- **Done When**: `TODO_CONFIRM.md` and `PROGRESS.md` exist and the list is ready for client review.

---

### [x] Phase 1 – Project Setup & Architecture
- **Status**: Completed
- **Deliverables**:
  - [x] Directory layout: `/public`, `/admin`, `/includes`, `/config`, `/database`, `/docs`
  - [x] Database connector `includes/db.php` (PDO, utf8mb4, ERRMODE_EXCEPTION, emulated prepares off)
  - [x] Security utilities `includes/csrf.php` and helper functions `includes/functions.php`
  - [x] Shared responsive layout (`includes/header.php`, `includes/footer.php`)
  - [x] Root `.gitignore`, `.htaccess` (HTTPS enforcement, directory browsing prevention), and `README.md`
- **Done When**: Every public page loads with the shared header/footer, no PHP errors, DB connection verified.

---

### [x] Phase 2 – Database Design & Seed Data
- **Status**: Completed
- **Deliverables**:
  - [x] `database/schema.sql` (12 tables with foreign keys, InnoDB, utf8mb4, proper indexing)
  - [x] `database/seed.sql` (7 confirmed brands, active/inactive products, default business settings)
  - [x] Admin user generation utility `database/create_admin.php`
  - [x] Documentation in `docs/DATABASE.md` with Entity-Relationship overview
- **Done When**: Schema imports cleanly, seed loads error-free, product/variant counts match master specifications.

---

### [x] Phase 3 – UI/UX Design System
- **Status**: Completed
- **Deliverables**:
  - [x] Pure CSS design system in `/public/assets/css/style.css` (variables, typography, colors, badges, alerts, modals, tables)
  - [x] Responsive navigation with mobile hamburger drawer and live cart indicator
  - [x] Printable stylesheet for clean A4 tax invoices
  - [x] Visual component showcase in `/public/styleguide.php` (and static `/public/styleguide.html`)
- **Done When**: Styleguide renders and responds cleanly across 360px, 768px, and 1280px breakpoints.

---

### [x] Phase 4 – Public Pages & Product Catalogue
- **Status**: Completed
- **Deliverables**:
  - [x] Home page with hero, active brands, service area highlights, policy bar, and CTA
  - [x] About Us & Policies pages driven by database settings
  - [x] Contact Us page with secure inquiry storage in `contact_messages` table
  - [x] Products catalogue with live search, brand filters, variant pickers, stock badges, and dynamic price updates
  - [x] Inactive & GST-unconfirmed product exclusion safeguards (Coca-Cola and Glucose excluded)
- **Done When**: Confirmed products display accurate variants and prices; live search/filters work; unconfirmed items hidden.

---

### [x] Phase 5 – Customer Accounts & Demo OTP
- **Status**: Completed (Hardened & Lifecycle Verified)
- **Deliverables**:
  - [x] Registration with dual validation (RFC-compliant email + 10-digit Indian mobile), database unique constraint (`uq_users_email`), terms acceptance, and Bcrypt hashing
  - [x] Dual-identifier login (Email or Mobile) with inactive account rejection, session regeneration, and brute-force rate limiting (`login_attempts`)
  - [x] Customer session handling and strict IDOR data isolation across profiles, orders, and invoices
  - [x] Password reset via 6-digit Demo OTP (`random_int`), SHA-256 hash storage, 5-minute expiry, cooldown spam guard, on-screen countdown, and transaction-safe single-use concurrency locks
  - [x] Production security gate (`DEMO_OTP_ENABLED` flag) preventing unauthorized OTP exposure in live environments
  - [x] Seamless cart preservation across login/registration, returning customers directly to checkout
  - [x] 30-assertion automated authentication lifecycle test suite `tests/auth_lifecycle_suite.php` (100% pass)
  - [x] Testing guidelines updated in `docs/TESTING.md`

---

### [x] Phase 6 – Cart, Checkout, Orders & Invoice
- **Status**: Completed
- **Deliverables**:
  - [x] Cart system supporting both guest sessions and persistent authenticated storage (`includes/cart.php`, `public/cart.php`)
  - [x] Dynamic server-side recalculation of subtotal, GST breakdown (inclusive/exclusive), and Free Delivery badge
  - [x] Hard enforcement of ₹2,000 minimum order threshold (checkout blocked under ₹2,000)
  - [x] Transaction-safe checkout, stock decrement under row lock, out-of-stock auto flag, and immutable `order_items` snapshotting (`public/checkout.php`)
  - [x] Customer order tracking (Placed -> Dispatched -> Delivered) and printable/downloadable invoice (`public/order.php`, `public/invoice.php`)
  - [x] IDOR authorization verification (HTTP 403 Forbidden on mismatched customer orders/invoices)
  - [x] Verified testing procedures added to `docs/TESTING.md`
- **Done When**: Orders under ₹2,000 are blocked; ₹2,000+ checkout succeeds; GST & totals match calculations; IDOR tests pass.

---

### [x] Phase 7 – Admin Panel
- **Status**: Completed (Hardened Admin Authentication & Comprehensive Audit Trail)
- **Deliverables**:
  - [x] Separate admin authentication session (`admin/login.php`, `admin/logout.php`, `admin/change-password.php`), HttpOnly/Secure/SameSite cookies, 30-minute idle timeout, and CSRF protection
  - [x] 5-attempt brute-force lockout for 15 minutes, generic error messages, and tracking in `admin_login_log`
  - [x] Customer access gate strictly returning HTTP 403 Forbidden to any logged-in customer attempting admin URL access
  - [x] First-login forced password change screen (`must_change_password = 1`), real-time strength meter, and password policy (min 12 chars, upper, lower, digit, symbol)
  - [x] Command-line only admin provisioning utility `database/create_admin.php` refusing HTTP execution with 403
  - [x] Comprehensive audit log `admin_audit_log` tracking creates, updates, and deletes for products, variants, prices, GST, stock, order statuses, content settings, and password changes
  - [x] Read-only Activity Log page `admin/activity-log.php` with action, entity, date range, and keyword filters
  - [x] Admin credential recovery and restoration manual [`docs/ADMIN_RECOVERY.md`](file:///e:/Freelance%20Project/docs/ADMIN_RECOVERY.md)
  - [x] Automated 44-assertion admin auth test suite `tests/admin_auth_suite.php` (100% pass)
  - [x] KPI metrics dashboard with 6 live counts (Placed, Dispatched, Delivered, Customers, Low Stock, Pending GST) & 10 recent orders (`admin/index.php`)
  - [x] Brand management with active/inactive visibility toggle (`admin/brands.php`)
  - [x] Product catalogue management with search, filters, safe soft-deletion, and strict GST rate activation guard (`admin/products.php`)
  - [x] Product creation/editing with verified image uploads (`getimagesize`) and size variant management (`admin/product-edit.php`)
  - [x] Bulk stock editor for rapid multi-SKU quantity and availability updates (`admin/stock.php`)
  - [x] Customer directory with lifetime purchase totals and account suspension toggle (`admin/customers.php`)
  - [x] Order pipeline with status advancement, tax invoice links, and CSV data export (`admin/orders.php`, `admin/order-detail.php`)
  - [x] Enterprise parameters, minimum order value (₹2,000), GST calculation toggle, and policy text editor (`admin/settings.php`)
  - [x] Storefront customer contact inquiry inbox (`admin/messages.php`)
  - [x] Verified testing procedures added to `docs/TESTING.md`
- **Done When**: Admin can manage products, variants, stock, order statuses, and settings; changes reflect immediately on frontend; all 9 admin authentication requirements pass.

---

### [x] Phase 8 – Security Hardening & Review
- **Status**: Completed
- **Deliverables**:
  - [x] Codebase audit for prepared statements (100% parameterized), XSS escaping (`e()`), CSRF verification across all POST forms
  - [x] Session cookie hardening (`HttpOnly`, `SameSite=Lax`, dynamic `Secure`), session regeneration, and 2-hour idle timeout
  - [x] Multi-layer upload directory lockdown (`public/uploads/.htaccess` and `public/uploads/products/.htaccess` disabling script execution)
  - [x] Security headers implementation (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, CSP)
  - [x] Multi-layer directory access protection with individual `.htaccess` files for `config/`, `database/`, `docs/`, `includes/`, and `logs/`
  - [x] Comprehensive security audit report documented in [`docs/SECURITY.md`](file:///e:/Freelance%20Project/docs/SECURITY.md)
- **Done When**: Full audit checklist passes and `docs/SECURITY.md` details all checks, mitigations, and residual items.

---

### [x] Phase 9 – Testing & QA
- **Status**: Completed
- **Deliverables**:
  - [x] Automated smoke test script `tests/smoke.php` (43 checks executed, 100% pass)
  - [x] Comprehensive manual test execution across all core customer & admin user flows
  - [x] Multi-device & responsive layout validation (375px mobile & 1280px desktop verified)
  - [x] Database backup & restore validation (100% parity verified with `mysqldump`)
  - [x] Test results documented in [`docs/TEST_REPORT.md`](file:///e:/Freelance%20Project/docs/TEST_REPORT.md)
- **Done When**: All test cases in `TEST_REPORT.md` are documented as Pass.

---

### [x] Phase 10 – Deployment, Backup & Handover
- **Status**: Completed
- **Deliverables**:
  - [x] Production deployment guide [`docs/DEPLOYMENT.md`](file:///e:/Freelance%20Project/docs/DEPLOYMENT.md) for cPanel / shared PHP hosting
  - [x] Automated and manual backup scripts [`database/backup.php`](file:///e:/Freelance%20Project/database/backup.php), [`database/backup.sh`](file:///e:/Freelance%20Project/database/backup.sh), and [`docs/BACKUP_RESTORE.md`](file:///e:/Freelance%20Project/docs/BACKUP_RESTORE.md)
  - [x] Client administrative operations manual [`docs/ADMIN_GUIDE.md`](file:///e:/Freelance%20Project/docs/ADMIN_GUIDE.md)
  - [x] Code and security maintenance instructions [`docs/MAINTENANCE.md`](file:///e:/Freelance%20Project/docs/MAINTENANCE.md)
  - [x] Client handover dossier and acceptance checklist [`docs/HANDOVER.md`](file:///e:/Freelance%20Project/docs/HANDOVER.md)
  - [x] Pre-go-live client confirmation checklist finalized in [`TODO_CONFIRM.md`](file:///e:/Freelance%20Project/TODO_CONFIRM.md)
- **Done When**: Production deployment instructions ready, backup/restore procedures verified, administrative manuals provided, and client handover dossier completed.

---

### [ ] Phase 11 (Optional) – Post-Launch Improvements
- **Status**: In Progress (Email OTP Live)
- **Items**:
  - [ ] Order notification emails via SMTP
  - [x] Direct Email OTP integration via SMTP mailer (`includes/mailer.php`)
  - [ ] Low-stock administrative email alerts
  - [ ] Sales & territory reporting

---

### [x] Phase 12 – Razorpay Payment Gateway Integration
- **Status**: Completed
- **Deliverables**:
  - [x] **Inspection & Baseline**: Non-destructive integration points mapped without altering core business rules (₹2,000 minimum order, free delivery, GST inclusive/exclusive logic, WhatsApp exclusion).
  - [x] **Database Schema**: Additive migrations applied (`database/migrations/001_add_razorpay_payment_tables.sql` & rollback script `001_rollback_razorpay_payment_tables.sql`). Created `payments`, `refunds`, `webhook_events`, and `payment_audit_log` tables. Expanded `orders` schema with `payment_status`, `payment_method`, `razorpay_order_id`, `paid_at`, `expires_at`.
  - [x] **REST Client & Gateway Engine**: Built `includes/razorpay.php` with cURL & HTTPS stream context fallback, orders API, verification, capture, refund endpoints, and row-locked idempotent status transitions (`order_mark_paid`).
  - [x] **Stock Reservation & Expiry**: Decrements inventory on pending order checkout; locks for 30 minutes; releases stock back on cancellation or expiration. Built `cron/cleanup_pending_orders.php` and lazy checkout cleanup.
  - [x] **Customer Payment Modal & Verification**: Built `public/payment.php` (Razorpay Standard modal, countdown timer, retry, manual release) and `public/payment-verify.php` (HMAC verification, server-side amount cross-check, idempotent paid marking).
  - [x] **Webhook Ingestion**: Built `public/webhooks/razorpay.php` (HMAC validation, event deduplication, handlers for `order.paid`, `payment.captured`, `payment.authorized`, `payment.failed`, `refund.processed`).
  - [x] **Admin Operations & Fulfillment Guard**: Added payment filters, CSV export columns, and strict guard blocking `Dispatched`/`Delivered` on unpaid online orders. Added "Re-check with Razorpay", "Confirm Cash Collected" for COD, and admin refund processor with stock restocking in `admin/order-detail.php`.
  - [x] **Policy & UI Consistency**: Updated `public/policies.php`, `public/cart.php`, `public/about.php`, `includes/footer.php`, `public/invoice.php`, and `public/order.php` to reflect online & COD payment options while strictly preserving the WhatsApp ordering exclusion.
  - [x] **Automated Testing Suite**: Built `tests/razorpay_integration_suite.php` (46 assertions passing 100%). Updated `tests/smoke.php` (100 checks passing 100%). Full regression test pass across all 221 assertions in the repository.
  - [x] **Documentation**: Created [`docs/PAYMENT_INTEGRATION.md`](file:///e:/Freelance%20Project/docs/PAYMENT_INTEGRATION.md) and synchronized [`docs/DATABASE.md`](file:///e:/Freelance%20Project/docs/DATABASE.md).

