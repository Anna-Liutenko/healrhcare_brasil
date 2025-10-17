<?php

// Test bootstrap: inject sqlite in-memory PDO into Connection via Reflection to avoid production API changes

require_once __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Database\Connection;

// create file-backed sqlite PDO so server (started separately) and tests share DB
$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0777, true);
}

$sqlitePath = $tmpDir . DIRECTORY_SEPARATOR . 'e2e.sqlite';
// Start fresh for test runs to avoid stale state
if (file_exists($sqlitePath)) {
    @unlink($sqlitePath);
}

$pdo = new PDO('sqlite:' . $sqlitePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
// Ensure foreign keys are enforced in sqlite
$pdo->exec('PRAGMA foreign_keys = ON;');

// create minimal schema for tests that rely on DB
$schemaFile = __DIR__ . '/Integration/schema/sqlite_schema.sql';
if (file_exists($schemaFile)) {
    $sql = file_get_contents($schemaFile);
    $pdo->exec($sql);
    // mark schema as loaded for tests to avoid re-running schema creation
    $GLOBALS['TEST_SCHEMA_LOADED'] = true;
}

// inject into Connection::$instance via Reflection
$ref = new ReflectionClass(Connection::class);
$prop = $ref->getProperty('instance');
$prop->setAccessible(true);
$prop->setValue(null, $pdo);

// expose PDO for tests if needed
$GLOBALS['TEST_PDO'] = $pdo;
