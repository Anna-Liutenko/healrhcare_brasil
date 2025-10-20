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
                $dsn = sprintf(
                    '%s:host=%s;port=%s;dbname=%s;charset=%s',
                    $dbConfig['driver'],
                    $dbConfig['host'],
                    $dbConfig['port'],
                    $dbConfig['database'],
                    $dbConfig['charset']
                );

                // Merge options with timeout
                $options = $dbConfig['options'] ?? [];
                $timeout = $dbConfig['timeout'] ?? 5;
                $options[PDO::ATTR_TIMEOUT] = $timeout;

                $pdo = new PDO(
                    $dsn,
                    $dbConfig['username'],
                    $dbConfig['password'],
                    $options
                );
            }

            return $pdo;
        } catch (PDOException $e) {
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
