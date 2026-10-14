<?php
/**
 * Kalika Enterprises - Razorpay Integration & Gateway Helper
 * Handles Razorpay Orders API, Payment Verification, Captures, Refunds,
 * Webhook signature validation, and idempotent order payment transitions.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Get active Razorpay Key ID.
 */
function razorpay_get_key_id(): string
{
    return defined('RAZORPAY_KEY_ID') ? (string)RAZORPAY_KEY_ID : '';
}

/**
 * Get active Razorpay Key Secret.
 */
function razorpay_get_key_secret(): string
{
    return defined('RAZORPAY_KEY_SECRET') ? (string)RAZORPAY_KEY_SECRET : '';
}

/**
 * Get active Razorpay Webhook Secret.
 */
function razorpay_get_webhook_secret(): string
{
    return defined('RAZORPAY_WEBHOOK_SECRET') ? (string)RAZORPAY_WEBHOOK_SECRET : '';
}

/**
 * Check if Razorpay credentials are fully configured.
 */
function razorpay_is_configured(): bool
{
    return razorpay_get_key_id() !== '' && razorpay_get_key_secret() !== '';
}

/**
 * Execute HTTP API request to Razorpay REST API endpoints.
 * Automatically uses cURL if available, or falls back to native PHP HTTPS streams.
 *
 * @param string $method GET, POST, PUT, DELETE
 * @param string $endpoint API endpoint e.g. "/v1/orders"
 * @param array<string, mixed> $data Request body payload
 * @return array<string, mixed> Decoded JSON response
 * @throws Exception on network or API failure
 */
function razorpay_request(string $method, string $endpoint, array $data = []): array
{
    $keyId = razorpay_get_key_id();
    $keySecret = razorpay_get_key_secret();

    if ($keyId === '' || $keySecret === '') {
        throw new Exception('Razorpay API credentials are not configured.');
    }

    $url = 'https://api.razorpay.com' . (str_starts_with($endpoint, '/') ? $endpoint : '/' . $endpoint);
    $auth = base64_encode("{$keyId}:{$keySecret}");
    $method = strtoupper($method);

    // Locate available SSL certificate authority bundles
    $caBundle = null;
    $possibleCas = [
        __DIR__ . '/../scratch/cacert.pem',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/cert.pem',
    ];
    foreach ($possibleCas as $ca) {
        if (file_exists($ca)) {
            $caBundle = $ca;
            break;
        }
    }

    $jsonBody = !empty($data) ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

    if (function_exists('curl_init') && extension_loaded('curl')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [
            "Authorization: Basic {$auth}",
            "User-Agent: KalikaEnterprises-Razorpay/1.0",
            "Accept: application/json"
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($caBundle) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new Exception("Razorpay cURL network error: {$curlError}");
        }
    } else {
        // Native PHP HTTPS Stream Context fallback
        $headers = [
            "Authorization: Basic {$auth}",
            "User-Agent: KalikaEnterprises-Razorpay/1.0",
            "Accept: application/json"
        ];

        $streamOpts = [
            'http' => [
                'method'        => $method,
                'ignore_errors' => true,
                'timeout'       => 20,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ]
        ];

        if ($caBundle) {
            $streamOpts['ssl']['cafile'] = $caBundle;
        }

        if ($method === 'POST' || $method === 'PUT') {
            $headers[] = 'Content-Type: application/json';
            $streamOpts['http']['content'] = $jsonBody;
        }

        $streamOpts['http']['header'] = implode("\r\n", $headers);
        $context = stream_context_create($streamOpts);

        $rawResponse = @file_get_contents($url, false, $context);
        if ($rawResponse === false) {
            $lastError = error_get_last()['message'] ?? 'Unable to establish secure HTTPS connection.';
            throw new Exception("Razorpay HTTP stream error: {$lastError}");
        }

        $httpCode = 0;
        if (!empty($http_response_header) && is_array($http_response_header)) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $matches)) {
                $httpCode = (int)$matches[1];
            }
        }
    }

    $decoded = json_decode((string)$rawResponse, true);
    if (!is_array($decoded)) {
        throw new Exception("Invalid JSON response from Razorpay API (HTTP {$httpCode}): " . substr((string)$rawResponse, 0, 200));
    }

    if ($httpCode >= 400) {
        $errorMessage = $decoded['error']['description'] ?? ($decoded['error']['message'] ?? 'Gateway request failed');
        $errorCode = $decoded['error']['code'] ?? "HTTP_{$httpCode}";
        throw new Exception("Razorpay API Error [{$errorCode}]: {$errorMessage}");
    }

    return $decoded;
}

/**
 * Create a Razorpay Order.
 *
 * @param int $amountPaise Amount in paise (integer)
 * @param string $receipt Internal order number receipt (max 40 chars)
 * @param array<string, mixed> $notes Key-value metadata notes
 * @return array<string, mixed> Razorpay Order object
 */
function razorpay_create_order(int $amountPaise, string $receipt, array $notes = []): array
{
    $payload = [
        'amount'   => $amountPaise,
        'currency' => 'INR',
        'receipt'  => substr($receipt, 0, 40),
        'notes'    => $notes
    ];

    return razorpay_request('POST', '/v1/orders', $payload);
}

/**
 * Fetch a Razorpay Order by ID.
 */
function razorpay_fetch_order(string $orderId): array
{
    return razorpay_request('GET', '/v1/orders/' . urlencode($orderId));
}

/**
 * Fetch a Razorpay Payment by ID.
 */
function razorpay_fetch_payment(string $paymentId): array
{
    return razorpay_request('GET', '/v1/payments/' . urlencode($paymentId));
}

/**
 * Capture an authorized Razorpay payment.
 */
function razorpay_capture_payment(string $paymentId, int $amountPaise, string $currency = 'INR'): array
{
    return razorpay_request('POST', '/v1/payments/' . urlencode($paymentId) . '/capture', [
        'amount'   => $amountPaise,
        'currency' => $currency
    ]);
}

/**
 * Create a full or partial refund for a captured payment.
 *
 * @param string $paymentId Razorpay payment ID
 * @param int|null $amountPaise Optional refund amount in paise. If null, issues full refund.
 * @param array<string, mixed> $notes Notes for refund record
 * @return array<string, mixed>
 */
function razorpay_create_refund(string $paymentId, ?int $amountPaise = null, array $notes = []): array
{
    $payload = [
        'notes' => $notes
    ];
    if ($amountPaise !== null && $amountPaise > 0) {
        $payload['amount'] = $amountPaise;
    }

    return razorpay_request('POST', '/v1/payments/' . urlencode($paymentId) . '/refund', $payload);
}

/**
 * Verify Razorpay payment signature from client checkout modal callback.
 * Formula: HMAC-SHA256(order_id + "|" + payment_id, key_secret)
 */
function razorpay_verify_signature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature): bool
{
    $keySecret = razorpay_get_key_secret();
    if ($keySecret === '' || $razorpayOrderId === '' || $razorpayPaymentId === '' || $razorpaySignature === '') {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $keySecret);
    return hash_equals($expectedSignature, $razorpaySignature);
}

/**
 * Verify Razorpay webhook signature from X-Razorpay-Signature HTTP header.
 * Formula: HMAC-SHA256(raw_body, webhook_secret)
 */
function razorpay_verify_webhook_signature(string $rawBody, string $receivedSignature): bool
{
    $webhookSecret = razorpay_get_webhook_secret();
    if ($webhookSecret === '' || $rawBody === '' || $receivedSignature === '') {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);
    return hash_equals($expectedSignature, $receivedSignature);
}

/**
 * Record an entry into the payment_audit_log table.
 */
function payment_log_audit(
    ?int $orderId,
    ?int $paymentId,
    string $action,
    string $actorType,
    ?string $actorId,
    ?string $oldStatus,
    ?string $newStatus,
    mixed $details = null
): bool {
    global $pdo;
    if (!$pdo && function_exists('get_db_connection')) {
        $pdo = get_db_connection();
    }
    if (!$pdo) {
        return false;
    }

    $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $detailsStr = is_array($details) || is_object($details)
        ? json_encode($details, JSON_UNESCAPED_UNICODE)
        : ($details !== null ? (string)$details : null);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO payment_audit_log (
                order_id, payment_id, action, actor_type, actor_id,
                old_status, new_status, details, ip, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $orderId,
            $paymentId,
            $action,
            $actorType,
            $actorId,
            $oldStatus,
            $newStatus,
            $detailsStr,
            $ip
        ]);
    } catch (PDOException $e) {
        error_log("Failed to insert payment audit log: " . $e->getMessage());
        return false;
    }
}

/**
 * Idempotently mark an order as PAID inside an atomic transaction with row locking.
 * Safe against concurrent callbacks and duplicate webhook retries.
 *
 * @param PDO $pdo Active PDO connection
 * @param int $orderId Internal order ID
 * @param array<string, mixed> $paymentData Payment details from Razorpay
 * @param string $actorType 'customer', 'webhook', or 'admin'
 * @param string|null $actorId
 * @return bool True if successfully confirmed as paid or was already paid
 */
function order_mark_paid(PDO $pdo, int $orderId, array $paymentData, string $actorType, ?string $actorId = null): bool
{
    $wasInTransaction = $pdo->inTransaction();
    if (!$wasInTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // Lock order row for update
        $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();

        if (!$order) {
            throw new Exception("Order #{$orderId} not found during payment marking.");
        }

        $oldPaymentStatus = $order['payment_status'];
        $oldOrderStatus = $order['status'];

        $rzpPaymentId = $paymentData['id'] ?? ($paymentData['razorpay_payment_id'] ?? '');
        $rzpOrderId = $paymentData['order_id'] ?? ($paymentData['razorpay_order_id'] ?? ($order['razorpay_order_id'] ?? ''));
        $amountPaise = (int)($paymentData['amount'] ?? round((float)$order['grand_total'] * 100));
        $currency = $paymentData['currency'] ?? 'INR';
        $gatewayStatus = $paymentData['status'] ?? 'captured';
        $method = $paymentData['method'] ?? null;
        $signatureVerified = !empty($paymentData['signature_verified']) ? 1 : 0;

        // Check if payment row already exists
        $paymentRowId = null;
        if ($rzpPaymentId !== '') {
            $chkPay = $pdo->prepare("SELECT id FROM payments WHERE razorpay_payment_id = ? LIMIT 1");
            $chkPay->execute([$rzpPaymentId]);
            $paymentRowId = $chkPay->fetchColumn();

            if (!$paymentRowId) {
                $payInsert = $pdo->prepare("
                    INSERT INTO payments (
                        order_id, razorpay_order_id, razorpay_payment_id,
                        amount_paise, currency, status, method,
                        signature_verified, raw_status_from_gateway, created_at
                    ) VALUES (
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, NOW()
                    )
                ");
                $payInsert->execute([
                    $orderId,
                    $rzpOrderId,
                    $rzpPaymentId,
                    $amountPaise,
                    $currency,
                    $gatewayStatus,
                    $method,
                    $signatureVerified,
                    json_encode($paymentData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ]);
                $paymentRowId = (int)$pdo->lastInsertId();
            }
        }

        // Only transition order if not already paid
        if ($oldPaymentStatus !== 'paid') {
            $newOrderStatus = ($oldOrderStatus === 'Awaiting Payment' || $oldOrderStatus === 'Placed') ? 'Placed' : $oldOrderStatus;

            $upStmt = $pdo->prepare("
                UPDATE orders 
                SET payment_status = 'paid',
                    status = ?,
                    paid_at = COALESCE(paid_at, NOW()),
                    razorpay_order_id = COALESCE(razorpay_order_id, ?)
                WHERE id = ?
            ");
            $upStmt->execute([
                $newOrderStatus,
                $rzpOrderId ?: null,
                $orderId
            ]);

            payment_log_audit(
                $orderId,
                $paymentRowId ? (int)$paymentRowId : null,
                'payment_confirmed',
                $actorType,
                $actorId,
                $oldPaymentStatus,
                'paid',
                [
                    'razorpay_payment_id' => $rzpPaymentId,
                    'razorpay_order_id'   => $rzpOrderId,
                    'amount_paise'        => $amountPaise,
                    'method'              => $method,
                    'old_order_status'    => $oldOrderStatus,
                    'new_order_status'    => $newOrderStatus
                ]
            );
        }

        if (!$wasInTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Exception $e) {
        if (!$wasInTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("order_mark_paid failed: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Release reserved stock for an unpaid, failed, or expired order.
 * Guaranteed to execute exactly once per order (idempotent).
 *
 * @param PDO $pdo Active PDO connection
 * @param int $orderId
 * @param string $reason Reason for cancellation/stock release
 * @param string $actorType
 * @param string|null $actorId
 * @return bool True if stock was restored
 */
function order_release_reserved_stock(PDO $pdo, int $orderId, string $reason, string $actorType, ?string $actorId = null): bool
{
    $wasInTransaction = $pdo->inTransaction();
    if (!$wasInTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();

        if (!$order) {
            return false;
        }

        // If order was already paid, NEVER release stock!
        if ($order['payment_status'] === 'paid') {
            return false;
        }

        // If order is already Cancelled and stock was already released, do nothing
        if ($order['status'] === 'Cancelled' && $order['payment_status'] === 'failed') {
            return false;
        }

        // Fetch line items to restore inventory
        $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll();

        foreach ($items as $item) {
            // Find corresponding product variant by name and label
            $varStmt = $pdo->prepare("
                SELECT v.id 
                FROM product_variants v
                INNER JOIN products p ON p.id = v.product_id
                WHERE p.name = ? AND v.size_label = ?
                LIMIT 1
            ");
            $varStmt->execute([$item['product_name'], $item['variant_label']]);
            $varId = $varStmt->fetchColumn();

            if ($varId) {
                $restockStmt = $pdo->prepare("
                    UPDATE product_variants
                    SET stock_qty = stock_qty + ?,
                        stock_status = 'in_stock'
                    WHERE id = ?
                ");
                $restockStmt->execute([(int)$item['quantity'], $varId]);
            }
        }

        $oldStatus = $order['status'];
        $oldPayStatus = $order['payment_status'];

        $upStmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'Cancelled',
                payment_status = 'failed'
            WHERE id = ?
        ");
        $upStmt->execute([$orderId]);

        payment_log_audit(
            $orderId,
            null,
            'stock_released_order_cancelled',
            $actorType,
            $actorId,
            $oldStatus . '|' . $oldPayStatus,
            'Cancelled|failed',
            ['reason' => $reason]
        );

        if (!$wasInTransaction) {
            $pdo->commit();
        }

        return true;
    } catch (Exception $e) {
        if (!$wasInTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("order_release_reserved_stock failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Lazy / Scheduled cleanup of expired pending orders (> 30 minutes).
 * Releases reserved inventory and cancels pending orders.
 *
 * @param PDO $pdo
 * @return int Number of expired orders cleaned up
 */
function cleanup_expired_pending_orders(PDO $pdo): int
{
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM orders
            WHERE status = 'Awaiting Payment'
              AND payment_status = 'pending'
              AND expires_at IS NOT NULL
              AND expires_at < NOW()
            LIMIT 50
        ");
        $stmt->execute();
        $expiredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $cleaned = 0;
        foreach ($expiredIds as $oid) {
            if (order_release_reserved_stock($pdo, (int)$oid, 'Pending payment 30-minute window expired', 'system', 'cron_cleanup')) {
                $cleaned++;
            }
        }

        return $cleaned;
    } catch (PDOException $e) {
        error_log("cleanup_expired_pending_orders failed: " . $e->getMessage());
        return 0;
    }
}
