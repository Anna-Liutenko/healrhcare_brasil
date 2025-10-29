<?php

declare(strict_types=1);

namespace Infrastructure\Database;

use PDO;
use PDOException;

/**
 * Database Connection
 *
 * Singleton pattern для подключения к MySQL
 */
class Connection
{
    private static ?PDO $instance = null;

    /**
     * Приватный конструктор (Singleton)
     */
    private function __construct()
    {
    }

    /**
     * Получить PDO соединение
     *
     * @return PDO
     * @throws PDOException
     */
    public static function getInstance(): PDO
    {
        $config = require __DIR__ . '/../../../config/database.php';
        $default = getenv('DB_DEFAULT') ?: $config['default'];
        // If an instance was pre-injected (for example by the test bootstrap),
        // respect it and return immediately. Tests inject a PDO into
        // Connection::$instance via Reflection to force sqlite usage; recreating
        // here would defeat that. Only create a new connection when none exists.
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = self::createConnection();
        return self::$instance;
    }

    /**
     * Создать новое PDO соединение
     *
     * @return PDO
     * @throws PDOException
     */
    private static function createConnection(): PDO
    {
        $config = require __DIR__ . '/../../../config/database.php';
        $default = getenv('DB_DEFAULT') ?: $config['default'];
        $dbConfig = $config['connections'][$default] ?? $config['connections'][$config['default']];

        try {
            if ($dbConfig['driver'] === 'sqlite') {
                // sqlite uses file path as DSN
                $dsn = 'sqlite:' . ($dbConfig['database'] ?? ':memory:');
                $options = $dbConfig['options'] ?? [];
                $pdo = new PDO($dsn, null, null, $options);
                // Ensure foreign keys are enabled for sqlite
                $pdo->exec('PRAGMA foreign_keys = ON;');
            } else {
                // Merge options with timeout
                $options = $dbConfig['options'] ?? [];
                $timeout = $dbConfig['timeout'] ?? 5;
                $options[PDO::ATTR_TIMEOUT] = $timeout;

                // Try connection with original host first
                $originalHost = $dbConfig['host'];
                $hostsToTry = [$originalHost];

                // On Windows/XAMPP, if host is 'localhost', also try 127.0.0.1
                if (is_string($originalHost) && strtolower($originalHost) === 'localhost') {
                    $hostsToTry[] = '127.0.0.1';
                }

                $lastException = null;
                $pdo = null;

                foreach ($hostsToTry as $host) {
                    try {
                        $dsn = sprintf(
                            '%s:host=%s;port=%s;dbname=%s;charset=%s',
                            $dbConfig['driver'],
                            $host,
                            $dbConfig['port'],
                            $dbConfig['database'],
                            $dbConfig['charset']
                        );

                        // Log connection attempt
                        $logMsg = sprintf(
                            '[DB] Attempting connection: driver=%s, host=%s, port=%s, db=%s, user=%s',
                            $dbConfig['driver'],
                            $host,
                            $dbConfig['port'],
                            $dbConfig['database'],
                            $dbConfig['username']
                        );
                        @file_put_contents(
                            __DIR__ . '/../../../logs/connection-attempts.log',
                            date('Y-m-d H:i:s') . ' ' . $logMsg . PHP_EOL,
                            FILE_APPEND | LOCK_EX
                        );

                        $pdo = new PDO(
                            $dsn,
                            $dbConfig['username'],
                            $dbConfig['password'],
                            $options
                        );

                        // Success!
                        @file_put_contents(
                            __DIR__ . '/../../../logs/connection-attempts.log',
                            date('Y-m-d H:i:s') . ' [DB] ✓ Connection successful with host: ' . $host . PHP_EOL,
                            FILE_APPEND | LOCK_EX
                        );

                        return $pdo;
                    } catch (PDOException $e) {
                        $lastException = $e;
                        @file_put_contents(
                            __DIR__ . '/../../../logs/connection-attempts.log',
                            date('Y-m-d H:i:s') . ' [DB] ✗ Connection failed with host: ' . $host . ' | Error: ' . $e->getMessage() . PHP_EOL,
                            FILE_APPEND | LOCK_EX
                        );
                    }
                }

                // All attempts failed
                if ($lastException) {
                    throw $lastException;
                }
            }

            return $pdo;
        } catch (PDOException $e) {
            // Log error details
            @file_put_contents(
                __DIR__ . '/../../../logs/connection-errors.log',
                date('Y-m-d H:i:s') . ' [FATAL] ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')' . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            throw new PDOException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }

    /**
     * Закрыть соединение
     */
    public static function close(): void
    {
        self::$instance = null;
    }

    /**
     * Начать транзакцию
     */
    public static function beginTransaction(): void
    {
        self::getInstance()->beginTransaction();
    }

    /**
     * Подтвердить транзакцию
     */
    public static function commit(): void
    {
        self::getInstance()->commit();
    }

    /**
     * Откатить транзакцию
     */
    public static function rollBack(): void
    {
        self::getInstance()->rollBack();
    }
}
