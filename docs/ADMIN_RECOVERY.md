# Kalika Enterprises – Administrator Password Recovery & Account Restoration Manual

This guide documents the procedures for the business owner of **Kalika Enterprises** to regain administrative access in the event of lost credentials, forgotten passwords, or account lockout.

---

## 1. Authentication Architecture Overview

The Kalika Enterprises administrative system is engineered for single-owner store management with enterprise security safeguards:

1. **Table Structure (`admins`)**:
   - `id`: Primary identifier (ID 1 for the business owner).
   - `username`: Unique username (e.g., `admin`).
   - `password_hash`: Secure Bcrypt/Argon2 one-way cryptographic hash.
   - `must_change_password`: Boolean flag (default `1` on reset or creation). When `1`, access to all management modules is blocked until a new strong password is set.
   - `failed_attempts`: Counter for consecutive invalid credentials.
   - `locked_until`: Datetime timestamp. Automatically set to `NOW() + 15 MINUTE` when 5 consecutive failures occur.
   - `last_login`: Audit timestamp of latest successful authentication.
2. **Brute-Force Lockout Defense**:
   - **5 failed attempts** trigger an automatic **15-minute lockout**.
   - Generic error messages (`"Invalid username or password"`) are always returned to prevent user enumeration and timing attacks.
   - All attempts (success, failure, lockout) are logged in `admin_login_log`.
3. **Audit Logging**:
   - Every password change and administrative operation is permanently recorded in `admin_audit_log`.

---

## 2. Recovery Method 1: Command-Line (CLI) Reset (Recommended)

If you have SSH access to your VPS or access to the **Terminal** feature in cPanel / hosting control panel, this is the fastest and safest method.

### Step 1: Open Terminal & Navigate to Project Root
```bash
cd /home/username/public_html
# (or the directory where Kalika Enterprises is deployed)
```

### Step 2: Run the Admin Provisioning Script
The provisioning script `database/create_admin.php` is strictly CLI-only and refuses to execute over HTTP:

```bash
php database/create_admin.php admin "NewPassphrase2026!#"
```

Alternatively, run interactively without arguments:
```bash
php database/create_admin.php
# Enter Admin Username [admin]: admin
# Enter Strong Admin Password (min 12 chars): NewPassphrase2026!#
```

### Step 3: Script Actions
The script will:
1. Validate that the password is at least 12 characters long.
2. Hash the password using Bcrypt (`cost => 12`).
3. Set `must_change_password = 1`.
4. Reset `failed_attempts = 0` and `locked_until = NULL` (clearing any active lockout).

### Step 4: CRITICAL SECURITY STEP – Delete the Script
Immediately delete the provisioning script after use:
```bash
# Linux / cPanel:
rm database/create_admin.php

# Windows:
del database\create_admin.php
```

---

## 3. Recovery Method 2: Direct Database / phpMyAdmin Reset

If SSH/Terminal is unavailable, you can reset the password directly via **phpMyAdmin** or your hosting MySQL console.

### Step 1: Log in to phpMyAdmin
1. Access your hosting control panel (cPanel, DirectAdmin, or TiDB Cloud Console).
2. Open **phpMyAdmin** and select the database: `kalika_enterprises`.
3. Click on the `admins` table.

### Step 2: Generate a Bcrypt Password Hash
Because passwords cannot be stored in plain text, you must generate a valid password hash. You can generate one using:

#### Option A: Local PHP CLI
Run on any computer with PHP:
```bash
php -r "echo password_hash('YourNewSecurePass2026!', PASSWORD_BCRYPT, ['cost' => 12]);"
```
Example output:
```
$2y$12$K7zXyV3aB9cW0dE1fG2h3uI4j5k6l7m8n9o0p1q2r3s4t5u6v7w8x
```

#### Option B: Use Standard Pre-Generated Temporary Hash
If you cannot generate a hash, use this pre-computed Bcrypt hash for the temporary password:  
**Temporary Password**: `Admin@Kalika2026`  
**Bcrypt Hash**:
```
$2y$10$DC4jmY.5QX.2KKT07WDGDuARQ7jRdWPhdk8R3FFS8SD6g8Bdlb7QS
```

### Step 3: Execute the SQL Update Query
In phpMyAdmin, navigate to the **SQL** tab and run:

```sql
UPDATE `admins` 
SET `password_hash` = '$2y$10$DC4jmY.5QX.2KKT07WDGDuARQ7jRdWPhdk8R3FFS8SD6g8Bdlb7QS',
    `must_change_password` = 1,
    `failed_attempts` = 0,
    `locked_until` = NULL
WHERE `username` = 'admin';
```

> [!IMPORTANT]
> Setting `locked_until = NULL` and `failed_attempts = 0` is required to immediately clear any active 15-minute brute-force lockout. Setting `must_change_password = 1` forces the admin to choose their own personal password upon logging in.

---

## 4. Recovery Method 3: One-Time Emergency Reset Script

If neither SSH nor phpMyAdmin SQL querying is available, you may deploy a temporary one-time script via cPanel File Manager or FTP.

### Step 1: Create `database/emergency_reset.php`
Using cPanel File Manager or FTP, create a file named `database/emergency_reset.php` with the following contents:

```php
<?php
declare(strict_types=1);

// Configuration & Database
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

// One-time security key: replace with a secret string only you know
$secretKey = 'ReplaceWithYourSecretToken2026';

if (($_GET['key'] ?? '') !== $secretKey) {
    http_response_code(403);
    die('403 Forbidden: Invalid authorization key.');
}

$tempPassword = 'Admin@Emergency2026!';
$hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    $stmt = $pdo->prepare("
        UPDATE admins 
        SET password_hash = ?,
            must_change_password = 1,
            failed_attempts = 0,
            locked_until = NULL
        WHERE username = 'admin'
    ");
    $stmt->execute([$hash]);

    echo "<h1>Admin Password Reset Successful</h1>";
    echo "<p>Temporary Password: <code>{$tempPassword}</code></p>";
    echo "<p><strong>CRITICAL WARNING:</strong> Delete this file (<code>database/emergency_reset.php</code>) immediately now!</p>";
    echo "<p><a href='../admin/login.php'>Proceed to Admin Login &rarr;</a></p>";
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
```

### Step 2: Trigger Once Over HTTPS
Visit the script in your web browser:
```
https://yourdomain.com/database/emergency_reset.php?key=ReplaceWithYourSecretToken2026
```

### Step 3: DELETE THE FILE IMMEDIATELY
> [!CAUTION]
> Leaving `emergency_reset.php` on the web server allows anyone who finds it to reset your credentials. Delete the file immediately after running it:
> 1. In cPanel File Manager: Select `database/emergency_reset.php` -> Click **Delete** (check "Skip trash").
> 2. Or via SSH: `rm database/emergency_reset.php`.

---

## 5. Post-Recovery Checklist

After completing any recovery method, follow this verification checklist:

1. **Log in via Admin Login**:
   - Navigate to `/admin/login.php` (or your configured `ADMIN_LOGIN_PATH`).
   - Enter username (`admin`) and the temporary/new password.
2. **Complete Forced Password Change**:
   - The system will immediately intercept the session and present the **First Login: Create Secure Password** screen (`admin/change-password.php`).
   - Enter the temporary password as the current password.
   - Enter a new personal passphrase meeting the policy requirements:
     - Minimum 12 characters
     - At least one uppercase letter (A-Z)
     - At least one lowercase letter (a-z)
     - At least one digit (0-9)
     - At least one special symbol (`!@#$%^&*`)
   - Confirm and save.
3. **Verify Audit Trail**:
   - Open `/admin/activity-log.php` from the navigation bar.
   - Confirm that the `password_change` event was logged with timestamp, admin username, and your IP address.
4. **Confirm File Cleanliness**:
   - Verify that neither `database/create_admin.php` nor `database/emergency_reset.php` remain on the server.
