<?php
/**
 * Kalika Enterprises - Wholesale Tax Invoice & PDF Print Template
 * Phase 6: Formal GST invoice layout, strict IDOR authorization, A4 print optimization,
 *          and browser print-to-PDF support.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$orderId = (int)($_GET['order'] ?? 0);

if ($orderId <= 0 || !$pdo) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>Invoice Not Found</h1><p>Invalid order reference provided.</p></body></html>';
    exit;
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><h1>Invoice Not Found</h1><p>The requested invoice does not exist.</p></body></html>';
    exit;
}

// IDOR Security Check: Check customer authorization or admin session
$currentUser = current_user();
$isAdmin = is_admin();

if (!$currentUser && !$isAdmin) {
    redirect(public_url('login.php?return=' . urlencode('invoice.php?order=' . $orderId)));
}

if (!$isAdmin && (int)$order['user_id'] !== (int)$currentUser['id']) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family: sans-serif; text-align: center; padding: 4rem;"><h1>403 Forbidden</h1><p>You do not have authorization to view this invoice.</p></body></html>';
    exit;
}

// Fetch order items snapshot
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$itemStmt->execute([$orderId]);
$orderItems = $itemStmt->fetchAll();

// Settings
$businessName = get_setting('business_name', 'Kalika Enterprises');
$businessAddress = get_setting('business_address', 'Shop No. 4, Raghukul Sankul, Jopul Road, Pimpalgaon (Baswant), Tal. Niphad, Dist. Nashik, Maharashtra – 422201');
$businessEmail = get_setting('business_email', 'deepakpawar4312@gmail.com');
$businessGstin = get_setting('business_gstin', 'Pending Confirmation');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - <?= e($order['order_no']) ?> | <?= e($businessName) ?></title>
    <link rel="stylesheet" href="<?= public_url('assets/css/style.css') ?>">
    <style>
        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 1.5rem;
        }
        .invoice-sheet {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            padding: 3rem;
            border-radius: var(--radius-md);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #e2e8f0;
        }
        .invoice-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.875rem;
        }
        .invoice-table th, .invoice-table td {
            padding: 0.75rem 0.6rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .invoice-table th {
            background-color: #f8fafc;
            font-weight: 700;
            color: #334155;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .invoice-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>
<body>

<!-- Print & Navigation Action Bar (Hidden in Print) -->
<div class="no-print" style="max-width: 820px; margin: 0 auto 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <a href="<?= public_url('order.php?id=' . (int)$order['id']) ?>" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
        <span>Back to Order Details</span>
    </a>
    <div style="display: flex; gap: 0.75rem;">
        <button onclick="window.print()" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print / Save PDF
        </button>
    </div>
</div>

<!-- Formal Tax Invoice Sheet -->
<div class="invoice-sheet">
    <!-- Invoice Top Header -->
    <div class="invoice-header-row">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0 0 0.35rem; color: #0f172a;">
                <?= e($businessName) ?>
            </h1>
            <div style="font-size: 0.8125rem; color: #475569; max-width: 440px; line-height: 1.45; margin-bottom: 0.35rem;">
                <?= e($businessAddress) ?>
            </div>
            <div style="font-size: 0.8125rem; color: #475569;">
                Email: <strong><?= e($businessEmail) ?></strong> &bull; GSTIN: <strong><?= e($businessGstin) ?></strong>
            </div>
        </div>

        <div style="text-align: right;">
            <div style="font-size: 1.25rem; font-weight: 800; color: #d97706; letter-spacing: 0.5px;">
                TAX INVOICE
            </div>
            <div style="font-size: 0.875rem; color: #334155; margin-top: 0.35rem;">
                <strong>Invoice No:</strong> <?= e($order['order_no']) ?><br>
                <strong>Date:</strong> <?= date('d M Y', strtotime($order['created_at'])) ?><br>
                <strong>Payment Method:</strong> <?= ($order['payment_method'] ?? 'online') === 'online' ? 'Online (Razorpay)' : 'Cash on Delivery' ?><br>
                <strong>Payment Status:</strong> <?= strtoupper(e(str_replace('_', ' ', $order['payment_status'] ?? 'pending'))) ?><br>
                <?php if (!empty($order['razorpay_order_id'])): ?>
                    <strong>Gateway Order Ref:</strong> <?= e($order['razorpay_order_id']) ?><br>
                <?php endif; ?>
                <strong>Fulfillment Status:</strong> <?= e($order['status']) ?>
            </div>
        </div>
    </div>

    <!-- Bill To / Deliver To Metadata -->
    <div style="display: flex; justify-content: space-between; gap: 2rem; margin-bottom: 1.5rem; font-size: 0.875rem;">
        <div style="flex: 1;">
            <strong style="color: #0f172a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; display: block; margin-bottom: 0.35rem;">
                Billed &amp; Delivered To:
            </strong>
            <div style="font-weight: 700; color: #0f172a; font-size: 1rem; margin-bottom: 0.2rem;">
                <?= e($order['customer_name']) ?>
            </div>
            <div style="color: #475569; margin-bottom: 0.25rem;">
                Contact: +91 <?= e($order['mobile']) ?>
            </div>
            <div style="color: #475569; line-height: 1.45;">
                <?= nl2br(e($order['delivery_address'])) ?>
            </div>
        </div>

        <div style="flex: 1; text-align: right;">
            <strong style="color: #0f172a; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; display: block; margin-bottom: 0.35rem;">
                Supply &amp; Logistics Details:
            </strong>
            <div style="color: #475569; line-height: 1.45;">
                <strong>Place of Supply:</strong> Maharashtra (State Code 27)<br>
                <strong>Delivery Mode:</strong> Regional Distribution Van<br>
                <strong>Delivery Surcharge:</strong> Free Regional (₹0.00)<br>
                <strong>Fulfillment Timeline:</strong> 2–3 Working Days
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="invoice-table">
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">#</th>
                <th>Item &amp; Brand</th>
                <th>Variant Size</th>
                <th style="text-align: right;">Unit Rate</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: center;">GST %</th>
                <th style="text-align: right;">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderItems as $idx => $item): ?>
                <tr>
                    <td style="text-align: center; color: #64748b;"><?= $idx + 1 ?></td>
                    <td>
                        <strong style="color: #0f172a;"><?= e($item['product_name']) ?></strong>
                        <div style="font-size: 0.75rem; color: #64748b;">Brand: <?= e($item['brand_name']) ?></div>
                    </td>
                    <td style="color: #475569;"><?= e($item['variant_label']) ?></td>
                    <td style="text-align: right;"><?= format_currency($item['unit_price']) ?></td>
                    <td style="text-align: center; font-weight: 600;"><?= (int)$item['quantity'] ?></td>
                    <td style="text-align: center; color: #0284c7; font-weight: 600;">
                        <?= number_format((float)$item['gst_percent'], 0) ?>%
                    </td>
                    <td style="text-align: right; font-weight: 700; color: #0f172a;">
                        <?= format_currency($item['line_total']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Calculation Block -->
    <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
        <div style="width: 320px; font-size: 0.875rem;">
            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0;">
                <span style="color: #64748b;">Subtotal:</span>
                <strong><?= format_currency($order['subtotal']) ?></strong>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0;">
                <span style="color: #64748b;">GST Amount (<?= (int)$order['prices_include_gst'] === 1 ? 'Included' : 'Extra' ?>):</span>
                <span style="color: #64748b; font-weight: 600;"><?= format_currency($order['gst_total']) ?></span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.35rem 0;">
                <span style="color: #64748b;">Delivery Logistics:</span>
                <span style="color: #059669; font-weight: 700;">FREE (₹0.00)</span>
            </div>

            <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-top: 2px solid #0f172a; margin-top: 0.35rem; font-size: 1.25rem; font-weight: 800; color: #0f172a;">
                <span>Grand Total:</span>
                <span><?= format_currency($order['grand_total']) ?></span>
            </div>
        </div>
    </div>

    <!-- Terms & Regulatory Disclaimers -->
    <div style="margin-top: 3rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; font-size: 0.75rem; color: #64748b; line-height: 1.5;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem;">
            <div>
                <p style="font-weight: 700; margin: 0 0 0.35rem; color: #334155;">Terms &amp; Wholesale Conditions:</p>
                <ol style="margin: 0; padding-left: 1.25rem;">
                    <li>Goods supplied strictly for authorized commercial distribution and retail sale.</li>
                    <li>Payment settlement: <?= ($order['payment_method'] ?? 'online') === 'online' ? 'Processed online via Razorpay gateway.' : 'Settled upon physical van arrival as agreed.' ?></li>
                    <li>Return / Damage claims must be notified within 2 days of delivery with intact packaging. Refund is initiated within 2 days of approval, subject to applicable conditions; the time to reach your account depends on your bank/payment method.</li>
                    <li>Free delivery applies for orders meeting the ₹2,000 threshold across Niphad, Dindori, Chandwad, Sinnar, and Yeola.</li>
                </ol>
            </div>
            <div style="text-align: center; min-width: 180px;">
                <div style="height: 40px;"></div>
                <div style="border-top: 1px dashed #94a3b8; padding-top: 0.35rem; font-weight: 600; color: #334155;">
                    Authorized Signatory<br>
                    <?= e($businessName) ?>
                </div>
            </div>
        </div>
        <div style="text-align: center; color: #94a3b8; font-size: 0.7rem;">
            This is a computer generated commercial wholesale tax invoice issued by <?= e($businessName) ?>.
        </div>
    </div>
</div>

</body>
</html>
