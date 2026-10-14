<?php
/**
 * Kalika Enterprises - Expired Pending Orders Cleanup Script
 * 
 * Automatically releases reserved stock for orders stuck in 'Awaiting Payment'
 * where 'expires_at' has passed (> 30 minutes from checkout initiation).
 * 
 * Scheduled execution recommendation:
 * Run every 5 minutes via system cron or cPanel cron:
 *   * /5 * * * * php /path/to/project/cron/cleanup_pending_orders.php > /dev/null 2>&1
 * 
 * Or via secured HTTP URL with secret key:
 *   https://yourdomain.com/cron/cleanup_pending_orders.php?key=<RAZORPAY_WEBHOOK_SECRET>
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/razorpay.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // If accessed via web, require authentication via webhook secret or cron key
    $cronKey = $_GET['key'] ?? ($_SERVER['HTTP_X_CRON_KEY'] ?? '');
    $validKey = defined('RAZORPAY_WEBHOOK_SECRET') ? (string)RAZORPAY_WEBHOOK_SECRET : '';

    if ($validKey === '' || !hash_equals($validKey, (string)$cronKey)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden: Invalid or missing authorization key']);
        exit;
    }
}

$pdo = get_db_connection(true);
if (!$pdo) {
    $msg = "Database connection unavailable for pending order cleanup.";
    error_log($msg);
    if ($isCli) {
        fwrite(STDERR, "[ERROR] {$msg}\n");
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $msg]);
        exit;
    }
}

$cleanedCount = cleanup_expired_pending_orders($pdo);
$timestamp = date('Y-m-d H:i:s');
$statusMsg = "[{$timestamp}] Cleanup executed. Expired orders cancelled and stock released: {$cleanedCount}";

if ($isCli) {
    echo "{$statusMsg}\n";
    exit(0);
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'status'        => 'success',
        'cleaned_count' => $cleanedCount,
        'timestamp'     => $timestamp
    ]);
    exit;
}
