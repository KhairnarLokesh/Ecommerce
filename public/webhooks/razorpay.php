<?php
/**
 * Kalika Enterprises - Razorpay Webhook Handler
 * Verifies X-Razorpay-Signature HMAC-SHA256 with webhook secret, deduplicates
 * event IDs in webhook_events, and idempotently handles payment and refund events.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/razorpay.php';

header('Content-Type: application/json; charset=UTF-8');

// 1. Read Raw Request Body
$rawBody = file_get_contents('php://input');
if ($rawBody === false || trim($rawBody) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request body']);
    exit;
}

// 2. Read Security Headers
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
$eventId = $_SERVER['HTTP_X_RAZORPAY_EVENT_ID'] ?? '';

if ($signature === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing X-Razorpay-Signature header']);
    exit;
}

// 3. Cryptographically Verify Webhook Signature
if (!razorpay_verify_webhook_signature($rawBody, $signature)) {
    http_response_code(400);
    error_log("Razorpay Webhook: Invalid signature received from IP " . get_client_ip());
    echo json_encode(['error' => 'Invalid webhook signature']);
    exit;
}

// 4. Decode JSON Payload
$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload structure']);
    exit;
}

$eventType = (string)$event['event'];
$payload = $event['payload'] ?? [];
$payloadHash = hash('sha256', $rawBody);

if ($eventId === '') {
    $eventId = 'evt_' . substr($payloadHash, 0, 24);
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

// 5. Deduplicate Event ID in webhook_events Table
try {
    $dedupeStmt = $pdo->prepare("
        INSERT INTO webhook_events (event_id, event_type, payload_hash, outcome, processed_at)
        VALUES (?, ?, ?, 'processing', NOW())
    ");
    $dedupeStmt->execute([$eventId, $eventType, $payloadHash]);
    $webhookRecordId = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
    // 23000 = Unique constraint violation (duplicate event ID)
    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
        payment_log_audit(
            null,
            null,
            'webhook_duplicate_ignored',
            'webhook',
            $eventId,
            null,
            null,
            ['event_type' => $eventType]
        );

        http_response_code(200);
        echo json_encode(['status' => 'duplicate_ignored', 'event_id' => $eventId]);
        exit;
    }
    throw $e;
}

// 6. Handle Specific Event Types
$outcome = 'processed';

try {
    switch ($eventType) {
        case 'order.paid':
        case 'payment.captured':
            $paymentEntity = $payload['payment']['entity'] ?? [];
            $rzpOrderId = $paymentEntity['order_id'] ?? ($payload['order']['entity']['id'] ?? '');

            if ($rzpOrderId !== '') {
                $orderStmt = $pdo->prepare("SELECT id, payment_status, status FROM orders WHERE razorpay_order_id = ? LIMIT 1");
                $orderStmt->execute([$rzpOrderId]);
                $matchedOrder = $orderStmt->fetch();

                if ($matchedOrder) {
                    $orderId = (int)$matchedOrder['id'];
                    $paymentEntity['signature_verified'] = 1;
                    order_mark_paid($pdo, $orderId, $paymentEntity, 'webhook', $eventId);
                } else {
                    $outcome = 'order_not_found';
                }
            }
            break;

        case 'payment.authorized':
            // If merchant account uses manual capture, auto-capture authorized payments
            $paymentEntity = $payload['payment']['entity'] ?? [];
            $rzpPaymentId = $paymentEntity['id'] ?? '';
            $amountPaise = (int)($paymentEntity['amount'] ?? 0);

            if ($rzpPaymentId !== '' && $amountPaise > 0) {
                try {
                    razorpay_capture_payment($rzpPaymentId, $amountPaise);
                } catch (Exception $e) {
                    error_log("Webhook payment.authorized auto-capture note: " . $e->getMessage());
                }
            }
            break;

        case 'payment.failed':
            $paymentEntity = $payload['payment']['entity'] ?? [];
            $rzpOrderId = $paymentEntity['order_id'] ?? '';
            $rzpPaymentId = $paymentEntity['id'] ?? '';

            if ($rzpOrderId !== '') {
                $orderStmt = $pdo->prepare("SELECT id, payment_status, status FROM orders WHERE razorpay_order_id = ? LIMIT 1");
                $orderStmt->execute([$rzpOrderId]);
                $matchedOrder = $orderStmt->fetch();

                if ($matchedOrder && $matchedOrder['payment_status'] !== 'paid') {
                    $orderId = (int)$matchedOrder['id'];

                    // Record failed payment row
                    if ($rzpPaymentId !== '') {
                        $failInsert = $pdo->prepare("
                            INSERT INTO payments (
                                order_id, razorpay_order_id, razorpay_payment_id,
                                amount_paise, currency, status, method,
                                signature_verified, raw_status_from_gateway, created_at
                            ) VALUES (
                                ?, ?, ?,
                                ?, 'INR', 'failed', ?,
                                0, ?, NOW()
                            )
                            ON DUPLICATE KEY UPDATE status = 'failed', updated_at = NOW()
                        ");
                        $failInsert->execute([
                            $orderId,
                            $rzpOrderId,
                            $rzpPaymentId,
                            (int)($paymentEntity['amount'] ?? 0),
                            $paymentEntity['method'] ?? null,
                            json_encode($paymentEntity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        ]);
                    }

                    payment_log_audit(
                        $orderId,
                        null,
                        'webhook_payment_failed',
                        'webhook',
                        $eventId,
                        $matchedOrder['payment_status'],
                        'failed_attempt',
                        [
                            'error_code'        => $paymentEntity['error_code'] ?? 'unknown',
                            'error_description' => $paymentEntity['error_description'] ?? 'Payment failed at gateway'
                        ]
                    );
                }
            }
            break;

        case 'refund.processed':
            $refundEntity = $payload['refund']['entity'] ?? [];
            $rzpRefundId = $refundEntity['id'] ?? '';
            $rzpPaymentId = $refundEntity['payment_id'] ?? '';
            $refundAmount = (int)($refundEntity['amount'] ?? 0);

            if ($rzpRefundId !== '') {
                // Find order via payment record
                $findOrder = $pdo->prepare("
                    SELECT p.id AS payment_id, p.order_id, o.grand_total, o.payment_status 
                    FROM payments p
                    INNER JOIN orders o ON o.id = p.order_id
                    WHERE p.razorpay_payment_id = ?
                    LIMIT 1
                ");
                $findOrder->execute([$rzpPaymentId]);
                $orderRow = $findOrder->fetch();

                if ($orderRow) {
                    $orderId = (int)$orderRow['order_id'];
                    $paymentId = (int)$orderRow['payment_id'];

                    // Upsert into refunds table
                    $refStmt = $pdo->prepare("
                        INSERT INTO refunds (
                            order_id, payment_id, razorpay_refund_id, amount_paise,
                            status, reason, created_at
                        ) VALUES (
                            ?, ?, ?, ?,
                            'processed', ?, NOW()
                        )
                        ON DUPLICATE KEY UPDATE status = 'processed', updated_at = NOW()
                    ");
                    $refStmt->execute([
                        $orderId,
                        $paymentId,
                        $rzpRefundId,
                        $refundAmount,
                        $refundEntity['notes']['reason'] ?? 'Processed via Razorpay gateway'
                    ]);

                    // Calculate net refunds for this order
                    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paise), 0) FROM refunds WHERE order_id = ? AND status = 'processed'");
                    $sumStmt->execute([$orderId]);
                    $totalRefundedPaise = (int)$sumStmt->fetchColumn();

                    $totalOrderPaise = (int)round((float)$orderRow['grand_total'] * 100);
                    $newPayStatus = ($totalRefundedPaise >= $totalOrderPaise) ? 'refunded' : 'partially_refunded';

                    $upOrd = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
                    $upOrd->execute([$newPayStatus, $orderId]);

                    payment_log_audit(
                        $orderId,
                        $paymentId,
                        'refund_processed',
                        'webhook',
                        $eventId,
                        $orderRow['payment_status'],
                        $newPayStatus,
                        [
                            'razorpay_refund_id'   => $rzpRefundId,
                            'refund_amount_paise'  => $refundAmount,
                            'total_refunded_paise' => $totalRefundedPaise
                        ]
                    );
                }
            }
            break;

        case 'refund.created':
        case 'refund.failed':
            $refundEntity = $payload['refund']['entity'] ?? [];
            $rzpRefundId = $refundEntity['id'] ?? '';
            $statusStr = ($eventType === 'refund.failed') ? 'failed' : 'pending';

            if ($rzpRefundId !== '') {
                $upRef = $pdo->prepare("UPDATE refunds SET status = ?, updated_at = NOW() WHERE razorpay_refund_id = ?");
                $upRef->execute([$statusStr, $rzpRefundId]);
            }
            break;

        default:
            $outcome = 'ignored_event_type';
            break;
    }

    // Update outcome in webhook_events
    $upEvt = $pdo->prepare("UPDATE webhook_events SET outcome = ? WHERE id = ?");
    $upEvt->execute([$outcome, $webhookRecordId]);

} catch (Exception $e) {
    error_log("Razorpay Webhook processing exception for event {$eventId}: " . $e->getMessage());
    $pdo->prepare("UPDATE webhook_events SET outcome = 'error' WHERE id = ?")->execute([$webhookRecordId]);
}

// 7. Always respond with HTTP 200 OK quickly
http_response_code(200);
echo json_encode([
    'status'   => 'ok',
    'event_id' => $eventId,
    'outcome'  => $outcome
]);
