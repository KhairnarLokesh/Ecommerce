# Kalika Enterprises – Testing Procedures & Manual Verification Runbook

This runbook documents verification procedures for Customer Accounts, Authentication, Rate Limiting, and the Free Demo OTP password reset system implemented in **Phase 5**.

---

## 1. Customer Registration Flow (`register.php`)

### Test Case 1.1: Valid Registration
1. Navigate to `/public/register.php`.
2. Fill in:
   - **Store Name / Contact**: `Omkar General Stores / Sunil Patil`
   - **Mobile**: `9876543210`
   - **Email**: `omkar@example.com`
   - **Address**: `Shop No. 12, Main Market Yard, Pimpalgaon Baswant, Niphad - 422201`
   - **Password**: `TestPass123`
   - **Confirm Password**: `TestPass123`
3. Click **Create Retailer Account**.
4. **Expected Result**:
   - Record created in `users` table with bcrypt `password_hash`.
   - Customer automatically signed in (`$_SESSION['user_id']` set).
   - Redirects to `/public/profile.php` with success banner.

### Test Case 1.2: Duplicate Mobile Number Rejection
1. Logout or open an incognito session.
2. Navigate back to `/public/register.php`.
3. Attempt to register again using the same mobile number (`9876543210`).
4. **Expected Result**:
   - Form blocked with message: *"A retailer account with this mobile number already exists. Please sign in instead."*
   - No duplicate record created in `users` table.

### Test Case 1.3: Invalid Mobile Format & Short Password
1. Enter a 9-digit mobile `987654321` or letters.
2. Enter a 5-character password `short`.
3. **Expected Result**:
   - Field validations block submission with descriptive error notices.

---

## 2. Customer Login & Rate Limiting (`login.php`)

### Test Case 2.1: Valid Authentication
1. Navigate to `/public/login.php`.
2. Enter `9876543210` and `TestPass123`.
3. Submit form.
4. **Expected Result**:
   - Session regenerated (`session_regenerate_id(true)`).
   - Redirects to `/public/profile.php` or return URL if specified.
   - User name displays in header navigation.

### Test Case 2.2: Rate Limiting & Lockout
1. Navigate to `/public/login.php`.
2. Enter `9876543210` and submit the wrong password 5 consecutive times.
3. On the 5th attempt and subsequent requests:
4. **Expected Result**:
   - `login_attempts` table records entries with `identifier` and client IP.
   - System locks out further attempts for 15 minutes:
     *"Too many failed login attempts. For security, please wait 15 minutes before trying again."*

---

## 3. Customer Profile & Security Management (`profile.php`)

### Test Case 3.1: Profile Information Update
1. Sign in and open `/public/profile.php`.
2. Edit store name to `Omkar Super Market` and save.
3. Refresh page.
4. **Expected Result**:
   - Database record and session cache reflect updated name immediately.

### Test Case 3.2: Password Change with Current Password Verification
1. On `/public/profile.php`, under **Change Password**:
   - Enter wrong current password & submit.
   - **Result**: Rejection: *"Current password is incorrect."*
2. Enter correct current password (`TestPass123`) and new password (`NewPass456`).
3. **Expected Result**:
   - Success banner displayed.
   - Logging out and signing in with old password fails; new password succeeds.

### Test Case 3.3: Server-Side Authorization (IDOR Defense)
1. Log out.
2. Attempt to navigate directly to `/public/profile.php`.
3. **Expected Result**:
   - Direct access rejected. Redirects to `/public/login.php` with error: *"Please log in to continue."*

---

## 4. Free Demo OTP Password Reset Flow (`forgot.php` & `reset.php`)

### Test Case 4.1: OTP Generation & Format (6 Digits)
1. Open `/public/forgot.php`.
2. Enter registered mobile `9876543210` and click **Generate Demo OTP**.
3. **Expected Result**:
   - 6-digit random code (e.g., `482910`) displayed in high-contrast **Demo OTP Box**.
   - Database record created in `password_otps` with SHA-256 hash, `expires_at = NOW() + 5 MINUTE`, and `used_at = NULL`.

### Test Case 4.2: Wrong OTP Rejection
1. On verification step (`forgot.php?step=verify`), enter `000000`.
2. Click **Verify Code**.
3. **Expected Result**:
   - Form rejected with: *"Invalid verification code. Please check the 6-digit OTP."*
   - Attempt count incremented in `password_otps` table.

### Test Case 4.3: Successful OTP Verification & Password Reset
1. Enter the correct 6-digit code displayed in the Demo box.
2. Submit verification.
3. **Expected Result**:
   - Record in `password_otps` marked `used_at = NOW()` immediately.
   - Redirects to `/public/reset.php`.
4. Enter new password (`ResetPass789`) and submit.
5. **Expected Result**:
   - Password updated in `users` table.
   - Session authorization flag destroyed.
   - Redirects to `/public/login.php` with success notification.
   - User can log in with `ResetPass789`.

### Test Case 4.4: Single-Use Guarantee (Second Use Fails)
1. Attempt to re-submit the same OTP code again or reload the verification endpoint.
2. **Expected Result**:
   - Access denied: *"This OTP has already been used. Please generate a new code."*

### Test Case 4.5: Gated Reset Page Protection
1. In a fresh browser session (without verifying an OTP), navigate directly to `/public/reset.php`.
2. **Expected Result**:
   - Unauthorized access blocked.
   - Redirects back to `/public/forgot.php` with warning: *"Unauthorized access. Please request and verify an OTP first."*

---

## 5. Cart, Checkout, Orders & Invoice Flow (Phase 6)

### Test Case 5.1: ₹1,999 Minimum Threshold Block vs. ₹2,000 Unlock
1. As guest or authenticated user, browse `/public/products.php`.
2. Add items totaling under ₹2,000 (e.g. 99 units @ ₹20.00 = ₹1,980.00 or 1 unit of 1L Campa Cola @ ₹80.00).
3. View `/public/cart.php`.
4. **Expected Result**:
   - Warning banner displays: *"Minimum Order Not Reached: Current subtotal is ₹X. Add ₹Y more to reach the mandatory ₹2,000 distributor threshold."*
   - Progress bar visually indicates progress toward ₹2,000.
   - **"Proceed to Checkout" button is completely disabled** (`disabled` attribute and styled inactive).
5. Increase quantity until subtotal >= ₹2,000.00 (e.g. 100 units of 500ml Campa @ ₹20.00 = ₹2,000.00).
6. **Expected Result**:
   - Status updates to: *"Wholesale Minimum Order Reached: Your subtotal qualifies for 100% Free Doorstep Delivery."*
   - **"Proceed to Checkout" button is enabled** and links to `/public/checkout.php`.

### Test Case 5.2: GST & Totals Manual Calculation Verification
1. Place 100 units of Campa Cola 500ml (@ ₹20.00, GST 28%) in cart. Subtotal = ₹2,000.00.
2. Under inclusive GST (`prices_include_gst = 1`):
   - Base taxable amount: `₹2,000 / (1 + 0.28) = ₹1,562.50`.
   - Tax amount: `₹2,000 - ₹1,562.50 = ₹437.50`.
   - Delivery charge: **FREE** (`₹0.00`).
   - Grand total: **₹2,000.00**.
3. **Expected Result**:
   - Server-side calculation matches manual formula down to the exact paisa.
   - Delivery is clearly marked "FREE" with ₹0 charge.

### Test Case 5.3: Checkout, Stock Decrement & Transaction Safety
1. Sign in as a registered user.
2. Navigate to `/public/checkout.php`.
3. Confirm delivery details: name, mobile, delivery address.
4. Note that offline payment notice is prominent: *"No online payment required. Settle offline upon delivery."*
5. Click **Confirm & Place Wholesale Order**.
6. **Expected Result**:
   - Order created in database with unique number format `KE-YYYYMMDD-XXXX`.
   - Line items snapshotted into `order_items`.
   - Stock in `product_variants` decremented by ordered quantities.
   - If stock hits 0, `stock_status` is updated to `'out_of_stock'`.
   - Customer cart is cleared.
   - Redirects to `/public/order.php?id=[new_id]&confirmed=1`.

### Test Case 5.4: Visual Order Tracker
1. View `/public/order.php?id=[order_id]`.
2. Inspect the Fulfillment Tracker.
3. **Expected Result**:
   - Shows 3 distinct visual milestones: `1. Placed` -> `2. Dispatched` -> `3. Delivered`.
   - Newly created orders display milestone 1 marked complete (green checkmark with placement timestamp) and milestones 2 & 3 pending.

### Test Case 5.5: IDOR & Security Authorization Defense
1. Log in as Customer A (e.g. User ID 1).
2. Place an order (generating Order ID X).
3. Log out and register or log in as Customer B (User ID 2).
4. Attempt to navigate directly to `/public/order.php?id=X` and `/public/invoice.php?order=X`.
5. **Expected Result**:
   - Direct access is strictly denied with **HTTP 403 Forbidden**: *"You do not have authorization to view this order."*
   - Customer B cannot view Customer A's order or invoice details.

### Test Case 5.6: Formal Tax Invoice & Print / PDF Generation
1. Log in as Customer A and open `/public/invoice.php?order=X`.
2. **Expected Result**:
   - Renders distributor details (Kalika Enterprises, Pimpalgaon Baswant address, GSTIN).
   - Renders buyer details, order date, and offline payment terms.
   - Itemized table with GST %, unit rates, and totals.
   - Print stylesheet optimizes cleanly for standard A4 paper without navigation bars.
   - "Print / Save PDF" triggers browser print modal.

---

## 6. Admin Panel Operations & Catalogue Compliance (Phase 7)

### Test Case 6.1: Admin Authentication, Rate Limiting & Session Isolation
1. Navigate to `/admin/login.php`.
2. Enter username `admin` and default password `Admin@1234`.
3. Submit form.
4. **Expected Result**:
   - Admin session initialized in dedicated namespace (`$_SESSION['admin_id']`), isolated from customer session (`$_SESSION['user_id']`).
   - `last_login` timestamp updated in `admins` table.
   - Redirects to `/admin/index.php` displaying wholesale KPIs.
5. In an incognito tab, submit incorrect passwords 5 consecutive times.
6. **Expected Result**:
   - Brute-force lockout triggered: *"Maximum attempts exceeded. Access locked for 15 minutes."*

### Test Case 6.2: Strict GST Validation & Product Publishing Guard
1. Navigate to `/admin/product-edit.php`.
2. Enter product name (e.g. `New Mango Nectar`), select brand `Campa`.
3. Leave GST field blank and attempt to check **"Active Product"**.
4. Submit form.
5. **Expected Result**:
   - Validation strictly blocks activation: *"A product cannot be activated if the GST rate is empty or unconfirmed."*
   - Product remains inactive until a valid numeric GST percentage is entered.
6. Enter valid GST (e.g. `12`), upload an image (JPG/PNG/WebP), and submit.
7. **Expected Result**:
   - Image verified with `getimagesize()`, renamed safely (`prod_[hash].ext`), and saved to `/public/uploads/products/`.
   - Product created and redirects to edit view to add size variants.

### Test Case 6.3: Variant Creation & Real-Time Customer Storefront Reflection
1. On the product edit page for the newly created product:
   - Add variant: `500ml Bottle`, unit price `₹25.00`, stock `100`, status `in_stock`.
2. Submit.
3. Open customer storefront `/public/products.php`.
4. **Expected Result**:
   - New product displays immediately under the Campa brand with authorized rate `₹25.00` and In-Stock badge.
   - No caching delay.

### Test Case 6.4: Bulk Inventory Management (`admin/stock.php`)
1. Navigate to `/admin/stock.php`.
2. Locate any variant, update stock quantity to `0`, and submit **Save All Inventory Updates**.
3. **Expected Result**:
   - Status automatically switches to `out_of_stock`.
   - On the customer storefront `/public/products.php`, the variant shows `Out of Stock` and the Add to Cart button is disabled.
4. Return to `/admin/stock.php`, increase stock to `150`, set status `in_stock`, and save.
5. **Expected Result**:
   - Immediately becomes available for purchase on customer storefront.

### Test Case 6.5: Order Pipeline & Fulfillment Status Advancement
1. Have a customer place an order on the storefront (e.g. `KE-YYYYMMDD-XXXX`).
2. Log into `/admin/orders.php`.
3. Order appears under **New Placed Orders** badge.
4. Click **Manage** to open `/admin/order-detail.php?id=[id]`.
5. Select status **"2. Dispatched (En Route in Van)"** and click **Update Fulfillment Status**.
6. Switch to customer account `/public/order.php?id=[id]`.
7. **Expected Result**:
   - Visual Fulfillment Tracker advances step 2 to complete (green checkmark / active milestone).
8. In admin, advance status to **"3. Delivered"**.
9. **Expected Result**:
   - Customer tracker shows step 3 complete (100% fulfilled).

### Test Case 6.6: Brand Management & Cascading Visibility
1. Navigate to `/admin/brands.php`.
2. Locate a brand (e.g. `Euro Chips`) and click **Deactivate**.
3. Open `/public/products.php`.
4. **Expected Result**:
   - Brand chip disappears from public filters, and all Euro Chips products are hidden from public view.
5. Click **Activate** in `/admin/brands.php`.
6. **Expected Result**:
   - Brand and products immediately reappear.

### Test Case 6.7: Business Settings, Thresholds & Policy Customization
1. Navigate to `/admin/settings.php`.
2. Modify `min_order_value` or update business address/phone.
3. Submit **Save All Settings & Policies**.
4. **Expected Result**:
   - Database updates instantly.
   - Public header, cart minimum threshold logic, and invoice templates reflect new values immediately.

---

## 7. Automated Authentication Lifecycle Suite (`tests/auth_lifecycle_suite.php`)

Run the comprehensive customer authentication suite directly via CLI:
```bash
php tests/auth_lifecycle_suite.php
```

### Coverage (30 Test Assertions):
1. **Registration Flow**: Full name, RFC email validation, 10-digit mobile, password mismatch, duplicate email/mobile rejection, and password policy validation.
2. **Login & Session Management**: Dual identifier login (email or mobile), inactive/suspended account rejection, invalid credentials rejection, session regeneration (`session_regenerate_id`), and secure server-side session initialization.
3. **Password Recovery & 6-Digit Demo OTP**: Cryptographic 6-digit OTP generation, SHA-256 hashed storage, 5-minute expiration, spam request cooldown limiter, single-use concurrency locks, attempt throttling, password reset token issuance, password update, and immediate revocation of old credentials.
4. **Customer Data Isolation (IDOR Defense)**: Customer A cannot view, modify, or download Customer B's profile, orders, or tax invoices.
5. **Cart & Checkout Authentication Integration**: Guest cart persistence through login, auto-redirection to checkout after authentication, server-side ₹2,000 minimum threshold enforcement, stock validation, and accurate tax calculations.

---

## 8. Automated Admin Authentication Suite (`tests/admin_auth_suite.php`)

Run the dedicated administrative authentication test suite directly via CLI:
```bash
php tests/admin_auth_suite.php
```

### Coverage (44 Test Assertions across 9 Core Requirements):
1. **Wrong Password & Generic Error**: Authentication failures return the generic message `"Invalid username or password"` to prevent user enumeration and timing attacks; failed attempts are incremented and logged in `admin_login_log`.
2. **5-Attempt Lockout (15 Minutes)**: Reaching 5 failed attempts locks the account for 15 minutes (`locked_until` set in the future); subsequent attempts while locked are rejected and recorded with status `locked`.
3. **Expired Session (30-Minute Idle Timeout)**: Inactive sessions exceeding 1,800 seconds are terminated; session data is purged, and the user is redirected with an expiration notice. Active requests continually refresh the activity window.
4. **Direct URL Access Without Login**: Unauthenticated visitors attempting direct URL access are redirected to `admin_login_url()` with an authentication required flash notice.
5. **Customer Session Denial (HTTP 403 Forbidden Gate)**: Authenticated retail customer accounts attempting to access ANY `/admin` endpoint are immediately denied with HTTP 403 Forbidden.
6. **CSRF Removal & Tamper Rejection**: All administrative forms and logout actions require valid CSRF tokens; missing or tampered tokens are rejected.
7. **First-Login Forced Password Change**: Accounts with `must_change_password = 1` are locked to `admin/change-password.php`; password strength engine enforces minimum 12 characters, uppercase, lowercase, numbers, and special symbols; successful change clears `must_change_password` to `0` and records an audit trail.
8. **CLI-Only Enforcement for `create_admin.php`**: The initial provisioning utility strictly checks `php_sapi_name() === 'cli'`, rejects HTTP requests with HTTP 403 Forbidden, validates passwords of at least 12 characters, and prints script deletion reminders.
9. **Activity Audit Log & Login Log Verification**: All CRUD operations on products, variants, prices, GST rates, stock levels, order statuses, content settings, and password updates are permanently written to `admin_audit_log` with IP addresses and timestamps; viewable via the read-only `/admin/activity-log.php` interface.




