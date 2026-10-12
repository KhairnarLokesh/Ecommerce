# Kalika Enterprises – Database Backup & Disaster Recovery Guide

This operational manual documents comprehensive procedures for backing up, maintaining, and restoring the **Kalika Enterprises** wholesale database and media asset repository. Adhering to these procedures ensures zero data loss and rapid recovery in the event of hardware failures, server migrations, or accidental data modifications.

---

## 1. Backup Overview & Architecture

The Kalika Enterprises platform relies on two stateful assets that must be protected:
1. **Relational Database (`kalika_enterprises`)**: Contains all core business data (users, products, variants, orders, order items, settings, and audit logs).
2. **Product Media Files (`/public/uploads/products/`)**: Contains uploaded product packshot images.

### Retention Strategy
- **Daily Automated Rolling Dumps**: Kept locally on the server in `/database/backups/` for **14 days**.
- **Weekly Offsite Backups**: Downloaded and stored securely off-server by the business owner (e.g., Google Drive or local external storage).

---

## 2. Automated Daily Backups (Cron-Based)

The platform includes a built-in automated backup utility [`database/backup.php`](file:///e:/Freelance%20Project/database/backup.php) and bash wrapper [`database/backup.sh`](file:///e:/Freelance%20Project/database/backup.sh).

### How It Works:
- Runs headless via PHP CLI or secure Admin session.
- Generates a full SQL dump named `kalika_backup_YYYYMMDD_HHMMSS.sql`.
- Disables and reenables `FOREIGN_KEY_CHECKS` to preserve referential integrity.
- Stores dumps in `/database/backups/`.
- Automatically enforces the **14-day rolling retention policy** by deleting dumps older than the 14 most recent files.
- The `/database/backups/` directory is hardened with an internal `.htaccess` file denying all public HTTP access.

### Setting Up cPanel Cron Job
1. Log in to cPanel and search for **Cron Jobs**.
2. Under **Add New Cron Job**, select **Once Per Day (0 2 * * *)** (Runs daily at 2:00 AM).
3. In the **Command** field, enter the following command (substituting your actual cPanel username and server PHP path):
   ```bash
   0 2 * * * /usr/local/bin/php /home/username/public_html/database/backup.php > /dev/null 2>&1
   ```
4. Click **Add New Cron Job**.
5. Verify initial execution by checking `/database/backups/` the following morning.

---

## 3. Manual Database Backup Procedures

### Method A: phpMyAdmin (Web Interface)
Recommended for non-technical administrators or before running major software updates:
1. Log into cPanel and click **phpMyAdmin**.
2. In the left panel, select your database (e.g., `kalika_enterprises` or `cpaneluser_kalika`).
3. Click the **Export** tab in the top navigation bar.
4. Choose the **Quick** export method (Format: **SQL**).
5. *(Optional for large databases)*: Select **Custom**, check **Zipped** under the compression section.
6. Click **Export** (or **Go**).
7. Save the downloaded `.sql` or `.sql.zip` file to a secure local folder.

### Method B: Command-Line (`mysqldump`)
Recommended for system administrators or scheduled shell scripts:
```bash
# Full database export with UTF-8 character encoding and single transaction
mysqldump -u username -p \
  --default-character-set=utf8mb4 \
  --single-transaction \
  --quick \
  kalika_enterprises > kalika_backup_$(date +%Y%m%d_%H%M%S).sql
```
*Windows PowerShell Note:* In Windows PowerShell, run:
```powershell
cmd /c "mysqldump -u root -p kalika_enterprises > kalika_backup_manual.sql"
```

---

## 4. Media Asset Backup (`/public/uploads/`)

Database records reference product images located in `/public/uploads/products/`. Always back up this directory alongside database dumps.

### Method A: cPanel File Manager (Zip Archive)
1. Open cPanel **File Manager**.
2. Navigate to `public_html/public/uploads/`.
3. Select the `products` directory and click **Compress** in the top toolbar.
4. Select **Zip Archive** and name it `uploads_backup_YYYYMMDD.zip`.
5. Download the created `.zip` file to your off-server storage.
6. Delete the `.zip` file from the server to conserve disk space.

### Method B: Linux Terminal / SSH (`tar`)
```bash
# Compress the uploads directory
tar -czvf uploads_backup_$(date +%Y%m%d).tar.gz -C /home/username/public_html/public uploads/

# Or sync to remote storage via rsync
rsync -avz /home/username/public_html/public/uploads/ backup-user@remote-server:/backups/kalika_uploads/
```

---

## 5. Complete Disaster Recovery / Database Restore

Follow these steps if data corruption occurs or when migrating to a new hosting server.

### Pre-Restore Safety Checklist
1. Put the storefront into maintenance mode or inform customers of a brief maintenance window.
2. If the current database is still accessible, take a snapshot backup of the current state before overwriting:
   ```bash
   mysqldump -u username -p kalika_enterprises > pre_restore_safety_dump.sql
   ```
3. Locate the latest clean backup file (`.sql`).

### Restore via phpMyAdmin
1. Open cPanel &rarr; **phpMyAdmin**.
2. Select the target database `kalika_enterprises`.
3. *(Recommended)* Select all tables and execute **Drop** to ensure no stale tables conflict.
4. Click the **Import** tab.
5. Click **Choose File** and select your clean `.sql` file.
6. Ensure Character Set is set to **utf-8**.
7. Click **Import** (or **Go**).
8. Verify that phpMyAdmin displays the success message: *"Import has been successfully finished, XXX queries executed."*

### Restore via Command-Line (`mysql`)
```bash
# Standard Linux Bash restore
mysql -u username -p kalika_enterprises < /path/to/kalika_backup_YYYYMMDD.sql
```

*Windows PowerShell Gotcha:*
PowerShell does not support `<` input redirection. Use `cmd /c` or piping:
```powershell
cmd /c "mysql -u root -p kalika_enterprises < database\backups\kalika_backup_20261011.sql"
# Or:
Get-Content -Path "database\backups\kalika_backup_20261011.sql" | mysql -u root -p kalika_enterprises
```

### Restoring Media Assets
1. Upload your `uploads_backup_YYYYMMDD.zip` into `public_html/public/`.
2. Right-click and choose **Extract**.
3. Verify that directory permissions remain `0755` and files are `0644`.
4. Ensure [`public/uploads/.htaccess`](file:///e:/Freelance%20Project/public/uploads/.htaccess) remains present to block malicious script execution.

---

## 6. Post-Restore Verification Checklist

After any database restore, perform the following validation checks immediately:

- [ ] Log in to Admin portal (`/admin/login.php`) with the master credentials.
- [ ] Verify recent orders in `/admin/orders.php` match the backup date.
- [ ] Browse `/public/products.php` and verify product thumbnails display correctly.
- [ ] Add an item to cart and test adding a note or advancing to checkout.
- [ ] Verify enterprise settings in `/admin/settings.php` (minimum order threshold ₹2,000, contact numbers, GST settings).
