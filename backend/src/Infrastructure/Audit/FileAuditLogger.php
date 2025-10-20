<?php

declare(strict_types=1);

namespace Infrastructure\Audit;

/**
 * Simple file-based audit logger used as default fallback for audit events.
 */
class FileAuditLogger
{
    /**
     * Write an audit entry (associative array) as a JSONL line to logs/collection-changes.log
     * Returns true on success, false on failure.
     */
    public function write(array $entry): bool
    {
        $logFile = __DIR__ . '/../../../logs/collection-changes.log';
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $res = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        return $res !== false;
    }
}
