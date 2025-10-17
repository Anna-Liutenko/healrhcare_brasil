<?php
// Simple endpoint to save debug logs from the editor
// Accepts JSON POST: { logs: [...], source: 'editor', ts: '...' }

$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'empty payload']);
    exit;
}

$data = json_decode($input, true);
if (!$data || !isset($data['logs'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid payload']);
    exit;
}

$logs = $data['logs'];
$source = isset($data['source']) ? $data['source'] : 'unknown';
$ts = isset($data['ts']) ? $data['ts'] : date('c');

$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

$filename = sprintf('%s/editor-debug-%s.log', $logsDir, date('Ymd_His'));

$content = "# Debug log from: " . $source . "\n# ts: " . $ts . "\n# entries: " . count($logs) . "\n\n";
foreach ($logs as $entry) {
    $content .= sprintf("%s [%s] %s\n", $entry['time'] ?? '', strtoupper($entry['type'] ?? ''), $entry['message'] ?? '');
    if (!empty($entry['payload'])) {
        $content .= $entry['payload'] . "\n";
    }
    $content .= "---\n";
}

file_put_contents($filename, $content);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'file' => $filename]);
