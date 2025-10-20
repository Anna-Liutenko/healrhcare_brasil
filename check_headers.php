<?php
$url = 'http://localhost/healthcare-cms-backend/public/page/glavnaya-stranitsa';
$headers = get_headers($url, 1);

echo "HTTP Headers:\n";
echo "Content-Security-Policy: " . ($headers['Content-Security-Policy'] ?? '(not set)') . "\n";
echo "X-Content-Type-Options: " . ($headers['X-Content-Type-Options'] ?? '(not set)') . "\n";
echo "X-Frame-Options: " . ($headers['X-Frame-Options'] ?? '(not set)') . "\n";

if (isset($headers['Content-Security-Policy'])) {
    $csp = $headers['Content-Security-Policy'];
    if (strpos($csp, "'unsafe-inline'") !== false) {
        echo "\n✓ CSP allows 'unsafe-inline' - inline scripts ALLOWED\n";
    } else if (preg_match("/script-src[^;]*'nonce-[a-f0-9]+'/", $csp)) {
        echo "\n⚠ CSP uses nonce-based script tags - inline scripts need nonce attribute\n";
    } else {
        echo "\n✗ CSP blocks inline scripts (no 'unsafe-inline' and no nonce)\n";
    }
}
