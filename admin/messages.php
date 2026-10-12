<?php
/**
 * Kalika Enterprises - Customer Messages & Inquiries Desk
 * Phase 7: View, mark as read, and manage contact inquiries from the public storefront.
 */

declare(strict_types=1);

$adminPage = 'messages';
$adminTitle = 'Customer Inquiries';

require_once __DIR__ . '/includes/header.php';

$error = '';

// Handle Actions (Mark Read, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token invalid or expired.';
    } else {
        $action = trim($_POST['action'] ?? '');
        $msgId = (int)($_POST['message_id'] ?? 0);

        if ($action === 'mark_read' && $msgId > 0 && $pdo) {
            $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
            $stmt->execute([$msgId]);
            set_flash('success', 'Message marked as read.');
            redirect(admin_url('messages.php'));
        } elseif ($action === 'delete' && $msgId > 0 && $pdo) {
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            $stmt->execute([$msgId]);
            set_flash('success', 'Message deleted.');
            redirect(admin_url('messages.php'));
        }
    }
}

// Fetch all messages
$messages = [];
if ($pdo) {
    try {
        $messages = $pdo->query("SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load contact messages: " . $e->getMessage());
    }
}
?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; margin-bottom: 0.25rem;">Retailer Inquiries &amp; Messages</h1>
            <p style="font-size: 0.9375rem; color: var(--text-muted); margin-bottom: 0;">
                Inbound inquiries submitted through the public storefront contact desk.
            </p>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert" style="margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="card" style="padding: 1.5rem;">
    <h2 style="font-size: 1.15rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-light);">
        Messages Received (<?= count($messages) ?>)
    </h2>

    <?php if (empty($messages)): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 3.5rem 1rem; margin: 0;">
            No contact inquiries received yet.
        </p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($messages as $msg): ?>
                <?php $isRead = ((int)$msg['is_read'] === 1); ?>
                <div style="border: 1px solid <?= $isRead ? '#e2e8f0' : '#f59e0b' ?>; border-left: 4px solid <?= $isRead ? '#94a3b8' : '#f59e0b' ?>; border-radius: var(--radius-sm); padding: 1.25rem; background: <?= $isRead ? '#ffffff' : '#fffbeb' ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.6rem;">
                                <strong style="font-size: 1.05rem; color: var(--color-primary);"><?= e($msg['name']) ?></strong>
                                <span class="badge <?= $isRead ? 'badge-stock-out' : 'badge-warning' ?>" style="font-size: 0.7rem;">
                                    <?= $isRead ? 'Read' : 'New Inquiry' ?>
                                </span>
                            </div>
                            <div style="font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.15rem;">
                                Mobile: <strong>+91 <?= e($msg['mobile']) ?></strong> &bull; Service Area: <strong><?= e($msg['area']) ?></strong>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 0.75rem; color: var(--text-muted); margin-right: 0.5rem;">
                                <?= date('d M Y, h:i A', strtotime($msg['created_at'])) ?>
                            </span>

                            <?php if (!$isRead): ?>
                                <form action="<?= admin_url('messages.php') ?>" method="POST" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline" style="font-size: 0.75rem;">
                                        Mark Read
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form action="<?= admin_url('messages.php') ?>" method="POST" onsubmit="return confirm('Delete this inquiry?');" style="margin: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline" style="color: var(--color-danger); border-color: var(--border-color); font-size: 0.75rem;">
                                    &times; Delete
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div style="font-size: 0.9375rem; color: #334155; line-height: 1.5; background: #ffffff; padding: 0.85rem; border-radius: var(--radius-sm); border: 1px solid #e2e8f0;">
                        <?= nl2br(e($msg['message'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
