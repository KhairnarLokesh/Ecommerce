# Kalika Enterprises – Project Handover Dossier & Acceptance Certificate

This document represents the formal handover agreement and asset transfer dossier for the **Kalika Enterprises** Wholesale Food & Beverage Web Portal, developed according to the specifications in [`Kalika_Enterprises_Build_Prompts.md`](file:///e:/Freelance%20Project/Kalika_Enterprises_Build_Prompts.md).

---

## 1. Project Overview & Scope Summary

- **Client**: Kalika Enterprises (Proprietor: Mr. Deepak / Management)
- **Nature of Business**: Wholesale Food & Beverage FMCG Distribution
- **Target Distribution Hub**: Nashik, Maharashtra
- **Service & Free Delivery Territories**: Niphad, Dindori, Chandwad, Sinnar, Yeola
- **Core Technology Stack**: Native PHP 8.x, MySQL 8.0 / MariaDB 10.4+, Custom Mobile-First Vanilla CSS (no external build tools, frameworks, or CDN dependencies).
- **Hard Business Rules Enforced**:
  - Strictly offline payments on delivery (Cash / Van UPI). No payment gateways.
  - Strictly no WhatsApp ordering. All orders routed through secure portal accounts.
  - ₹2,000 hard minimum order value enforced at checkout.
  - Free regional delivery across all 5 serviced talukas.
  - Strict GST publishing rule (products cannot be made public without confirmed GST %).

---

## 2. Deliverables Inventory

All codebase assets, database structures, and documentation manuals have been delivered in full:

| Category | Deliverable Asset | Description |
|:---|:---|:---|
| **Frontend** | `/public/` | Storefront pages: Home, About, Catalogue, Contact, Policies, Styleguide |
| **Accounts** | `/public/` | Customer registration, rate-limited login, profile management, Demo OTP password recovery |
| **Wholesale Core**| `/public/` & `/includes/` | Session & DB cart, ₹2,000 minimum threshold guard, atomic checkout with `FOR UPDATE` stock locking, order history, live 3-stage order tracker, A4 print tax invoice |
| **Admin Panel** | `/admin/` | Dedicated admin authentication, 6 KPI dashboard, brand manager, product/variant editor, bulk stock manager, customer directory, order lifecycle manager, enterprise settings, contact inbox |
| **Database** | `/database/` | `schema.sql` (12 InnoDB tables), `seed.sql` (confirmed brands, SKUs, settings), `backup.php` & `backup.sh` (daily rolling 14-day retention utility) |
| **Security** | Whole codebase | 100% PDO prepared statements, XSS escaping, CSRF protection, session hardening, multi-layer `.htaccess` blocks on internal folders, script execution denial in uploads |
| **Documentation**| `/docs/` | `DEPLOYMENT.md`, `BACKUP_RESTORE.md`, `ADMIN_GUIDE.md`, `MAINTENANCE.md`, `SECURITY.md`, `TESTING.md`, `TEST_REPORT.md`, `DATABASE.md` |

---

## 3. Credentials & Access Dossier

> [!WARNING]
> **MANDATORY SECURITY NOTICE**: The default administrator credentials seeded below MUST be changed immediately upon deployment via `/admin/change-password.php`.

### A. Web Application & Administrative Access
- **Production Storefront URL**: `https://kalikaenterprises.com/` *(or client's chosen domain)*
- **Administrative Portal URL**: `https://kalikaenterprises.com/admin/login.php`
- **Initial Default Admin Username**: `admin`
- **Initial Default Admin Password**: `Admin@1234`
- **Password Status**: [ ] Changed to private client password on: `____________________`

### B. Hosting & Infrastructure Credentials (To Be Filled by Client)
- **Domain Registrar**: `________________________________________` (e.g. GoDaddy / Hostinger)
- **Domain Registrar Account ID**: `________________________________________`
- **Hosting Provider**: `________________________________________` (e.g. cPanel Linux Shared Hosting)
- **cPanel / Server URL**: `https://serverXX.yourhosting.com:2083`
- **cPanel Master Username**: `________________________________________`
- **cPanel Master Password**: `________________________________________`

### C. MySQL Database Credentials (As configured in `config/config.php`)
- **Database Server**: `localhost` (Port: `3306`)
- **Database Name**: `________________________________________`
- **Database Username**: `________________________________________`
- **Database Password**: `________________________________________`

### D. Source Code Repository Ownership
- **Git Repository URL**: `________________________________________`
- **Repository Access Transferred To Client**: [ ] Yes &nbsp;&nbsp;&nbsp; [ ] Not Applicable (Direct ZIP Archive)

---

## 4. Recurring Operational Cost Schedule

To maintain continuous, uninterrupted web operations, the client should record and track the following recurring service obligations:

| Service Component | Provider / Registrar | Billing Cycle | Est. Annual Cost (₹) | Renewal Due Date | Notes |
|:---|:---|:---|:---:|:---:|:---|
| **Domain Name Registration** | GoDaddy / Hostinger / Namecheap | Annual (1–3 yrs) | ₹800 – ₹1,200 | `DD/MM/YYYY` | Maintain `.com` or `.in` ownership |
| **Linux PHP Shared Hosting** | Hostinger / cPanel Shared / Bluehost | Annual (1–3 yrs) | ₹3,000 – ₹5,500 | `DD/MM/YYYY` | Requires PHP 8.1+ & MySQL 8.0+ |
| **SSL Security Certificate** | Let's Encrypt / cPanel AutoSSL | Auto-Renewing | **FREE (₹0)** | Auto (Every 90d) | Included free with cPanel hosting |
| **Database & Media Backups** | Automated Server Utility + Offsite | Ongoing | **FREE (₹0)** | Automated Daily | 14-day local rolling retention |
| **Website Maintenance** | Developer Support / In-House | Monthly / Annual | `[Optional Retainer]` | `DD/MM/YYYY` | Routine code & log maintenance |
| **Transactional SMS / OTP (Optional Post-Launch)** | Fast2SMS / MSG91 (DLT Compliant) | Pay-per-credit | *Optional (~₹0.20/SMS)* | As consumed | Not required for Demo OTP |

---

## 5. Pre-Go-Live Outstanding Client Inputs

Before officially launching the website to wholesale retail grocery buyers, the client must finalize the items documented in [`TODO_CONFIRM.md`](file:///e:/Freelance%20Project/TODO_CONFIRM.md):

1. **GSTIN & FSSAI Numbers**: Input verified 15-digit GSTIN and 14-digit FSSAI numbers into `/admin/settings.php` for tax invoice display.
2. **Official High-Resolution Logo**: Provide final SVG/PNG company brand logo to replace the vector placeholder in `/public/assets/img/logo.svg`.
3. **Unconfirmed Brands (Coca-Cola & Sundar Glucose)**:
   - Provide confirmed wholesale pricing, pack sizes, and GST tax classifications.
   - Once verified, toggle brands to **Active** in `/admin/brands.php`.
4. **Warehouse Physical Address & Direct Support Phone**: Update warehouse dispatch address in `/admin/settings.php`.

---

## 6. Post-Handover Warranty & Technical Support

- **Bug-Fix Warranty**: A 30-day warranty starting from the sign-off date covers corrections for any software bugs, defects, or deviations from the agreed specifications in [`Kalika_Enterprises_Build_Prompts.md`](file:///e:/Freelance%20Project/Kalika_Enterprises_Build_Prompts.md).
- **Scope Exclusions**: New feature additions, external payment gateway integrations, WhatsApp ordering bots, or hosting environment migrations beyond the initial deployment are outside the initial project scope and may be quoted separately under Phase 11 Post-Launch Improvements.

---

## 7. Formal Handover Sign-Off & Acceptance

By signing below, the Client confirms that all 11 phases of the Kalika Enterprises web portal have been delivered, verified against the automated test report (`docs/TEST_REPORT.md`), and that administrative credentials and documentation have been received.

<br>

**Delivered By (Developer / Lead Engineer):**  
Name: `________________________________________`  
Signature: `___________________________________`  
Date: `_____ / _____ / 2026`  

<br>

**Accepted By (Client / Kalika Enterprises):**  
Name: `Deepak / Authorized Representative`  
Signature: `___________________________________`  
Date: `_____ / _____ / 2026`  
