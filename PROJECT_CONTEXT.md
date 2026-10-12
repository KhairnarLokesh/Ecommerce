# Kalika Enterprises – Master Project Context

## 1. Business Details
- **Business Name**: Kalika Enterprises
- **Address**: Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201
- **Email**: deepakpawar4312@gmail.com
- **Service Areas**: Niphad, Dindori, Chandwad, Sinnar, Yeola
- **Logo**: Approved logo file at `/public/assets/img/logo.*` (Client supplies; use clearly marked placeholder until supplied)

---

## 2. Tech Stack & Architecture
- **Language / Runtime**: PHP 8.x (procedural or lightweight MVC, strictly no heavy frameworks)
- **Database**: MySQL / MariaDB (PDO with prepared statements exclusively, `utf8mb4`, exceptions enabled, emulate prepares disabled)
- **Frontend**: HTML5, CSS3 (mobile-first, responsive, custom vanilla CSS), Vanilla JavaScript
- **Constraints**: No paid APIs, no online payment gateways, no SMS gateway providers. Deployable to shared hosting with public URL capability.

---

## 3. Strict Business Rules (Hard Constraints)
- **Minimum Order Value**: ₹2,000 (Checkout strictly blocked below ₹2,000 with informative message).
- **Delivery Fee**: Free delivery on all valid orders (₹2,000+), delivery charge always ₹0 and labeled "FREE".
- **Fulfillment Timelines**: Order confirmation within 24 hours; order delivery in 2–3 working days.
- **Refund Policy**: Refund window within 2 days, subject to applicable conditions.
- **Order Lifecycle**: `Placed` -> `Dispatched` -> `Delivered`.
- **Payment Method**: Offline / Cash on Delivery / Delivery Settlement only. **NO online payment UI or gateways**.
- **No WhatsApp Ordering**: Strictly no WhatsApp ordering buttons, links, or widgets.
- **Excluded Categories/Products**: Strictly **NO** Energy Drinks category, **NO** Khadke Chikki, and **NO** unconfirmed products/brands.
- **Image Policy**: Never use stock photos. Missing photos display a neutral `"Photo coming soon"` placeholder.

---

## 4. Confirmed Product Catalogue (Seed Baseline)
All catalogue prices, stock, and GST must remain editable via the Admin panel.

1. **Campa** (GST: 28% provided, *VERIFY*)
   - *Products*: Campa Cola, Campa Orange, Campa Jeera, Campa Lemon
   - *Variants*: 200ml (₹10), 500ml (₹20), 700ml (₹35), 1L (₹80)
2. **Sundar Marie** (GST: 5% provided, *VERIFY*)
   - *Variants*: 20g (₹5), 45g (₹10), 95g (₹20)
3. **Sundar Bourbon** (GST: 18% provided, *VERIFY*)
   - *Variants*: 500g (₹65)
4. **Sundar Coconut** (GST: 18% provided, *VERIFY*)
   - *Variants*: 20g (₹5), 50g (₹10), 200g (₹30), 400g (₹55)
5. **Sundar Glucose** (GST: *TO BE CONFIRMED*)
   - *Variants*: 20g (₹5), 45g (₹10), 95g (₹20)
   - *Rule*: Seed as inactive (`is_active = 0`, `gst_percent = NULL`). Prohibit publishing until GST is configured.
6. **Euro Chips** (GST: 12% provided, *VERIFY*)
   - *Products*: Euro Chips Salted, Euro Chips Masala, Euro Banana Chips
   - *Variants*: 16g (₹5), 35g (₹10), 70g (₹20), 160g (₹50)
7. **Coca-Cola Range** (GST: Thums Up 28% provided, others unconfirmed)
   - *Products*: Thums Up, Sprite
   - *Rule*: Variants, pricing, and GST are unconfirmed. Seed as **INACTIVE** with no variants so they never appear publicly until confirmed.

---

## 5. GST Architecture & Open Questions
- System setting `prices_include_gst` (default: `1` = inclusive) in the `settings` table.
- All order totals, cart calculations, GST breakup, and invoices must adhere to `prices_include_gst`.
- Products with pending GST are flagged as `"GST pending"` in admin and blocked from public display.

---

## 6. Security Standards (Enforced Across All Phases)
- **Passwords**: `password_hash()` and `password_verify()` with bcrypt/Argon2.
- **SQL Injection**: Exclusively PDO prepared statements with bound parameters.
- **XSS Prevention**: Context-aware escaping via `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **CSRF Protection**: Synchronizer token pattern on every POST form.
- **Session Security**: Secure session cookie flags (`HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS), session regeneration on authentication.
- **Authorization & IDOR**: Strict server-side ownership validation for profiles, orders, and invoices; administrative routes locked behind admin sessions.
- **Rate Limiting**: Failed login and OTP attempt throttling via tracking tables.
- **Upload Security**: Extension whitelisting (`jpg`, `png`, `webp`), MIME type verification, unique randomized filenames, and `.htaccess` disabling script execution in uploads directory.
- **Environment & Error Handling**: Production error masking (`display_errors = Off`), central error logging, config credentials held outside web root where possible.

---

## 7. Directory Organization
- `/public`: Customer-facing web root (`index.php`, `products.php`, `cart.php`, `checkout.php`, `orders.php`, etc., `/assets`, `/uploads`)
- `/admin`: Administrative management interface (`index.php`, `products.php`, etc.)
- `/includes`: Core shared libraries (`db.php`, `auth.php`, `csrf.php`, `functions.php`, `header.php`, `footer.php`)
- `/config`: Configuration templates (`config.sample.php`) and runtime configuration (`config.php`, gitignored)
- `/database`: Migration schemas (`schema.sql`), baseline seed records (`seed.sql`), setup utilities
- `/docs`: Technical specifications, security reviews, test logs, and client handover guides
