<?php
/**
 * Kalika Enterprises - Razorpay Payment Verification Handler
 * Verifies HMAC-SHA256 signature, validates payment details from Razorpay API,
 * idempotently transitions order state, records payment snapshot, and handles errors.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/razorpay.php';

// Customer must be authenticated
require_login('checkout.php');

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(public_url('orders.php'));
}

if (!csrf_verify()) {
    set_flash('error', 'Security token expired or invalid. Please retry your payment.');
    $orderId = (int)($_POST['order_id'] ?? 0);
    redirect($orderId > 0 ? public_url("payment.php?order_id={$orderId}") : public_url('checkout.php'));
}

$orderId = (int)($_POST['order_id'] ?? 0);
$razorpayOrderId = trim((string)($_POST['razorpay_order_id'] ?? ''));
$razorpayPaymentId = trim((string)($_POST['razorpay_payment_id'] ?? ''));
$razorpaySignature = trim((string)($_POST['razorpay_signature'] ?? ''));

if ($orderId <= 0 || !$pdo) {
    set_flash('error', 'Invalid order reference.');
    redirect(public_url('orders.php'));
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect(public_url('orders.php'));
}

// IDOR Authorization guard
if ((int)$order['user_id'] !== (int)$user['id'] && !is_admin()) {
    http_response_code(403);
    set_flash('error', 'Unauthorized access to order.');
    redirect(public_url('orders.php'));
}

// If already paid, redirect straight to confirmation
if ($order['payment_status'] === 'paid') {
    set_flash('info', "Order #{$order['order_no']} has already been verified and paid.");
    redirect(public_url("order.php?id={$orderId}&confirmed=1"));
}

// 1. Verify HMAC-SHA256 signature
$sigValid = razorpay_verify_signature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);

if (!$sigValid) {
    payment_log_audit(
        $orderId,
        null,
        'signature_verification_failed',
        'customer',
        (string)$user['id'],
        $order['payment_status'],
        'failed',
        [
            'razorpay_order_id'   => $razorpayOrderId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'reason'              => 'HMAC-SHA256 signature mismatch'
        ]
    );

    set_flash('error', 'Payment verification failed: Gateway signature mismatch. Please retry or contact support.');
    redirect(public_url("payment.php?order_id={$orderId}"));
}

// 2. Fetch payment from Razorpay API to confirm status, amount, currency, and matching order_id
try {
    $payment = razorpay_fetch_payment($razorpayPaymentId);
    $expectedPaise = (int)round((float)$order['grand_total'] * 100);

    // Verify order ID match
    if (!empty($payment['order_id']) && $payment['order_id'] !== $razorpayOrderId) {
        throw new Exception("Payment order reference mismatch. Expected: {$razorpayOrderId}, Received: {$payment['order_id']}");
    }

    // Verify amount match
    if ((int)($payment['amount'] ?? 0) !== $expectedPaise) {
        throw new Exception("Payment amount mismatch. Expected: {$expectedPaise} paise, Received: " . ($payment['amount'] ?? 0) . " paise.");
    }

    // Verify currency
    if (($payment['currency'] ?? '') !== 'INR') {
        throw new Exception("Payment currency mismatch. Expected INR, Received: " . ($payment['currency'] ?? ''));
    }

    // If status is 'authorized', capture it
    if ($payment['status'] === 'authorized') {
        $payment = razorpay_capture_payment($razorpayPaymentId, $expectedPaise, 'INR');
    }

    if ($payment['status'] === 'captured') {
        // Mark paid in single transaction with row locking
        $payment['signature_verified'] = 1;
        $payment['razorpay_order_id'] = $razorpayOrderId;
        $payment['razorpay_payment_id'] = $razorpayPaymentId;

        order_mark_paid($pdo, $orderId, $payment, 'customer', (string)$user['id']);

        set_flash('success', "Payment successful! Order #{$order['order_no']} is confirmed and scheduled for fulfillment.");
        redirect(public_url("order.php?id={$orderId}&confirmed=1"));
    } elseif ($payment['status'] === 'pending') {
        // Gateway status unclear; do not mark failed prematurely
        payment_log_audit(
            $orderId,
            null,
            'payment_status_pending_gateway',
            'customer',
            (string)$user['id'],
            $order['payment_status'],
            'pending',
            $payment
        );

        set_flash('info', "Your payment is currently being confirmed by your bank. Your order #{$order['order_no']} will update once processed.");
        redirect(public_url("order.php?id={$orderId}&pending_reconcile=1"));
    } else {
        throw new Exception("Gateway reported unfulfilled payment status: " . ($payment['status'] ?? 'unknown'));
    }

} catch (Exception $e) {
    error_log("Razorpay verification error for order #{$orderId}: " . $e->getMessage());

    payment_log_audit(
        $orderId,
        null,
        'payment_verification_error',
        'customer',
        (string)$user['id'],
        $order['payment_status'],
        $order['payment_status'],
        ['error' => $e->getMessage()]
    );

    set_flash('error', 'Payment confirmation issue: ' . $e->getMessage() . '. You may retry or contact our desk.');
    redirect(public_url("payment.php?order_id={$orderId}"));
}
