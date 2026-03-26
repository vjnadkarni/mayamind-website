<?php
/**
 * MayaMind Waitlist — Email Confirmation Endpoint
 *
 * Validates the token and marks the subscriber as confirmed.
 * GET /api/confirm.php?token=xxx
 * Redirects to homepage with ?confirmed=true on success.
 */

$token = $_GET['token'] ?? '';

if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invalid Link</title></head>
    <body style="background:#000;color:#fff;font-family:Arial,sans-serif;display:flex;
    align-items:center;justify-content:center;min-height:100vh;margin:0;">
    <div style="text-align:center;padding:40px;">
    <h1 style="color:#FC4001;">Invalid Link</h1>
    <p>This confirmation link is invalid or has expired.</p>
    <a href="/" style="color:#FC4001;">Return to MayaMind</a>
    </div></body></html>';
    exit;
}

$dataFile = __DIR__ . '/data/subscribers.json';

if (!file_exists($dataFile)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not Found</title></head>
    <body style="background:#000;color:#fff;font-family:Arial,sans-serif;display:flex;
    align-items:center;justify-content:center;min-height:100vh;margin:0;">
    <div style="text-align:center;padding:40px;">
    <h1 style="color:#FC4001;">Not Found</h1>
    <p>No waitlist data found.</p>
    <a href="/" style="color:#FC4001;">Return to MayaMind</a>
    </div></body></html>';
    exit;
}

$subscribers = json_decode(file_get_contents($dataFile), true);
$found = false;

foreach ($subscribers as &$sub) {
    if ($sub['token'] === $token) {
        $found = true;
        if ($sub['confirmed']) {
            // Already confirmed — redirect anyway
            header('Location: /?confirmed=true');
            exit;
        }
        $sub['confirmed'] = true;
        $sub['confirmed_at'] = date('Y-m-d H:i:s');
        break;
    }
}
unset($sub);

if (!$found) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not Found</title></head>
    <body style="background:#000;color:#fff;font-family:Arial,sans-serif;display:flex;
    align-items:center;justify-content:center;min-height:100vh;margin:0;">
    <div style="text-align:center;padding:40px;">
    <h1 style="color:#FC4001;">Link Not Found</h1>
    <p>This confirmation link was not found. It may have already been used.</p>
    <a href="/" style="color:#FC4001;">Return to MayaMind</a>
    </div></body></html>';
    exit;
}

// Save updated subscribers
file_put_contents($dataFile, json_encode($subscribers, JSON_PRETTY_PRINT));

// Redirect to homepage with confirmation
header('Location: /?confirmed=true');
exit;
