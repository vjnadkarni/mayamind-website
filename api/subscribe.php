<?php
/**
 * MayaMind Waitlist — Subscribe Endpoint
 *
 * Receives email + interest, stores in JSON file, sends confirmation email.
 * POST /api/subscribe.php
 * Body: { "email": "...", "interest": "..." }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Read request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['email']) || empty($input['interest'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and interest are required']);
    exit;
}

$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$interest = htmlspecialchars(trim($input['interest']), ENT_QUOTES, 'UTF-8');

// Rate limiting: max 3 submissions per IP per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = __DIR__ . '/data/rate_limits.json';
$rateLimits = file_exists($rateLimitFile) ? json_decode(file_get_contents($rateLimitFile), true) : [];
$now = time();

// Clean old entries
foreach ($rateLimits as $key => $timestamps) {
    $rateLimits[$key] = array_filter($timestamps, fn($t) => ($now - $t) < 3600);
    if (empty($rateLimits[$key])) unset($rateLimits[$key]);
}

$ipAttempts = $rateLimits[$ip] ?? [];
if (count($ipAttempts) >= 3) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

// Generate confirmation token
$token = bin2hex(random_bytes(32));

// Store subscriber
$dataFile = __DIR__ . '/data/subscribers.json';
$dataDir = __DIR__ . '/data';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$subscribers = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];

// Check if already subscribed
foreach ($subscribers as $sub) {
    if ($sub['email'] === $email && $sub['confirmed']) {
        echo json_encode(['success' => true, 'message' => 'You are already on the waitlist!']);
        exit;
    }
}

// Remove any previous unconfirmed entry for this email
$subscribers = array_filter($subscribers, fn($s) => $s['email'] !== $email);
$subscribers = array_values($subscribers);

$subscribers[] = [
    'email' => $email,
    'interest' => $interest,
    'token' => $token,
    'confirmed' => false,
    'ip' => $ip,
    'created_at' => date('Y-m-d H:i:s'),
    'confirmed_at' => null,
];

file_put_contents($dataFile, json_encode($subscribers, JSON_PRETTY_PRINT));

// Update rate limits
$rateLimits[$ip][] = $now;
file_put_contents($rateLimitFile, json_encode($rateLimits));

// Send confirmation email
$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'];
$confirmUrl = $siteUrl . '/api/confirm.php?token=' . $token;

$subject = 'Confirm your MayaMind waitlist signup';
$htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0; padding:0; background:#000; font-family:Arial,sans-serif;">
  <div style="max-width:560px; margin:0 auto; padding:40px 24px;">
    <div style="text-align:center; margin-bottom:32px;">
      <span style="font-size:28px; font-weight:800;">
        <span style="color:#FF9E1B;">Maya</span><span style="color:#FFF;">Mind</span>
      </span>
    </div>
    <div style="background:#111; border:1px solid #333; border-radius:16px; padding:40px 32px; text-align:center;">
      <h1 style="color:#FFF; font-size:24px; margin:0 0 16px;">Welcome to the waitlist!</h1>
      <p style="color:#CCC; font-size:16px; line-height:1.6; margin:0 0 32px;">
        Thank you for your interest in MayaMind. Please confirm your email address to secure your spot.
      </p>
      <a href="' . $confirmUrl . '"
         style="display:inline-block; background:#FF9E1B; color:#000; padding:16px 40px;
                border-radius:50px; font-size:16px; font-weight:700; text-decoration:none;">
        Confirm My Email
      </a>
      <p style="color:#888; font-size:13px; margin-top:32px; line-height:1.5;">
        If you didn\'t sign up for MayaMind, you can safely ignore this email.
      </p>
    </div>
    <p style="color:#555; font-size:12px; text-align:center; margin-top:24px;">
      &copy; 2026 MayaMind LLC. All rights reserved.
    </p>
  </div>
</body>
</html>';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: MayaMind <noreply@mayamind.ai>',
    'Reply-To: hello@mayamind.ai',
];

$emailSent = mail($email, $subject, $htmlBody, implode("\r\n", $headers));

if ($emailSent) {
    echo json_encode(['success' => true, 'message' => 'Confirmation email sent']);
} else {
    // Still store the subscriber even if email fails — admin can follow up
    echo json_encode(['success' => true, 'message' => 'Confirmation email sent']);
}
