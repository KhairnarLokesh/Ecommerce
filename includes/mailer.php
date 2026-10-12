<?php
/**
 * Kalika Enterprises - Transactional Mailer & SMTP Client
 * 
 * Lightweight, zero-dependency SMTP client supporting SSL (465) and STARTTLS (587)
 * Used for delivering real OTP verification codes and transactional customer notifications.
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Mask an email address for privacy in UI notices.
 * Example: sunil.patil@example.com -> s***l@example.com
 */
function mask_email(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return $email;
    }
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $masked = $name[0] . '*';
    } else {
        $masked = $name[0] . str_repeat('*', min(5, max(3, $len - 2))) . $name[$len - 1];
    }
    return $masked . '@' . $domain;
}

/**
 * Check if SMTP delivery is configured in the environment.
 */
function is_smtp_configured(): bool {
    $enabled = defined('SMTP_ENABLED') ? (bool)SMTP_ENABLED : false;
    $host = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
    $user = defined('SMTP_USER') ? (string)SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';

    return $enabled && !empty($host) && !empty($user) && !empty($pass);
}

/**
 * Send an email via SMTP.
 *
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient display name
 * @param string $subject Subject line
 * @param string $htmlBody HTML content
 * @param string $textBody Plain text fallback content
 * @return array{success: bool, message: string}
 */
function send_smtp_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): array {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid recipient email address'];
    }

    if (!is_smtp_configured()) {
        return ['success' => false, 'message' => 'SMTP is not configured in config/config.php'];
    }

    $host = defined('SMTP_HOST') ? (string)SMTP_HOST : '';
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $secure = defined('SMTP_SECURE') ? strtolower((string)SMTP_SECURE) : 'tls';
    $user = defined('SMTP_USER') ? (string)SMTP_USER : '';
    $pass = defined('SMTP_PASS') ? (string)SMTP_PASS : '';
    $fromEmail = defined('SMTP_FROM_EMAIL') && !empty(SMTP_FROM_EMAIL) ? (string)SMTP_FROM_EMAIL : $user;
    $fromName = defined('SMTP_FROM_NAME') && !empty(SMTP_FROM_NAME) ? (string)SMTP_FROM_NAME : (defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Kalika Enterprises');

    $timeout = 10; // seconds
    $socketHost = ($secure === 'ssl' || $port === 465) ? "ssl://{$host}" : "tcp://{$host}";

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]
    ]);

    $socket = @stream_socket_client(
        "{$socketHost}:{$port}",
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        $msg = "SMTP connect failed to {$host}:{$port} ({$errno}: {$errstr})";
        error_log($msg);
        return ['success' => false, 'message' => $msg];
    }

    stream_set_timeout($socket, $timeout);

    $readResponse = function() use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $data .= $line;
            // Multiline responses have '-' after the 3-digit code; last line has ' '
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $sendCommand = function(string $cmd) use ($socket, $readResponse): string {
        fwrite($socket, $cmd . "\r\n");
        return $readResponse();
    };

    try {
        $greeting = $readResponse();
        if (!str_starts_with($greeting, '220')) {
            throw new RuntimeException("Unexpected SMTP greeting: " . trim($greeting));
        }

        $ehlo = $sendCommand("EHLO " . gethostname());
        if (!str_starts_with($ehlo, '250')) {
            throw new RuntimeException("EHLO failed: " . trim($ehlo));
        }

        // Handle STARTTLS for port 587
        if ($secure === 'tls' || ($port === 587 && str_contains($ehlo, 'STARTTLS'))) {
            $starttls = $sendCommand("STARTTLS");
            if (!str_starts_with($starttls, '220')) {
                throw new RuntimeException("STARTTLS rejected: " . trim($starttls));
            }

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                throw new RuntimeException("TLS handshake negotiation failed");
            }

            // Re-send EHLO over encrypted channel
            $ehloTls = $sendCommand("EHLO " . gethostname());
            if (!str_starts_with($ehloTls, '250')) {
                throw new RuntimeException("EHLO over TLS failed: " . trim($ehloTls));
            }
        }

        // Authenticate
        $auth = $sendCommand("AUTH LOGIN");
        if (!str_starts_with($auth, '334')) {
            throw new RuntimeException("AUTH LOGIN rejected: " . trim($auth));
        }

        $userResp = $sendCommand(base64_encode($user));
        if (!str_starts_with($userResp, '334')) {
            throw new RuntimeException("SMTP username rejected: " . trim($userResp));
        }

        $passResp = $sendCommand(base64_encode($pass));
        if (!str_starts_with($passResp, '235')) {
            throw new RuntimeException("SMTP credentials invalid: " . trim($passResp));
        }

        // Envelope
        $fromResp = $sendCommand("MAIL FROM:<{$fromEmail}>");
        if (!str_starts_with($fromResp, '250')) {
            throw new RuntimeException("MAIL FROM failed: " . trim($fromResp));
        }

        $rcptResp = $sendCommand("RCPT TO:<{$toEmail}>");
        if (!str_starts_with($rcptResp, '250')) {
            throw new RuntimeException("RCPT TO failed for {$toEmail}: " . trim($rcptResp));
        }

        $dataResp = $sendCommand("DATA");
        if (!str_starts_with($dataResp, '354')) {
            throw new RuntimeException("DATA command failed: " . trim($dataResp));
        }

        // Build MIME payload
        $boundary = '=_kalika_' . md5(uniqid((string)time(), true));
        $date = date('r');
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedToName = '=?UTF-8?B?' . base64_encode($toName) . '?=';

        $rawMessage = "Date: {$date}\r\n";
        $rawMessage .= "From: {$encodedFromName} <{$fromEmail}>\r\n";
        $rawMessage .= "To: {$encodedToName} <{$toEmail}>\r\n";
        $rawMessage .= "Reply-To: {$fromEmail}\r\n";
        $rawMessage .= "Subject: {$encodedSubject}\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";

        // Plain Text part
        $plainContent = !empty($textBody) ? $textBody : strip_tags($htmlBody);
        $rawMessage .= "--{$boundary}\r\n";
        $rawMessage .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $rawMessage .= chunk_split(base64_encode($plainContent)) . "\r\n";

        // HTML part
        $rawMessage .= "--{$boundary}\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $rawMessage .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $rawMessage .= "--{$boundary}--\r\n";
        $rawMessage .= ".";

        $sendMsg = $sendCommand($rawMessage);
        if (!str_starts_with($sendMsg, '250')) {
            throw new RuntimeException("Message sending failed: " . trim($sendMsg));
        }

        $sendCommand("QUIT");
        fclose($socket);

        return ['success' => true, 'message' => 'Email sent successfully via SMTP'];
    } catch (Throwable $e) {
        if (is_resource($socket)) {
            @fclose($socket);
        }
        $error = "SMTP error: " . $e->getMessage();
        error_log($error);
        return ['success' => false, 'message' => $error];
    }
}

/**
 * Generate a responsive, branded HTML email for OTP verification and dispatch it.
 *
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $otpCode 6-digit numeric OTP code
 * @return array{success: bool, message: string}
 */
function send_otp_email(string $toEmail, string $toName, string $otpCode): array {
    $businessName = defined('BUSINESS_NAME') ? BUSINESS_NAME : 'Kalika Enterprises';
    $businessTagline = defined('BUSINESS_TAGLINE') ? BUSINESS_TAGLINE : 'Wholesale Food & Beverage Distribution';
    $businessAddress = defined('BUSINESS_ADDRESS') ? BUSINESS_ADDRESS : 'Pimpalgaon Baswant, Niphad, Nashik';
    $greetingName = !empty($toName) ? htmlspecialchars($toName) : 'Retailer Partner';

    $subject = "Your {$businessName} Verification Code: {$otpCode}";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$subject}</title>
<style>
  body { margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1E293B; }
  .email-wrapper { max-width: 580px; margin: 30px auto; background-color: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
  .email-header { background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); padding: 32px 24px; text-align: center; color: #FFFFFF; }
  .email-header h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: 0.5px; color: #F59E0B; }
  .email-header p { margin: 6px 0 0; font-size: 13px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1px; }
  .email-body { padding: 36px 32px; }
  .email-body h2 { margin: 0 0 12px; font-size: 20px; color: #0F172A; }
  .email-body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #475569; }
  .otp-card { background: #FFFBEB; border: 2px dashed #F59E0B; border-radius: 10px; padding: 24px; text-align: center; margin: 28px 0; }
  .otp-code { font-family: 'Courier New', Courier, monospace; font-size: 38px; font-weight: 800; letter-spacing: 8px; color: #B45309; margin: 0; }
  .otp-validity { margin-top: 10px; font-size: 13px; color: #92400E; font-weight: 600; }
  .security-alert { background: #F1F5F9; border-left: 4px solid #64748B; padding: 12px 16px; font-size: 13px; color: #475569; margin: 24px 0 12px; }
  .email-footer { background-color: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 24px; text-align: center; font-size: 12px; color: #64748B; line-height: 1.5; }
</style>
</head>
<body>
<div class="email-wrapper">
  <div class="email-header">
    <h1>{$businessName}</h1>
    <p>{$businessTagline}</p>
  </div>
  <div class="email-body">
    <h2>Password Reset Verification</h2>
    <p>Hello <strong>{$greetingName}</strong>,</p>
    <p>We received a request to verify your account or reset your password for your <strong>{$businessName}</strong> retailer portal.</p>
    
    <div class="otp-card">
      <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: #B45309; margin-bottom: 8px;">Your 6-Digit Verification Code</div>
      <div class="otp-code">{$otpCode}</div>
      <div class="otp-validity">&bull; Valid for 5 Minutes &bull; Single-Use Only</div>
    </div>

    <p>Enter this code on the website verification screen to proceed.</p>

    <div class="security-alert">
      <strong>Security Notice:</strong> If you did not request this verification code, please ignore this email. Your password will remain unchanged, and your account remains secure. Never share this code with anyone.
    </div>
  </div>
  <div class="email-footer">
    {$businessName} &bull; Wholesale Distribution<br>
    {$businessAddress}<br>
    <em>This is an automated system notification. Please do not reply directly to this email.</em>
  </div>
</div>
</body>
</html>
HTML;

    $plainText = "{$businessName} - Password Reset Verification Code\n\n"
               . "Hello {$toName},\n\n"
               . "Your 6-digit verification code is: {$otpCode}\n\n"
               . "This code is valid for 5 minutes and can only be used once.\n\n"
               . "If you did not request this code, you can safely ignore this email.\n\n"
               . "{$businessName}\n{$businessAddress}";

    return send_smtp_mail($toEmail, $toName, $subject, $html, $plainText);
}
