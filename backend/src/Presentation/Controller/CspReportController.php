<?php

declare(strict_types=1);

namespace Presentation\Controller;

/**
 * CSP Violation Report Endpoint
 *
 * Receives CSP violation reports from browsers and logs them.
 */
class CspReportController
{
    public function report(): void
    {
        $rawData = file_get_contents('php://input');

        if (empty($rawData)) {
            http_response_code(400);
            echo json_encode(['error' => 'Empty report']);
            return;
        }

        $report = json_decode($rawData, true);
        if (!$report || !isset($report['csp-report'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid report format']);
            return;
        }

        $cspReport = $report['csp-report'];

        $documentUri = $cspReport['document-uri'] ?? 'unknown';
        $blockedUri = $cspReport['blocked-uri'] ?? 'unknown';
        $violatedDirective = $cspReport['violated-directive'] ?? 'unknown';
        $sourceFile = $cspReport['source-file'] ?? 'unknown';
        $lineNumber = $cspReport['line-number'] ?? 'unknown';

        $logMessage = sprintf(
            "%s | CSP VIOLATION | Document: %s | Blocked: %s | Directive: %s | Source: %s:%s\n",
            date('c'),
            $documentUri,
            $blockedUri,
            $violatedDirective,
            $sourceFile,
            $lineNumber
        );

        @file_put_contents(__DIR__ . '/../../../logs/security-alerts.log', $logMessage, FILE_APPEND | LOCK_EX);
        @file_put_contents(__DIR__ . '/../../../logs/csp-violations.json', json_encode($report, JSON_PRETTY_PRINT) . ",\n", FILE_APPEND | LOCK_EX);

        http_response_code(204);
    }
}
