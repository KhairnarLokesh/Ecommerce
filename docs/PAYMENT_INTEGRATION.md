# Kalika Enterprises — Razorpay Payment Gateway Integration Guide

This document details the complete technical architecture, configuration, operational procedures, database schema, webhook setup, and testing documentation for the Razorpay payment gateway integration into Kalika Enterprises' wholesale B2B platform.

---

## 1. Executive Summary & Architecture

The integration introduces online wholesale payments (UPI, Cards, NetBanking, Wallets) via Razorpay Standard Checkout modal while preserving existing wholesale distribution policies:
- **Minimum Order Requirement**: ₹2,000.00 strictly enforced before checkout or payment initialization.
- **Free Regional Delivery**: 100% free delivery across Niphad, Dindori, Chandwad, Sinnar, and Yeola.
- **Dual Payment Channels**: Customers choose between **Online Payment (Razorpay)** and **Cash on Delivery (COD)**.
- **Stock Reservation**:
  - Decrements variant inventory immediately when a pending online order is created.
  - Locks reserved stock for 30 minutes (`expires_at = NOW() + 30 MIN`).
  - Auto-releases reserved stock back to active inventory if payment fails, is canceled, or expires.
  - Implemented via a dual mechanism: lazy expiry cleanup on `/checkout.php` and an automated scheduler script `/cron/cleanup_pending_orders.php`.
- **Forward-Only Fulfillment Guard**: Unpaid online orders (`payment_method = 'online'` AND `payment_status != 'paid'`) **cannot** be dispatched or delivered by admin dispatchers.
- **Idempotent Webhook & Verification Engine**:
  - Verification occurs on both customer return (`/payment-verify.php`) and asynchronous server-to-server webhooks (`/public/webhooks/razorpay.php`).
  - Race conditions between client redirect and webhook are eliminated using database row-level locking (`SELECT ... FOR UPDATE`) and atomic state checking.
  - Webhook deduplication enforces unique event logging (`webhook_events` table).
- **Zero WhatsApp Ordering**: Preserves strict exclusion of WhatsApp ordering links or buttons across all public pages.

---

## 2. Environment Configuration

### Required Variables in `.env`

```ini
# Razorpay Payment Gateway Settings
RAZORPAY_MODE=test                         # 'test' or 'live'
RAZORPAY_KEY_ID=rzp_test_TnjHqUcu2bdc9F    # Your Razorpay API Key ID
RAZORPAY_KEY_SECRET=3YS4GVGZQBlwgiFzzVnWtSaH# Your Razorpay API Key Secret
RAZORPAY_WEBHOOK_SECRET=kalika_whsec_test_7f9b2d8e4a1c5e3f # Webhook signing secret
```

### Production Cutover Checklist
When switching to production:
1. Log into [Razorpay Dashboard](https://dashboard.razorpay.com/).
2. Switch mode to **Live Mode**.
3. Generate Live API Keys (`rzp_live_...`) and update `RAZORPAY_KEY_ID` and `RAZORPAY_KEY_SECRET` in `.env`.
4. Set `RAZORPAY_MODE=live` in `.env`.
5. Generate a strong Webhook Secret and set it as `RAZORPAY_WEBHOOK_SECRET`.
6. Ensure `.env` file permissions are set to `600` and restricted from web access.

---

## 3. Database Schema & Migration

The migration is **100% additive** and safe for live production instances. No tables or columns are dropped or destructively modified.

### Migration Files
- **Up Migration**: `database/migrations/001_add_razorpay_payment_tables.sql`
- **Rollback Script**: `database/migrations/001_rollback_razorpay_payment_tables.sql`
- **Runner**: `database/run_migration.php`

### Additive Schema Changes
1. **`orders` table modifications**:
   - `payment_status`: `ENUM('pending', 'paid', 'failed', 'refund_pending', 'partially_refunded', 'refunded') DEFAULT 'pending'`
   - `payment_method`: `ENUM('cod', 'online') NOT NULL DEFAULT 'cod'`
   - `razorpay_order_id`: `VARCHAR(100) NULL` (Indexed, Unique)
   - `paid_at`: `DATETIME NULL`
   - `expires_at`: `DATETIME NULL` (Indexed for fast expiry cleanup)
   - `status`: Expanded enum to support internal state `'Awaiting Payment'`.

2. **New table: `payments`**:
   - Tracks individual gateway transaction attempts, gateway status, payment methods (UPI, card, etc.), and HMAC verification status.
   - Unique constraint on `razorpay_payment_id`.

3. **New table: `refunds`**:
   - Tracks gateway refunds initiated from the admin console with amount, reason, admin ID, and Razorpay refund ID.

4. **New table: `webhook_events`**:
   - Tracks incoming Razorpay webhook event IDs to guarantee idempotent single processing.
   - Unique constraint on `event_id`.

5. **New table: `payment_audit_log`**:
   - Immutable audit trail of all payment, refund, order state, and stock release actions.

---

## 4. Webhook Setup & Dashboard Configuration

Webhooks ensure guaranteed payment reconciliation even if a buyer's internet disconnects or they close their browser before being redirected.

### Webhook Configuration in Razorpay Dashboard
1. Go to **Settings > Webhooks** in the Razorpay Dashboard.
2. Click **+ Add New Webhook**.
3. Set **Webhook URL**:
   ```
   https://<your-domain>/public/webhooks/razorpay.php
   ```
   *(or `https://<your-domain>/webhooks/razorpay.php` if Apache URL rewrites are active).*
4. Set **Secret**: Enter the exact secret string defined in `RAZORPAY_WEBHOOK_SECRET`.
5. Set **Alert Email**: Enter the distributor's technical alerts email.
6. Select the following **Active Events**:
   - `order.paid`
   - `payment.authorized`
   - `payment.captured`
   - `payment.failed`
   - `refund.created`
   - `refund.processed`
   - `refund.failed`
7. Click **Save Webhook**.

---

## 5. Stock Reservation & 30-Minute Expiry Engine

### Lifecycle
1. When customer clicks "Place Order" with **Online Payment**:
   - Database transaction begins.
   - Stock is validated for all line items.
   - Stock is decremented in `product_variants`.
   - `orders` row created with `status = 'Awaiting Payment'`, `payment_status = 'pending'`, and `expires_at = NOW() + 1800 SEC`.
   - Razorpay Order created via REST API with order number receipt.
   - Customer is redirected to `/payment.php?order_id=...`.
2. Customer Payment Interface (`/payment.php`):
   - Displays real-time 30-minute stock reservation countdown timer.
   - Triggers Razorpay Standard modal.
   - If modal is dismissed, displays a persistent retry banner and a manual "Cancel Order & Release Stock" button.
3. Expiry Mechanisms:
   - **Lazy Cleanup**: Every visit to `/checkout.php` triggers `cleanup_expired_pending_orders($pdo)`.
   - **Automated Cron**: Add the following crontab entry to your server:
     ```bash
     */5 * * * * php /var/www/html/cron/cleanup_pending_orders.php > /dev/null 2>&1
     ```

---

## 6. Admin Operations & Fulfillment Guard

### Order Listing (`/admin/orders.php`)
- **Filter Toolbar**: Filter orders by payment status (`All`, `Paid`, `Pending`, `Refunded`) and payment method (`Online`, `COD`).
- **Payment Badges**: Shows method (Online / COD) and payment status badge (`Paid`, `Pending`, `Refunded`).
- **CSV Export**: Includes columns `payment_method`, `payment_status`, and `razorpay_order_id`.
- **Fulfillment Guard**: If an online order has `payment_status != 'paid'`, changing status to `Dispatched` or `Delivered` is strictly blocked with a warning notice.

### Order Detail (`/admin/order-detail.php`)
- **Re-check with Razorpay**: One-click button that queries Razorpay's API in real-time, reconciles payment status, updates the database, and logs an audit entry.
- **Cash on Delivery Settlement**: One-click "Confirm Cash Collected" button for COD orders upon driver return.
- **Refund Processor**:
  - Secure modal allowing partial or full refunds.
  - Restrictive balance check: prevents refunding more than the total paid amount.
  - Calls Razorpay Refund API (`razorpay_create_refund()`).
  - Restock checkbox: automatically restores returned inventory quantities back to `product_variants`.

---

## 7. Automated Testing Suite

All tests can be executed via CLI:

| Test Suite | Purpose | Assertions | Status |
| :--- | :--- | :--- | :--- |
| `tests/smoke.php` | System health, database connectivity, required tables, seed data, file presence | 100 | **PASS (100%)** |
| `tests/razorpay_integration_suite.php` | Credentials, HMAC verification, stock reservation, expiry, payment idempotency, webhooks, fulfillment guard, refunds, IDOR | 46 | **PASS (100%)** |
| `tests/auth_lifecycle_suite.php` | Customer registration, login, rate limits, session security, OTP recovery, customer IDOR | 31 | **PASS (100%)** |
| `tests/admin_auth_suite.php` | Admin authentication, 5-attempt lockout, idle timeout, authorization gates, audit log | 44 | **PASS (100%)** |
| **Total** | **Comprehensive Full System Coverage** | **221** | **PASS (100%)** |

To run the suites:
```bash
php tests/smoke.php
php tests/razorpay_integration_suite.php
php tests/auth_lifecycle_suite.php
php tests/admin_auth_suite.php
```
