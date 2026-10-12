<?php
/**
 * Kalika Enterprises - Automated Daily Database Backup Script
 * Phase 10: Generates timestamped SQL dump, enforces 14-day rolling retention,
 *          and can be invoked via cPanel Cron or CLI.
 * 
 * Recommended cPanel Cron Job (Runs daily at 02:00 AM):
 * 0 2 * * * /usr/local/bin/php /home/username/public_html/database/backup.php > /dev/null 2>&1
 */

declare(strict_types=1);

// Prevent unauthorized direct browser execution unless run from CLI or by authenticated admin
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/auth.php';
    if (!is_admin_logged_in()) {
        http_response_code(403);
        echo "403 Forbidden: Administrative authentication required.";
        exit;
    }
} else {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/../includes/db.php';

$backupDir = dirname(__DIR__) . '/database/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0750, true);
    // Protect backup directory
    file_put_contents($backupDir . '/.htaccess', "# Deny all web access to database backups\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
}

$dateStr = date('Ymd_His');
$backupFile = $backupDir . "/kalika_backup_{$dateStr}.sql";

$pdo = get_db_connection(true);
if (!$pdo) {
    $err = "[ERROR] Database connection failed for backup execution.\n";
    if ($isCli) echo $err; else error_log($err);
    exit(1);
}

try {
    $handle = fopen($backupFile, 'w');
    if (!$handle) {
        throw new Exception("Cannot open backup file for writing: {$backupFile}");
    }

    // Header info
    fwrite($handle, "-- =====================================================\n");
    fwrite($handle, "-- Kalika Enterprises - Automated Database Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Host: " . DB_HOST . " | Database: " . DB_NAME . "\n");
    fwrite($handle, "-- =====================================================\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
    fwrite($handle, "SET NAMES utf8mb4;\n\n");

    // Fetch all tables
    $tables = [];
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        fwrite($handle, "-- -----------------------------------------------------\n");
        fwrite($handle, "-- Table structure for `{$table}`\n");
        fwrite($handle, "-- -----------------------------------------------------\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

        $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
        fwrite($handle, $createRow[1] . ";\n\n");

        // Dump data rows
        $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
        $rowCount = 0;
        while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($rowCount === 0) {
                fwrite($handle, "-- Dumping data for table `{$table}`\n");
            }

            $columns = array_map(fn($col) => "`" . str_replace("`", "``", $col) . "`", array_keys($row));
            $values = array_map(function ($val) use ($pdo) {
                if ($val === null) return "NULL";
                return $pdo->quote((string)$val);
            }, array_values($row));

            $insertSql = "INSERT INTO `{$table}` (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");\n";
            fwrite($handle, $insertSql);
            $rowCount++;
        }
        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($handle, "-- Backup completed successfully.\n");
    fclose($handle);

    $fileSize = filesize($backupFile);
    $sizeFormatted = number_format($fileSize / 1024, 2) . ' KB';
    $msg = "[SUCCESS] Database backup created: {$backupFile} ({$sizeFormatted})\n";
    if ($isCli) echo $msg;

    // ---------------------------------------------------------
    // Retention Policy: Keep last 14 daily dumps, purge older
    // ---------------------------------------------------------
    $existingBackups = glob($backupDir . '/kalika_backup_*.sql');
    if ($existingBackups) {
        rsort($existingBackups); // Newest first
        $maxRetain = 14;
        if (count($existingBackups) > $maxRetain) {
            $toPurge = array_slice($existingBackups, $maxRetain);
            foreach ($toPurge as $oldFile) {
                @unlink($oldFile);
                if ($isCli) echo "[PURGE] Rotated old backup: " . basename($oldFile) . "\n";
            }
        }
    }

    if (!$isCli) {
        set_flash('success', "Database backup created successfully ({$sizeFormatted}).");
        redirect(admin_url('settings.php'));
    }

} catch (Exception $e) {
    if (isset($handle) && is_resource($handle)) {
        fclose($handle);
    }
    @unlink($backupFile);
    $errMsg = "[ERROR] Backup failed: " . $e->getMessage() . "\n";
    if ($isCli) echo $errMsg; else error_log($errMsg);
    exit(1);
}
