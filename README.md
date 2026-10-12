# Kalika Enterprises – Wholesale Food & Beverage Distribution Website

A production-ready wholesale distribution web platform built with procedural/lightweight PHP 8.x, MySQL (PDO with prepared statements), HTML5, CSS3, and vanilla JavaScript.

Designed specifically to be deployed on standard shared PHP hosting (cPanel/Apache) as well as local development environments (XAMPP / PHP CLI).

---

## Business Summary & Hard Constraints

- **Business Name**: Kalika Enterprises
- **Address**: Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201
- **Email**: `deepakpawar4312@gmail.com`
- **Service Areas**: Niphad, Dindori, Chandwad, Sinnar, Yeola
- **Minimum Order Value**: ₹2,000 (Checkout strictly blocked below ₹2,000)
- **Delivery Policy**: 100% Free delivery across all service areas (Delivery fee always ₹0)
- **Fulfillment**: Confirmation within 24 hours; delivery within 2–3 working days
- **Refund Policy**: Returns accepted within 2 days of delivery, subject to terms
- **Payment Method**: Strictly offline payment upon delivery (No online gateways, no transaction commissions)
- **WhatsApp Policy**: Strictly no WhatsApp ordering buttons or links

---

## Directory Organization

```
/
├── config/
│   ├── config.sample.php       # Configuration blueprint
│   └── config.php              # Local environment configuration (gitignored)
├── database/
│   ├── schema.sql              # Database DDL (12 InnoDB tables)
│   ├── seed.sql                # Seed baseline data (confirmed brands & products)
│   ├── backup.php              # Automated daily rolling backup script (14-day retention)
│   ├── backup.sh               # Cron shell wrapper for Linux hosting
│   └── create_admin.php        # Initial admin provisioning utility (delete after use)
├── docs/                       # Project Documentation Suite
│   ├── DEPLOYMENT.md           # Step-by-step cPanel / shared PHP hosting deployment guide
│   ├── BACKUP_RESTORE.md       # Backup procedures (phpMyAdmin, mysqldump, cron) & disaster recovery
│   ├── ADMIN_GUIDE.md          # Business operations & admin portal user manual
│   ├── MAINTENANCE.md          # Safe code updates, credential rotation & monthly health checks
│   ├── HANDOVER.md             # Client handover dossier, credentials & recurring cost schedule
│   ├── SECURITY.md             # Security audit report, OWASP mitigations & server hardening
│   ├── TEST_REPORT.md          # Full QA execution report across all user flows (100% pass)
│   ├── TESTING.md              # Test specifications and execution procedures
│   └── DATABASE.md             # Database schema, table relationships & dictionary
├── includes/
│   ├── db.php                  # PDO connection wrapper (utf8mb4, ERRMODE_EXCEPTION)
│   ├── csrf.php                # CSRF token generator & validator
│   ├── functions.php           # Output escaping, currency formatting, flash messages
│   ├── auth.php                # Session security, customer & admin authentication
│   ├── cart.php                # Cart calculation engine & dual persistence
│   ├── header.php              # Shared responsive header with navigation & announcement
│   └── footer.php              # Shared footer with business details, policies & service areas
├── public/                     # Web root for customer-facing application
│   ├── index.php               # Home page
│   ├── products.php            # Catalogue with brand filter, search & variant switching
│   ├── about.php               # Company profile and service areas
│   ├── contact.php             # Contact desk and customer inquiry form
│   ├── policies.php            # Minimum order, delivery, and refund policies
│   ├── styleguide.php          # Living UI design system & token reference
│   ├── cart.php                # Cart with ₹2,000 minimum threshold enforcement
│   ├── checkout.php            # Order delivery confirmation & atomic order creation
│   ├── orders.php              # Customer order history
│   ├── order.php               # Order detail & fulfillment status tracker
│   ├── invoice.php             # Printable A4 wholesale tax invoice
│   ├── login.php               # Customer login with rate-limiting
│   ├── register.php            # New retailer registration
│   ├── forgot.php              # Free demo OTP password reset initiation
│   ├── reset.php               # New password entry with verified OTP
│   ├── profile.php             # Retailer profile management
│   ├── logout.php              # Session termination
│   ├── assets/
│   │   ├── css/style.css       # Mobile-first vanilla CSS design system
│   │   ├── js/main.js          # Responsive navigation & interactions
│   │   └── img/logo.svg        # Marked placeholder logo
│   └── uploads/
│       └── products/           # Product media (locked down via .htaccess)
├── admin/                      # Dedicated administrative management portal
│   ├── index.php               # Operations dashboard & 6 KPI metrics
│   ├── brands.php              # Brand partner management
│   ├── products.php            # Product catalog & safe soft-deletion
│   ├── product-edit.php        # Product & variant editor with image uploads & GST guard
│   ├── stock.php               # Bulk multi-SKU inventory updater
│   ├── orders.php              # Wholesale order pipeline & CSV export
│   ├── order-detail.php        # Order management & status advancement
│   ├── customers.php           # Retailer account directory & suspension toggle
│   ├── settings.php            # Enterprise parameters & policy text editor
│   ├── messages.php            # Customer inquiries inbox
│   ├── login.php               # Admin authentication with brute-force lockout
│   ├── logout.php              # Admin logout
│   └── change-password.php     # Admin password rotation
├── tests/
│   └── smoke.php               # Automated 43-point PHP smoke test suite
├── .gitignore
├── .htaccess                   # HTTPS redirection, security lockdown, no directory listing
├── PROJECT_CONTEXT.md          # Architectural context & rules
├── TODO_CONFIRM.md             # Pre-go-live client action checklist
├── PROGRESS.md                 # Implementation progress tracking across all 11 phases
└── README.md
```

---

## Local Setup Instructions

### Option 1: PHP Built-in Server (Fastest)

1. Ensure PHP 8.0+ is installed.
2. From the project root (`e:\Freelance Project`), run:
   ```bash
   php -S localhost:8000
   ```
3. Open `http://localhost:8000/public/` in your browser.

### Option 2: XAMPP Setup

1. Copy or clone this folder into your XAMPP `htdocs` directory:
   `C:\xampp\htdocs\kalika`
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Access the site at:
   `http://localhost/kalika/public/`
4. Access the admin portal at:
   `http://localhost/kalika/admin/`

---

## Production Shared Hosting Setup (cPanel / Apache)

1. **Document Root**:
   - If your host allows changing the document root, set it to the `/public` directory.
   - If not, keep the root `.htaccess` provided in this repository, which prevents directory browsing and restricts direct access to `/config`, `/includes`, `/database`, and `/docs`.
2. **Configuration**:
   - Copy `config/config.sample.php` to `config/config.php`.
   - Update `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
   - Change `APP_ENV` to `'production'` to ensure errors are logged to `logs/php_errors.log` rather than displayed on-screen.
3. **Database Import (Phase 2)**:
   - Import `database/schema.sql` and `database/seed.sql` via phpMyAdmin.
4. **Permissions**:
   - Ensure `public/uploads/products/` is writable by the web server (chmod 755).
