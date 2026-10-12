# Kalika Enterprises – Phase-wise Build Prompt File

How to use this file with an AI coding assistant (Claude Code, Cursor, etc.):

1. Paste **Section A (Master Context)** first, once, at the start of the session. Save it also as `CLAUDE.md` / `PROJECT_CONTEXT.md` in the project root so the assistant always has it.
2. Then paste **one phase prompt at a time** (Phase 0 → Phase 10). Do not move on until that phase's "Done when" checks pass.
3. After each phase, ask the assistant to update `PROGRESS.md` with what was built and what is pending.

---

## A. MASTER CONTEXT (paste first)

```
You are a senior full-stack developer building a complete, production-ready website for
"Kalika Enterprises", a food and beverage distribution business. The site must be deployable
to ordinary PHP + MySQL shared hosting and reachable through a public URL (not XAMPP-only).

BUSINESS DETAILS
- Business name: Kalika Enterprises
- Address: Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant),
  Tal. Niphad, Dist. Nashik, Maharashtra – 422201
- Email: deepakpawar4312@gmail.com
- Service areas: Niphad, Dindori, Chandwad, Sinnar, Yeola
- Logo: use only the approved logo file at /public/assets/img/logo.* (client supplies it; use a
  clearly marked placeholder until then)

TECH STACK
- PHP 8.x (procedural or light MVC, no heavy framework), PDO with prepared statements only
- MySQL / MariaDB
- HTML5, CSS3 (mobile-first, responsive), vanilla JavaScript
- No paid APIs. No online payment. No SMS provider.

BUSINESS RULES (hard rules)
- Minimum order value: Rs 2,000 (block checkout below this with a clear message)
- Free delivery on orders of Rs 2,000 or more (so delivery charge is always Rs 0; show "FREE")
- Order confirmation within 24 hours; delivery in 2-3 working days
- Refund policy text: refund within 2 days, subject to applicable conditions
- Order statuses: Placed -> Dispatched -> Delivered
- NO online payment of any kind. NO WhatsApp ordering/links/buttons.
- NO Energy Drinks category. NO Khadke Chikki. NO unconfirmed brands, products or images.
- Never use stock photos. Products without a client-supplied photo show a neutral
  "Photo coming soon" placeholder.

CONFIRMED CATALOGUE (seed data only; all prices/GST must be editable in admin)
Brand: Campa  (GST 28% provided, VERIFY)
  Products: Campa Cola, Campa Orange, Campa Jeera, Campa Lemon
  Variants for each: 200ml Rs10, 500ml Rs20, 700ml Rs35, 1L Rs80
Brand: Sundar Marie (GST 5% provided, VERIFY)
  Variants: 20g Rs5, 45g Rs10, 95g Rs20
Brand: Sundar Bourbon (GST 18% provided, VERIFY)
  Variants: 500g Rs65
Brand: Sundar Coconut (GST 18% provided, VERIFY)
  Variants: 20g Rs5, 50g Rs10, 200g Rs30, 400g Rs55
Brand: Sundar Glucose (GST TO BE CONFIRMED)
  Variants: 20g Rs5, 45g Rs10, 95g Rs20
Brand: Euro Chips (GST 12% provided, VERIFY)
  Products: Euro Chips Salted, Euro Chips Masala, Euro Banana Chips
  Variants for each: 16g Rs5, 35g Rs10, 70g Rs20, 160g Rs50
Brand: Coca-Cola Range
  Products: Thums Up (GST 28% provided), Sprite. Variants, prices and GST for the range are
  NOT confirmed. Seed these products as INACTIVE with no variants, so they do not show
  publicly until the admin enters confirmed data.

OPEN QUESTION TO HANDLE IN CODE: it is not confirmed whether listed prices include GST.
Implement a single setting `prices_include_gst` (default: 1 = inclusive) in the settings table.
All totals, GST breakup and invoices must respect this setting. Flag any item with
unconfirmed GST (e.g. Sundar Glucose) as "GST pending" in admin and prevent it from being
published until a GST value is entered.

SECURITY REQUIREMENTS (apply in every phase)
- password_hash()/password_verify(); never store plain passwords
- PDO prepared statements for all queries
- Escape all output (htmlspecialchars); validate and sanitize all input server-side
- CSRF token on every POST form
- Secure session cookies (HttpOnly, SameSite, Secure on HTTPS); regenerate session ID on login
- Server-side authorization: a customer can only read their own profile/orders/invoices;
  admin pages are inaccessible without admin login
- Rate-limit login and OTP attempts
- Image uploads: allow jpg/png/webp only, size limit, random filenames, no script execution
  in the uploads folder
- Database credentials in config outside the web root where hosting allows; hide PHP errors
  from users in production; log them instead

CODING RULES
- Mobile-first responsive design; test at 360px, 768px, 1280px
- Keep code organised: /public, /admin, /includes, /config, /database, /docs
- Comment non-obvious logic; no dead code
- After each phase: list files created/changed, how to run it, and what to test
- Never invent business data. If something is unconfirmed, use a clearly marked placeholder
  and list it in TODO_CONFIRM.md
```

---

## PHASE 0 – Discovery & Confirmation Checklist

```
Create a file TODO_CONFIRM.md in the project root listing every unconfirmed item, with columns:
Item | Why it matters | Owner | Status.

Include at minimum:
1. Approved logo file
2. Product photos for every product
3. GST rate for Sundar Glucose
4. Coca-Cola range: products, variants, prices, GST (only Thums Up and Sprite named)
5. Verification of all GST rates provided
6. Whether prices are GST-inclusive or GST-extra
7. Business GSTIN, FSSAI number, state code (for tax invoices)
8. Opening stock quantities per variant
9. Refund policy conditions (eligibility, damaged/expired goods, how refunds are returned
   without online payment)
10. Business phone number for Contact page
11. Final domain name
12. Hosting account details (created in the client's name)

Also create PROGRESS.md with a checklist of Phases 1-10. Do not write application code yet.
```

**Done when:** `TODO_CONFIRM.md` and `PROGRESS.md` exist and the client has been sent the list.

---

## PHASE 1 – Project Setup & Architecture

```
Set up the project skeleton.

1. Create this structure:
   /public (index.php, about.php, contact.php, products.php, cart.php, checkout.php,
            orders.php, order.php, invoice.php, login.php, register.php, forgot.php,
            reset.php, profile.php, logout.php, policies.php, assets/css, assets/js,
            assets/img, uploads/products)
   /admin (index.php, login.php, ...)
   /includes (db.php, auth.php, csrf.php, functions.php, header.php, footer.php)
   /config (config.sample.php, config.php is gitignored)
   /database (schema.sql, seed.sql)
   /docs
2. Write includes/db.php with a PDO connection (utf8mb4, exceptions on, emulate prepares off).
3. Write csrf.php (generate + verify token helpers) and functions.php (escape helper, flash
   messages, money formatting in INR, redirect helper).
4. Create a shared header/footer with responsive navigation (Home, Products, About, Contact,
   Login/Account, Cart with item count) and the footer showing business address, email and
   service areas.
5. Add .gitignore (config.php, uploads), a README.md with local setup steps (XAMPP) and
   hosting setup steps, and an .htaccess that blocks directory listing and forces HTTPS in
   production.

Do not build features yet. Give me a running skeleton with the header/footer on every page.
```

**Done when:** every page loads with the shared header/footer, no PHP errors, DB connection works.

---

## PHASE 2 – Database Design & Seed Data

```
Write database/schema.sql and database/seed.sql (MySQL, InnoDB, utf8mb4).

Tables:
- users(id, name, mobile UNIQUE, email NULL, password_hash, address, created_at, updated_at)
- admins(id, username UNIQUE, password_hash, last_login, created_at)
- brands(id, name, slug UNIQUE, is_active, sort_order)
- products(id, brand_id FK, name, slug, image, description, gst_percent DECIMAL(5,2) NULL,
  is_active, created_at, updated_at)
- product_variants(id, product_id FK, size_label, price DECIMAL(10,2), stock_qty INT,
  stock_status ENUM('in_stock','out_of_stock'), is_active)
- cart_items(id, user_id FK, variant_id FK, quantity, UNIQUE(user_id, variant_id))
- orders(id, order_no UNIQUE, user_id FK, customer_name, mobile, delivery_address,
  subtotal, gst_total, delivery_charge, grand_total, prices_include_gst, status
  ENUM('Placed','Dispatched','Delivered'), created_at, updated_at)
- order_items(id, order_id FK, product_name, brand_name, variant_label, unit_price,
  gst_percent, quantity, line_total)  -- snapshot copy so old invoices never change
- password_otps(id, user_id FK, otp_hash, expires_at, used_at NULL, attempts, created_at)
- login_attempts(id, identifier, ip, attempted_at)
- settings(setting_key PK, setting_value TEXT)  -- business info, policies,
  prices_include_gst, min_order_value=2000, etc.

Seed:
- The 7 confirmed brands, all products and variants exactly as listed in the master context
- Coca-Cola products seeded INACTIVE, no variants
- Sundar Glucose seeded with gst_percent NULL and is_active = 0 until GST is entered
- Default settings (business name, address, email, service areas, min order 2000,
  delivery policy, refund policy text, confirmation/delivery timelines)
- NO admin password in seed.sql: create database/create_admin.php (CLI/one-time) that
  prompts for a username and strong password and inserts a hashed record, then instructs
  the owner to delete the script

Add useful indexes (mobile, order_no, product slug, brand_id, user_id).
Document the schema in docs/DATABASE.md with an ER summary.
```

**Done when:** schema imports cleanly, seed loads, product/variant counts match the master context.

---

## PHASE 3 – UI/UX Design System

```
Create the visual design system in /public/assets/css/style.css and apply it to the shared
layout.

- Mobile-first, clean, professional look suited to a food distribution brand
- Define CSS variables for colours (derive from the logo; if not yet supplied, use a neutral
  palette with one accent colour and mark it as temporary), typography scale, spacing, radius
- Components: buttons, form fields, alerts/flash messages, cards, badges (In Stock green,
  Out of Stock red, GST pending amber), tables that scroll horizontally on mobile, modals
- Sticky mobile-friendly nav with hamburger menu and a cart icon with count
- Accessible: visible focus states, labels on all inputs, sufficient contrast, tap targets
  of at least 44px
- Print stylesheet for invoices (hide nav/footer, clean A4 layout)

Create a static /public/styleguide.php page showing every component so I can review it.
Do not use any external UI frameworks that require a build step.
```

**Done when:** styleguide renders correctly at 360px, 768px and 1280px.

---

## PHASE 4 – Public Pages & Product Catalogue

```
Build the public-facing pages using data from the database.

1. Home: hero section with business name and logo, short intro, the 7 brands as clickable
   cards (only active brands), service areas, key policies strip (min order Rs 2,000, free
   delivery, 2-3 day delivery, 24-hour confirmation), call-to-action to Products.
2. About Us: company information from the settings table.
3. Contact Us: address, email (mailto link), service areas list, a contact form that stores
   messages in a contact_messages table and shows them in admin (add the table). No WhatsApp.
4. Policies page: minimum order, free delivery, delivery time, order confirmation time,
   refund policy (2 days, subject to conditions). Text comes from the settings table.
5. Products page:
   - Brand filter (chips/dropdown), live search by product name (server-side, with
     debounced JS enhancement), URL-based filters so links are shareable
   - Product cards: actual image or "Photo coming soon" placeholder, name, brand, variant
     selector (size/price), GST %, stock badge, quantity input, Add to Cart button
   - Selecting a variant updates price and stock badge without page reload
   - Out-of-stock variants have a disabled Add to Cart button
   - Only active products with active variants and a defined GST are shown
   - Pagination or lazy loading if more than 24 products

All queries use prepared statements. Pages must be SEO-friendly (title, meta description).
```

**Done when:** every confirmed product appears with the right variants/prices; filter and search work; Coca-Cola and Glucose do not show publicly.

---

## PHASE 5 – Customer Accounts & Demo OTP

```
Build customer authentication and profile.

1. Register: name, mobile (10-digit Indian mobile validation), optional email, password
   (min 8 chars) + confirm, delivery address. Mobile must be unique.
2. Login with mobile + password; logout (POST + CSRF); session regeneration; remember
   nothing insecure. Rate-limit failed logins (e.g. 5 attempts / 15 minutes per mobile+IP)
   using the login_attempts table.
3. Profile page: view and edit name, mobile, email, address; change password (requires
   current password). A customer can only access their own profile.
4. Forgot Password with FREE DEMO OTP:
   - User enters registered mobile
   - Generate a random 6-digit OTP, store only its hash with expires_at = now + 5 minutes
   - Display the OTP on the page in a clearly styled "Demo OTP" box (explain it is for
     demonstration, no SMS is sent)
   - User enters OTP; verify hash, not expired, not used, attempts < 5
   - On success mark OTP used immediately (single use) and allow setting a new password
   - Reset page must be reachable only after successful OTP verification (session flag),
     and the flag is destroyed after use
   - Invalidate older unused OTPs when a new one is generated
   - Generic error messages that do not reveal whether a mobile is registered where possible
     (note the demo exception in a code comment)

Write brief manual test steps in docs/TESTING.md for each flow.
```

**Done when:** OTP is 6 digits, expires after 5 minutes, fails on second use, wrong OTP rejected, password actually changes.

---

## PHASE 6 – Cart, Checkout, Orders & Invoice

```
Build the cart and ordering system.

CART
- Add to cart (variant + quantity), remove item, increase/decrease quantity, all via POST
  with CSRF and also a JS-enhanced AJAX path that updates totals live
- Logged-in users: cart stored in cart_items (persists across logins/devices)
- Guests: cart in session; on login, merge into the database cart
- Validate quantity (1-999 integer), variant active, product active, stock available
- Cart page shows per line: product, brand, variant, unit price, quantity, GST %, line
  total; summary shows subtotal, GST breakup (respecting prices_include_gst), delivery
  "FREE", and grand total. Totals recalculated server-side on every request; never trust
  client-submitted prices.
- Minimum order: if subtotal < Rs 2,000 show "Add Rs X more to reach the Rs 2,000 minimum"
  and disable checkout.

CHECKOUT
- Requires login. Prefill name, mobile, delivery location from profile; customer may edit
  for this order.
- Re-validate stock and minimum order inside a database transaction; decrement stock; if
  stock reaches 0 set out_of_stock.
- Create unique order_no (e.g. KE-YYYYMMDD-XXXX), insert orders + order_items snapshots
  (name, brand, variant, price, GST, quantity), clear the cart, status = 'Placed'.
- Confirmation page: order number, summary, message "Your order will be confirmed within 24
  hours. Estimated delivery: 2-3 working days." State clearly that payment is handled
  offline/at delivery as agreed with the business (do not add any online payment UI).

CUSTOMER ORDERS
- Order history list (own orders only), order detail page with a visual status tracker
  Placed -> Dispatched -> Delivered, with timestamps where available.
- Invoice page (invoice.php?order=...) showing: business name, address, GSTIN (from
  settings, if set), email, order number, date, customer details, item table with GST
  percent, subtotal, GST total, delivery FREE, grand total, policy footer. Print-friendly
  and a "Download PDF" option (use a lightweight library such as Dompdf installed via
  Composer, or fall back to browser print-to-PDF if Composer is unavailable on the host).
- Authorization: accessing another customer's order/invoice by changing the ID must
  return 403/404.
```

**Done when:** Rs 1,999 cart blocked, Rs 2,000 allowed; totals and GST match a manual calculation; another user's order URL is denied.

---

## PHASE 7 – Admin Panel

```
Build the admin panel under /admin with its own login, separate from customer login.

AUTH
- Separate admin session namespace, login with username + password, rate limiting and
  lockout, CSRF, logout, change-password page. Optionally restrict by a non-obvious admin
  URL path configurable in config.php.

DASHBOARD
- Cards: new (Placed) orders, Dispatched, Delivered, total customers, low-stock items
  (stock_qty <= 10), products pending GST; a table of the 10 latest orders.

CATALOGUE MANAGEMENT
- Brands: add/edit/activate/deactivate
- Products: add/edit/delete (soft delete if the product has past orders), assign brand,
  description, GST %, active toggle
- Image upload/replace with validation (jpg/png/webp, max ~2MB, random filename, verify
  real image with getimagesize, resize/compress if GD is available)
- Variants: add/edit/delete per product (size label, price, stock quantity, In Stock /
  Out of Stock toggle, active)
- Bulk stock update table for quick editing
- A product cannot be activated if GST is empty

CUSTOMERS
- List/search customers, view profile and their orders, deactivate account

ORDERS
- Filterable list (status, date, search by order no/mobile), order detail, change status
  (Placed -> Dispatched -> Delivered only forward by default), view/print invoice
- Optional: export orders to CSV

CONTENT
- Edit business info, address, email, service areas, policy texts, min order value,
  prices_include_gst setting, GSTIN/FSSAI, and view contact-form messages

All admin actions use prepared statements, CSRF tokens and server-side admin checks.
Changes must be visible on the customer site immediately (no caching issues).
```

**Done when:** admin can add a product with image, change a price, toggle stock, and update an order status; each change shows correctly on the public site and customer account.

---

## PHASE 8 – Security Hardening & Review

```
Perform a security review of the entire codebase and fix issues. Produce docs/SECURITY.md
with findings and fixes.

Check and fix:
1. Every SQL query uses prepared statements (grep for string-concatenated queries)
2. Every echo of dynamic data is escaped
3. Every POST form has CSRF verification
4. Session settings: HttpOnly, Secure, SameSite=Lax/Strict, regenerate on login, idle
   timeout, destroy on logout
5. Authorization: test accessing other users' orders, invoices and profiles by ID changes
   (IDOR); test /admin pages while logged out and while logged in as a customer
6. File upload safety; uploads folder has an .htaccess that disables PHP execution
7. Rate limiting works on login, admin login and OTP
8. Security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy, basic
   Content-Security-Policy
9. Production error handling: display_errors off, errors logged to a non-public file
10. config.php, schema/seed files and docs are not web-accessible
11. Passwords hashed; no secrets committed to Git
12. Input validation: mobile format, quantity ranges, text lengths, price/stock numeric ranges

List any remaining risks honestly, including the fact that a demo OTP displayed on screen
is not suitable for real-world account security.
```

**Done when:** all items pass and `docs/SECURITY.md` lists findings and fixes.

---

## PHASE 9 – Testing & QA

```
Create docs/TEST_REPORT.md and run through this checklist, recording Pass/Fail and fixing
failures:

REGISTRATION/LOGIN: valid and invalid signup, duplicate mobile, wrong password, logout,
session expiry
PASSWORD RESET: OTP is 6 digits; works within 5 minutes; fails after 5 minutes; fails on
second use; wrong OTP rejected; new password works; old password no longer works
PROFILE: edit name/mobile/address and confirm it persists
CATALOGUE: brand filter, search, variant switching, correct price/GST/stock; unconfirmed
products not visible
CART: add, remove, +/- quantity, correct totals and GST, out-of-stock blocked, cart
persists after logout/login, guest cart merges on login
MINIMUM ORDER: Rs 1,999 blocked; Rs 2,000 allowed; delivery shown as FREE
ORDERS: unique order number, correct invoice, appears in history, status tracker correct,
stock decremented, price snapshot unaffected when admin later changes price
ADMIN: add/edit/delete product, image upload, variant price, GST, stock toggle, order
status change, customer view, content edit; changes appear on customer site
SECURITY: IDOR attempts, admin access without login, SQL injection and XSS strings in all
form fields, CSRF token removal
DEVICES: Android Chrome, iPhone Safari, tablet, desktop; slow network
PERSISTENCE: data intact after closing and reopening the browser and after a server restart
BACKUP: take a database backup and restore it into a clean database successfully

Also add a simple automated PHP smoke-test script (tests/smoke.php) that checks DB
connection, required tables, and that key pages return HTTP 200.
```

**Done when:** every row in `TEST_REPORT.md` is Pass.

---

## PHASE 10 – Deployment, Backup & Handover

```
Prepare production deployment and handover documentation.

1. docs/DEPLOYMENT.md: step-by-step for typical cPanel/shared PHP hosting:
   - create MySQL database and user, import schema.sql and seed.sql
   - upload files, set document root to /public (or provide an alternative layout for hosts
     that cannot change it)
   - copy config.sample.php to config.php and fill credentials
   - set folder permissions for uploads
   - enable SSL and force HTTPS
   - run create_admin.php once, then DELETE it
   - set production flags (errors hidden, logging on)
   - connect the client's domain
2. docs/BACKUP_RESTORE.md: manual backup via phpMyAdmin and via mysqldump; a cron-based
   daily backup script (database/backup.php or backup.sh) that keeps the last 14 dumps
   outside the web root; restore steps; also backing up /uploads
3. docs/ADMIN_GUIDE.md: with screenshots placeholders - how to add a product, change a
   price, update stock, set GST, upload an image, process an order (Placed -> Dispatched ->
   Delivered), print an invoice, change the admin password, edit policies
4. docs/MAINTENANCE.md: updating PHP code safely, taking a backup before updates, how to
   rotate the admin password, how to add a new brand, what to check monthly
5. docs/HANDOVER.md: checklist of what the client must receive - live URL, admin login
   (to be changed immediately), hosting/domain/database/repository ownership details, source
   code, DB files, test report, and a table showing which services have recurring charges
   (domain, hosting, SSL, maintenance) with fields to fill in
6. Final smoke test on the live URL from a phone and a laptop: register, login, add to
   cart, place a Rs 2,000+ order, view invoice, admin status update, logout.

List everything that still needs client input in TODO_CONFIRM.md before go-live.
```

**Done when:** live public URL works end to end, backup/restore is proven, credentials and ownership are handed over.

---

## PHASE 11 (Optional) – Post-Launch Improvements

```
Only after go-live and client approval, propose and implement one at a time:
- Email notification to the business on each new order (using the host's mail or SMTP)
- Email OTP in place of the on-screen demo OTP
- Low-stock email alerts
- Sales reports by date, brand and area
- Reorder from past orders button
Each must keep the original exclusions: no online payment, no WhatsApp ordering.
```

---

## Quick Reference – Prompt Order

| Step | Paste | Result |
|------|-------|--------|
| 1 | Section A Master Context | AI understands the project |
| 2 | Phase 0 | Open-items list and progress tracker |
| 3 | Phase 1 | Project skeleton |
| 4 | Phase 2 | Database and seed data |
| 5 | Phase 3 | Design system |
| 6 | Phase 4 | Public pages and catalogue |
| 7 | Phase 5 | Accounts and demo OTP |
| 8 | Phase 6 | Cart, orders, invoices |
| 9 | Phase 7 | Admin panel |
| 10 | Phase 8 | Security hardening |
| 11 | Phase 9 | Testing |
| 12 | Phase 10 | Deployment and handover |
