<?php
// Simple HTTP-level E2E script for the publish feature
// Usage: php run_e2e_http.php --base-url="http://localhost" --api-prefix="/api" --out="../tmp/menu_result.json"
// The script will create a page, publish it, fetch the public menu and page, and write the menu JSON to the output file.

$options = getopt('', ['base-url::', 'api-prefix::', 'out::', 'created-by::', 'login-user::', 'login-pass::']);
$baseUrl = rtrim($options['base-url'] ?? 'http://localhost', '/');
$apiPrefix = $options['api-prefix'] ?? '/api';
$outPath = $options['out'] ?? __DIR__ . '/../tmp/menu_result.json';
// createdBy can be provided explicitly; otherwise may be set from login response
$createdBy = $options['created-by'] ?? null;
$loginUser = $options['login-user'] ?? null;
$loginPass = $options['login-pass'] ?? null;

// Authorization header to include in subsequent requests when logged in
$authHeader = null;

function http_request(string $method, string $url, $data = null, array $headers = []): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Accept: application/json']));
    if ($data !== null) {
        $json = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, ['Content-Type: application/json']));
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return ['code' => $code, 'body' => $body, 'error' => $err];
}

// Helper to fail fast
function fail(string $msg, int $code = 1)
{
    fwrite(STDERR, "FAIL: $msg\n");
    exit($code);
}

$headers = [];

// If login credentials were provided, attempt to login and obtain a bearer token
if ($loginUser !== null && $loginPass !== null) {
    $loginUrl = $baseUrl . $apiPrefix . '/auth/login';
    $res = http_request('POST', $loginUrl, ['username' => $loginUser, 'password' => $loginPass]);
    if ($res['error']) {
        fail('HTTP error on login: ' . $res['error']);
    }
    if ($res['code'] < 200 || $res['code'] >= 300) {
        fwrite(STDOUT, "Login failed with HTTP {$res['code']}, proceeding with fallback created-by if provided\n");
    } else {
        $body = json_decode($res['body'], true);
        if (!empty($body['token'])) {
            $authHeader = 'Authorization: Bearer ' . $body['token'];
            $headers[] = $authHeader;
            // If createdBy was not provided, set it to the logged-in user's id
            if ($createdBy === null && !empty($body['user']['id'])) {
                $createdBy = $body['user']['id'];
            }
        }
    }
}

// 1) Create page
$createUrl = $baseUrl . $apiPrefix . '/pages';
$slug = 'e2e-test-' . bin2hex(random_bytes(4));
$payload = [
    'title' => 'E2E Test Page',
    'slug' => $slug,
    'createdBy' => $createdBy,
    'status' => 'draft',
    'showInMenu' => true,
    'menu_title' => 'E2E Menu'
];

$res = http_request('POST', $createUrl, $payload, array_merge($headers, ['Content-Type: application/json']));
if ($res['error']) {
    fail('HTTP error on create: ' . $res['error']);
}
if ($res['code'] < 200 || $res['code'] >= 300) {
    fail('Create returned HTTP ' . $res['code'] . ' body: ' . $res['body']);
}
$body = json_decode($res['body'], true);
// API may return 'id' or 'page_id' depending on deployment; accept either
if (empty($body['id']) && empty($body['page_id'])) {
    fail('Create response missing id/page_id: ' . $res['body']);
}
$pageId = $body['id'] ?? $body['page_id'];

// 2) Publish page
$publishUrl = $baseUrl . $apiPrefix . '/pages/' . $pageId . '/publish';
$res = http_request('PUT', $publishUrl, null);
if ($res['error']) {
    fail('HTTP error on publish: ' . $res['error']);
}
if ($res['code'] < 200 || $res['code'] >= 300) {
    fail('Publish returned HTTP ' . $res['code'] . ' body: ' . $res['body']);
}

// 3) Fetch public menu
$menuUrl = $baseUrl . $apiPrefix . '/menu/public';
$res = http_request('GET', $menuUrl);
if ($res['error']) {
    fail('HTTP error on get menu: ' . $res['error']);
}
if ($res['code'] < 200 || $res['code'] >= 300) {
    fail('Menu returned HTTP ' . $res['code'] . ' body: ' . $res['body']);
}
$menu = json_decode($res['body'], true);
if (!is_array($menu)) {
    fail('Menu response not JSON array: ' . $res['body']);
}

// 4) Fetch public page HTML by slug
$pageUrl = $baseUrl . '/' . $slug;
$res = http_request('GET', $pageUrl);
if ($res['error']) {
    fail('HTTP error on get page: ' . $res['error']);
}
if ($res['code'] < 200 || $res['code'] >= 300) {
    fail('Page returned HTTP ' . $res['code'] . ' body: ' . $res['body']);
}
$html = $res['body'];

// Persist results
@mkdir(dirname($outPath), 0755, true);
file_put_contents($outPath, json_encode(['pageId' => $pageId, 'slug' => $slug, 'menu' => $menu, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

fwrite(STDOUT, "E2E OK: pageId=$pageId slug=$slug output=$outPath\n");
exit(0);
