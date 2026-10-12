# Kalika Enterprises – Discovery & Pre-Go-Live Confirmation Checklist (TODO_CONFIRM)

This document tracks all unconfirmed business details, statutory tax records, catalog decisions, and digital assets required from the client (**Kalika Enterprises / Mr. Deepak**) prior to live launch.

---

## 1. Statutory & Business Details Checklist

| # | Item | Why It Matters | Client Action Required | Admin Update Location | Status |
|:---:|:---|:---|:---|:---|:---:|
| 1 | **Official GSTIN** | Required by law on B2B wholesale tax invoices and website footer. | Provide 15-character Maharashtra GSTIN (e.g., `27AAAAA0000A1Z5`). | `/admin/settings.php` &rarr; GSTIN | ⏳ Pending Client |
| 2 | **FSSAI License Number** | Mandatory food safety registration number for food & beverage wholesale distribution. | Provide 14-digit FSSAI license number. | `/admin/settings.php` &rarr; FSSAI Number | ⏳ Pending Client |
| 3 | **Warehouse Dispatch Address** | Prints on tax invoices and shows on Contact / About pages. | Provide exact physical warehouse/godown address in Nashik. | `/admin/settings.php` &rarr; Address | ⏳ Pending Client |
| 4 | **Official Business Phone** | Customer care, delivery coordination, and order follow-ups. | Provide primary 10-digit mobile/landline number. | `/admin/settings.php` &rarr; Contact Phone | ⏳ Pending Client |
| 5 | **Approved Brand Logo** | Replaces temporary SVG logo across website header, mobile drawer, and printable tax invoices. | Provide high-resolution logo (PNG / SVG). | Replace `/public/assets/img/logo.svg` | ⏳ Pending Client |

---

## 2. Product Catalogue & Brand Clearances

| # | Item | Current System Status | Client Action Required to Activate | Admin Update Location |
|:---:|:---|:---|:---|:---|
| 6 | **Sundar Glucose Biscuits** | **INACTIVE** (Missing GST rate). System blocks activation without GST. | Confirm applicable GST rate (`5%` or `18%`) with business accountant. | `/admin/product-edit.php?id=8` &rarr; Set GST %, then set Active |
| 7 | **Coca-Cola Brand Range** | **INACTIVE** (No confirmed wholesale pack sizes or prices). | Provide bottle variants (e.g., 250ml, 750ml, 2L), case tray sizes, and wholesale rates for Thums Up, Sprite, and Coca-Cola. | `/admin/brands.php` &rarr; Set Active<br>`/admin/products.php` &rarr; Add SKUs |
| 8 | **GST Rates Verification** | Initial tax estimates loaded: Campa (`28%`), Marie (`5%`), Bourbon/Coconut (`18%`), Euro Chips (`12%`). | Validate these tax percentages with business accountant. | `/admin/product-edit.php` &rarr; GST Rate (%) |
| 9 | **Opening Physical Inventory** | Seeded with arbitrary starter quantities (30 to 150 units). | Perform warehouse stock count and enter live quantities before launch. | `/admin/stock.php` &rarr; Bulk Stock Editor |
| 10 | **Product Packshot Photos** | Clean typography placeholders generated and displayed. | Take clean square (1:1) white-background photos of actual cases/bottles and upload. | `/admin/product-edit.php` &rarr; Upload Image |

---

## 3. Policy & Commercial Terms Confirmation

| # | Item | Current Platform Default | Client Action Required | Admin Update Location |
|:---:|:---|:---|:---|:---|
| 11 | **Minimum Order Value** | ₹2,000 hard threshold enforced at cart and checkout. | Confirm whether ₹2,000 threshold is optimal for launch. | `/admin/settings.php` &rarr; Minimum Order Value |
| 12 | **Free Delivery Zones** | Niphad, Dindori, Chandwad, Sinnar, and Yeola. | Confirm if any additional talukas or villages should be added or removed. | `/admin/settings.php` &rarr; Delivery Policy Text |
| 13 | **Return & Replacement Window** | 2-day inspection window on delivery for damaged or broken packaging. | Confirm return/replacement terms for damaged beverage crates. | `/admin/settings.php` &rarr; Returns Policy Text |
| 14 | **Offline Payment Terms** | Strictly Cash or offline UPI upon delivery van arrival. | Confirm delivery drivers have offline merchant QR codes for shopkeepers. | Commercial SOP (Offline) |

---

## 4. Hosting & Domain Go-Live Verification

| # | Item | Requirement | Status |
|:---:|:---|:---|:---:|
| 15 | **Domain Registration** | Client purchases and owns domain (e.g., `kalikaenterprises.com`). | ⏳ Pending Client Setup |
| 16 | **PHP 8 / MySQL 8 Shared Hosting** | Client procures cPanel hosting account. | ⏳ Pending Client Setup |
| 17 | **SSL Certificate** | Free Let's Encrypt / AutoSSL enabled on domain. | ⏳ To enable at deployment |
| 18 | **Default Admin Password Rotation** | Mandatory change of `Admin@1234` immediately upon deployment. | ⏳ To execute at first sign-in |

