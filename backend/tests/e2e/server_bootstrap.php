<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Determine log path inside backend/logs for visibility in the repo
$logsDir = realpath(__DIR__ . '/../../logs') ?: (__DIR__ . '/../../logs');
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0777, true);
}
$logFile = $logsDir . '/server_bootstrap.log';
$localLogFile = __DIR__ . '/server_bootstrap_local.log';

function writeLog(array $data) {
    global $logFile, $localLogFile;
    $data['timestamp'] = date('c');
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    @file_put_contents($logFile, $json, FILE_APPEND | LOCK_EX);
    @file_put_contents($localLogFile, $json, FILE_APPEND | LOCK_EX);
}

// Write early environment snapshot
writeLog([
    'phase' => 'early-env',
    'DB_DEFAULT' => getenv('DB_DEFAULT'),
    'DB_DATABASE' => getenv('DB_DATABASE'),
    'PHP_BINARY' => defined('PHP_BINARY') ? PHP_BINARY : null,
    'cwd' => getcwd(),
]);

// Try to require autoload if available
$autoloadPaths = [
    __DIR__ . '/../../../vendor/autoload.php', // when executed from backend/tests/E2E
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];
foreach ($autoloadPaths as $p) {
    if (is_file($p)) {
        require_once $p;
        writeLog(['phase' => 'autoload-required', 'path' => $p]);
        break;
    }
}

// If DB_DEFAULT=sqlite, try to ensure sqlite DB exists and apply idempotent schema if available
$dbDefault = getenv('DB_DEFAULT') ?: 'mysql';
$dbPath = getenv('DB_DATABASE') ?: null;

if ($dbDefault === 'sqlite') {
    $dbFile = $dbPath ?: (__DIR__ . '/../../tmp/e2e.sqlite');
    // Ensure folder exists
    $dbDir = dirname($dbFile);
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }

    writeLog(['phase' => 'sqlite-prepare', 'db_file' => $dbFile]);

    try {
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Enable foreign keys
        $pdo->exec('PRAGMA foreign_keys = ON;');
        writeLog(['phase' => 'sqlite-opened', 'db_file' => $dbFile]);

        // Try to apply schema SQL if present
        $schemaPath = __DIR__ . '/../Integration/schema/sqlite_schema.sql';
        if (is_file($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            if ($sql !== false && trim($sql) !== '') {
                $pdo->exec($sql);
                writeLog(['phase' => 'schema-applied', 'schema' => $schemaPath]);
            } else {
                writeLog(['phase' => 'schema-empty', 'schema' => $schemaPath]);
            }
        } else {
            writeLog(['phase' => 'schema-not-found', 'schema' => $schemaPath]);
        }

        // Try to inject PDO into Infrastructure\Database\Connection::$instance via Reflection
        $injected = false;
        $injectError = null;
        if (class_exists('\Infrastructure\Database\Connection')) {
            try {
                $ref = new ReflectionClass('\Infrastructure\Database\Connection');
                if ($ref->hasProperty('instance')) {
                    $prop = $ref->getProperty('instance');
                    $prop->setAccessible(true);
                    $prop->setValue(null, $pdo);
                    $injected = true;
                    writeLog(['phase' => 'injected-into-connection', 'class' => '\\Infrastructure\\Database\\Connection']);
                } else {
                    writeLog(['phase' => 'connection-property-missing']);
                }
            } catch (Throwable $e) {
                $injectError = (string)$e;
                writeLog(['phase' => 'inject-exception', 'error' => $injectError]);
            }
        } else {
            writeLog(['phase' => 'connection-class-missing']);
        }

    } catch (Throwable $e) {
        writeLog(['phase' => 'sqlite-exception', 'message' => (string)$e]);
    }
} else {
    writeLog(['phase' => 'not-sqlite', 'DB_DEFAULT' => $dbDefault]);
}

// Final marker
writeLog(['phase' => 'bootstrap-complete']);

// Do not exit/stop execution; server should continue to handle the request
