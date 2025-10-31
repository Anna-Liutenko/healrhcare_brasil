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

                // Use 127.0.0.1 instead of localhost on Windows to avoid DNS issues
                $host = $dbConfig['host'];
                if (strtolower($host) === 'localhost') {
                    $host = '127.0.0.1';
                }

                $dsn = sprintf(
                    '%s:host=%s;port=%s;dbname=%s;charset=%s',
                    $dbConfig['driver'],
                    $host,
                    $dbConfig['port'],
                    $dbConfig['database'],
                    $dbConfig['charset']
                );

                try {
                    $pdo = new PDO(
                        $dsn,
                        $dbConfig['username'],
                        $dbConfig['password'],
                        $options
                    );
                } catch (PDOException $e) {
                    throw new PDOException(
                        'Database connection failed: ' . $e->getMessage(),
                        (int) $e->getCode()
                    );
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
