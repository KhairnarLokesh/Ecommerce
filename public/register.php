<?php
/**
 * Kalika Enterprises - Customer Registration
 * Phase 5: Account creation with mobile uniqueness, password hashing, and auto-login
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart.php';

require_guest();

$errors = [];
$nameVal = '';
$mobileVal = '';
$emailVal = '';
$addressVal = '';
$returnUrl = trim($_GET['return'] ?? $_POST['return'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token invalid or expired. Please refresh the page.';
    } else {
        $nameVal = trim($_POST['name'] ?? '');
        $mobileVal = trim($_POST['mobile'] ?? '');
        $emailVal = trim($_POST['email'] ?? '');
        $addressVal = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($nameVal) || mb_strlen($nameVal) > 100) {
            $errors[] = 'Please enter a valid shop or contact name (max 100 characters).';
        }

        if (empty($mobileVal) || !preg_match('/^[6-9]\d{9}$/', $mobileVal)) {
            $errors[] = 'Please enter a valid 10-digit Indian mobile number.';
        }

        if (!empty($emailVal) && (!filter_var($emailVal, FILTER_VALIDATE_EMAIL) || mb_strlen($emailVal) > 150)) {
            $errors[] = 'Please enter a valid email address or leave it blank.';
        }

        if (empty($addressVal) || mb_strlen($addressVal) < 10) {
            $errors[] = 'Please enter complete shop delivery address (minimum 10 characters).';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Password confirmation does not match.';
        }

        // Check if mobile already exists in database
        if (empty($errors)) {
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
                    $stmt->execute([$mobileVal]);
                    if ($stmt->fetch()) {
                        $errors[] = 'A retailer account with this mobile number already exists. Please sign in instead.';
                    } else {
                        // Insert new user
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $insertStmt = $pdo->prepare("
                            INSERT INTO users (name, mobile, email, password_hash, address)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $insertStmt->execute([
                            $nameVal,
                            $mobileVal,
                            !empty($emailVal) ? $emailVal : null,
                            $passwordHash,
                            $addressVal
                        ]);

                        $newUserId = (int)$pdo->lastInsertId();

                        // Auto-authenticate customer session
                        login_user([
                            'id'      => $newUserId,
                            'name'    => $nameVal,
                            'mobile'  => $mobileVal,
                            'email'   => $emailVal,
                            'address' => $addressVal
                        ]);

                        // Merge guest cart items into newly created user account
                        cart_merge_on_login($pdo, $newUserId);

                        set_flash('success', 'Account created successfully! Welcome to Kalika Enterprises wholesale distribution.');
                        
                        // Sanitize return redirect
                        if (!empty($returnUrl) && preg_match('/^[a-z0-9_-]+\.php(\?[a-z0-9_=&-]+)?$/i', $returnUrl)) {
                            redirect(public_url($returnUrl));
                        } else {
                            redirect(public_url('profile.php'));
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    $errors[] = 'A database error occurred during registration. Please try again.';
                }
            } else {
                $errors[] = 'Database connection error. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Register Retail Account | Kalika Enterprises';
$currentPage = 'account';
$pageDescription = 'Create a wholesale customer account for your retail store with Kalika Enterprises in Niphad, Dindori, Chandwad, Sinnar, or Yeola.';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-form-side">
        <div class="auth-form-card" style="max-width: 480px;">
            <div class="auth-form-card__eyebrow">Retailer Registration</div>
            <h1 class="auth-form-card__title">Create Account</h1>
            <p class="auth-form-card__sub">Access wholesale distributor rates and digital ordering.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert" style="margin-bottom: 20px;">
                    <div>
                        <strong>Registration failed:</strong>
                        <ul style="margin: 6px 0 0 16px;">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= public_url('register.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>" method="POST" novalidate>
                <?= csrf_field() ?>
                <?php if (!empty($returnUrl)): ?>
                    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label" for="reg_name">Store Name / Contact Person *</label>
                    <input type="text" id="reg_name" name="name" class="form-control" placeholder="e.g. Mahalakshmi Provision Stores / Nilesh Patil" value="<?= e($nameVal) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_mobile">10-Digit Mobile Number *</label>
                    <div class="input-prefix">
                        <span class="input-prefix-addon">+91</span>
                        <input type="tel" id="reg_mobile" name="mobile" class="form-control" pattern="[6-9][0-9]{9}" placeholder="9876543210" value="<?= e($mobileVal) ?>" required>
                    </div>
                    <div class="field-hint">Your mobile number is your primary login identifier.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_email">Email Address (Optional)</label>
                    <input type="email" id="reg_email" name="email" class="form-control" placeholder="store@example.com" value="<?= e($emailVal) ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_address">Shop Delivery Address *</label>
                    <textarea id="reg_address" name="address" class="form-control" rows="3" placeholder="Shop No., Market Yard, Village/City, PIN code" required><?= e($addressVal) ?></textarea>
                    <div class="field-hint">Must be located in Niphad, Dindori, Chandwad, Sinnar, or Yeola.</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label" for="reg_password">Password *</label>
                        <input type="password" id="reg_password" name="password" class="form-control" minlength="8" placeholder="Min 8 chars" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg_confirm_password">Confirm *</label>
                        <input type="password" id="reg_confirm_password" name="confirm_password" class="form-control" minlength="8" placeholder="Repeat" required>
                    </div>
                </div>

                <button type="submit" class="dd-btn dd-btn--mint" style="width: 100%; justify-content: center; margin-top: 8px;">
                    Create Retailer Account
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </button>
            </form>

            <div class="auth-form-card__footer">
                Already registered? <a href="<?= public_url('login.php' . (!empty($returnUrl) ? '?return=' . urlencode($returnUrl) : '')) ?>">Sign in to account</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
